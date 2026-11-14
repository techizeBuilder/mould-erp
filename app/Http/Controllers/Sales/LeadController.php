<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\HandlesProductLines;
use App\Models\Lead;
use App\Models\LeadItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    use HandlesProductLines;

    public function index(Request $request)
    {
        $leads = Lead::with(['items', 'qualification', 'assignee:id,name'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('company_name', 'like', $term)
                    ->orWhere('contact_person', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('city', 'like', $term));
            })
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('industry'), fn ($q) => $q->where('industry', $request->industry))
            ->when($request->filled('product_type'), fn ($q) => $q->whereHas('items', fn ($i) => $i->where('product_type', $request->product_type)))
            ->when($request->filled('product_id'), fn ($q) => $q->whereHas('items', fn ($i) => $i->where('product_id', $request->product_id)))
            ->latest('enquiry_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales.leads.index', [
            'leads' => $leads,
            'products' => Product::orderBy('code')->get(['id', 'code', 'name']),
            'statusCounts' => Lead::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'sourceCounts' => Lead::select('source', DB::raw('count(*) as total'))->groupBy('source')->orderByDesc('total')->pluck('total', 'source'),
        ]);
    }

    public function show(Lead $lead)
    {
        return view('sales.leads.show', [
            'lead' => $lead->load(['items.product', 'items.mould', 'qualification', 'customer', 'assignee:id,name', 'creator:id,name']),
            'quotations' => $lead->quotations()->current()->latest('id')->get(),
            'followUps' => $lead->followUps()->with('assignee:id,name', 'product:id,name')->latest('due_at')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $lead = new Lead(['enquiry_date' => now(), 'status' => 'new', 'assigned_to' => $request->user()->id]);

        return view('sales.leads.form', $this->formData($request, $lead, Lead::nextCode()));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $lead = DB::transaction(function () use ($request, $data) {
            $lead = new Lead($data);
            $lead->created_by = $request->user()->id;
            $lead->save();
            $this->syncItems($lead, $request->input('items'));

            return $lead;
        });

        $duplicate = Lead::where('phone', $lead->phone)->whereKeyNot($lead->id)->value('code');
        $message = "Lead \"{$lead->code} – {$lead->company_name}\" created.";

        return redirect()->route('sales.leads.show', $lead)->with('success', $duplicate ? "$message Note: $duplicate has the same mobile number." : $message);
    }

    public function edit(Request $request, Lead $lead)
    {
        return view('sales.leads.form', $this->formData($request, $lead, null));
    }

    public function update(Request $request, Lead $lead)
    {
        $data = $this->validated($request, $lead);

        DB::transaction(function () use ($request, $lead, $data) {
            $lead->update($data);
            $lead->items()->delete();
            $this->syncItems($lead, $request->input('items'));
        });

        return redirect()->route('sales.leads.show', $lead)->with('success', "Lead \"{$lead->code}\" updated.");
    }

    public function destroy(Lead $lead)
    {
        if ($lead->quotations()->exists()) {
            return back()->with('error', "{$lead->code} has quotations, so it can't be deleted. Mark it Lost instead.");
        }

        $lead->delete();

        return redirect()->route('sales.leads.index')->with('success', "Lead \"{$lead->code}\" deleted.");
    }

    protected function syncItems(Lead $lead, array $rows): void
    {
        foreach ($rows as $row) {
            $lead->items()->create($this->productLineAttributes($row) + [
                'qty_basis' => $row['qty_basis'],
                'target_price' => $row['target_price'] ?? null,
            ]);
        }
    }

    protected function formData(Request $request, Lead $lead, ?string $nextCode): array
    {
        return [
            'lead' => $lead,
            'nextCode' => $nextCode,
            'rows' => $this->formRows($request, $lead->exists
                ? $lead->items->map(fn (LeadItem $i) => $i->toFormRow() + ['qty_basis' => $i->qty_basis, 'target_price' => $i->target_price !== null ? (float) $i->target_price : null])
                : []),
            'assignees' => $this->assignees(),
            'statuses' => array_unique([...Lead::MANUAL_STATUSES, $lead->status ?? 'new']),
        ] + $this->productLineOptions();
    }

    protected function validated(Request $request, ?Lead $lead = null): array
    {
        $request->validate([
            'enquiry_date' => ['required', 'date', 'before_or_equal:today'],
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][0-9A-Za-z][Zz][0-9A-Za-z]$/'],
            'industry' => ['required', Rule::in(array_keys(Lead::INDUSTRIES))],
            'source' => ['required', Rule::in(array_keys(Lead::SOURCES))],
            'source_detail' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(array_unique([...Lead::MANUAL_STATUSES, $lead?->status ?? 'new']))],
            'lost_reason' => ['nullable', 'required_if:status,lost', Rule::in(array_keys(Lead::LOST_REASONS))],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$this->productLineRules(),
            'items.*.qty_basis' => ['required', Rule::in(array_keys(LeadItem::QTY_BASES))],
            'items.*.target_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ], [
            'lost_reason.required_if' => 'Pick why this lead was lost.',
            'phone.regex' => 'Enter a valid mobile number.',
            'gstin.regex' => 'GSTIN must look like 27ABCDE1234F1Z5.',
            ...$this->productLineMessages(),
        ], [
            'enquiry_date' => 'enquiry date',
            'company_name' => 'company name',
            'phone' => 'mobile number',
            'gstin' => 'GSTIN',
            'items.*.quantity' => 'quantity',
        ]);

        $data = $request->only([
            'enquiry_date', 'company_name', 'contact_person', 'phone', 'email', 'city', 'state', 'address', 'industry',
            'source', 'source_detail', 'status', 'expected_close_date', 'assigned_to', 'notes',
        ]);

        return $data + [
            'gstin' => $request->filled('gstin') ? strtoupper($request->gstin) : null,
            'lost_reason' => $request->input('status') === 'lost' ? $request->input('lost_reason') : null,
        ];
    }
}
