<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\HandlesProductLines;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadItem;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Support\BuyerScore;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    use HandlesProductLines;

    public function index(Request $request)
    {
        $status = $request->input('status');

        $quotations = Quotation::with(['lead', 'customer', 'items', 'assignee:id,name', 'followUps' => fn ($q) => $q->pending()->orderBy('due_at')])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)
                    ->orWhereHas('lead', fn ($l) => $l->where('company_name', 'like', $term))
                    ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', $term)));
            })
            ->when($status === 'expired', fn ($q) => $q->expired())
            ->when($status === 'revised', fn ($q) => $q->where('status', 'revised'))
            ->when($status !== 'revised', fn ($q) => $q->current())
            ->when($status && ! in_array($status, ['expired', 'revised'], true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('sample'), fn ($q) => $q->where('sample_status', $request->sample))
            ->when($request->filled('product_id'), fn ($q) => $q->whereHas('items', fn ($i) => $i->where('product_id', $request->product_id)))
            ->when($request->filled('product_type'), fn ($q) => $q->whereHas('items', fn ($i) => $i->where('product_type', $request->product_type)))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $won = Quotation::where('status', 'won')->count();
        $lost = Quotation::where('status', 'lost')->count();

        return view('sales.quotations.index', [
            'quotations' => $quotations,
            'products' => Product::orderBy('code')->get(['id', 'code', 'name']),
            'stats' => [
                'open_value' => (float) Quotation::open()->sum('total'),
                'open_count' => Quotation::open()->count(),
                'won_value' => (float) Quotation::where('status', 'won')->sum('total'),
                'win_rate' => $won + $lost ? (int) round($won * 100 / ($won + $lost)) : null,
                'expiring' => Quotation::expiringSoon()->count(),
                'expired' => Quotation::expired()->count(),
            ],
        ]);
    }

    public function show(Quotation $quotation)
    {
        return view('sales.quotations.show', [
            'quotation' => $quotation->load(['items.product', 'items.mould', 'lead.qualification', 'customer', 'assignee:id,name', 'creator:id,name', 'statusLogs.user:id,name']),
            'revisions' => $quotation->revisions()->get(),
            'followUps' => $quotation->followUps()->with('assignee:id,name')->latest('due_at')->get(),
        ]);
    }

    public function print(Quotation $quotation)
    {
        return view('sales.quotations.print', ['quotation' => $quotation->load(['items', 'lead', 'customer'])]);
    }

    public function create(Request $request)
    {
        $lead = $request->filled('lead') ? Lead::with('items', 'qualification')->findOrFail($request->lead) : null;
        $customer = $request->filled('customer') ? Customer::findOrFail($request->customer) : null;

        if ($lead && ! $lead->canBeQuoted()) {
            return redirect()->route('sales.leads.show', $lead)->with('error', 'This lead is disqualified, so it cannot be quoted.');
        }

        $quotation = new Quotation([
            'lead_id' => $lead?->id,
            'customer_id' => $customer?->id,
            'quote_date' => today(),
            'valid_until' => today()->addDays(Quotation::DEFAULT_VALIDITY_DAYS),
            'price_basis' => 'ex_works',
            'payment_terms' => $lead?->qualification?->payment_terms ?? $customer?->payment_terms,
            'sample_status' => $lead?->qualification?->sample_required ? 'pending' : 'not_required',
            'gst_percent' => 18,
            'assigned_to' => $lead?->assigned_to ?? $customer?->assigned_to ?? $request->user()->id,
        ]);

        // A quotation for a lead starts from what the lead asked for.
        $rows = $lead?->items->map(fn (LeadItem $i) => $i->toFormRow() + ['unit' => 'pcs', 'rate' => $i->target_price !== null ? (float) $i->target_price : null]) ?? [];

        return view('sales.quotations.form', $this->formData($request, $quotation, $rows, Quotation::nextCode()));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $quotation = DB::transaction(function () use ($request, $data) {
            $quotation = new Quotation($data);
            $quotation->created_by = $request->user()->id;
            $quotation->status = 'draft';
            $quotation->save();
            $this->syncItems($quotation, $request->input('items'));
            $quotation->logStatus(null, 'draft', null, $request->user());
            $quotation->lead?->syncStatusFromQuotations();

            return $quotation;
        });

        return redirect()->route('sales.quotations.show', $quotation)->with('success', "Quotation \"{$quotation->number()}\" created as a draft.");
    }

    public function edit(Request $request, Quotation $quotation)
    {
        if (! $quotation->isDraft()) {
            return redirect()->route('sales.quotations.show', $quotation)->with('error', 'Only a draft can be edited. Create a revision to change this quotation.');
        }

        return view('sales.quotations.form', $this->formData($request, $quotation, $quotation->items, null));
    }

    public function update(Request $request, Quotation $quotation)
    {
        if (! $quotation->isDraft()) {
            return redirect()->route('sales.quotations.show', $quotation)->with('error', 'Only a draft can be edited. Create a revision to change this quotation.');
        }

        $data = $this->validated($request);
        $oldLead = $quotation->lead;

        DB::transaction(function () use ($request, $quotation, $data, $oldLead) {
            $quotation->update($data);
            $quotation->items()->delete();
            $this->syncItems($quotation, $request->input('items'));
            $quotation->refresh();
            $quotation->lead?->syncStatusFromQuotations();
            if ($oldLead && $oldLead->id !== $quotation->lead_id) {
                $oldLead->syncStatusFromQuotations();
            }
        });

        return redirect()->route('sales.quotations.show', $quotation)->with('success', "Quotation \"{$quotation->number()}\" updated.");
    }

    public function destroy(Quotation $quotation)
    {
        if (! $quotation->canDelete()) {
            return back()->with('error', 'Only a first-version draft can be deleted. Mark this quotation Lost instead.');
        }

        DB::transaction(function () use ($quotation) {
            $lead = $quotation->lead;
            $quotation->delete();
            if ($lead && ! $lead->quotations()->exists()) {
                $lead->update(['status' => $lead->qualification?->decision === 'qualified' ? 'qualified' : 'contacted']);
            } else {
                $lead?->syncStatusFromQuotations();
            }
        });

        return redirect()->route('sales.quotations.index')->with('success', "Quotation \"{$quotation->number()}\" deleted.");
    }

    /** Sent / negotiation / won / lost. Winning records the buyer's PO; losing records why. */
    public function status(Request $request, Quotation $quotation)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Quotation::STATUSES))],
            'note' => ['nullable', 'string', 'max:500'],
            'lost_reason' => ['nullable', 'required_if:status,lost', Rule::in(array_keys(Lead::LOST_REASONS))],
            'competitor' => ['nullable', 'string', 'max:150'],
            'po_number' => ['nullable', 'string', 'max:60'],
            'won_on' => ['nullable', 'required_if:status,won', 'date', 'before_or_equal:today'],
        ], [
            'lost_reason.required_if' => 'Pick why this quotation was lost.',
            'won_on.required_if' => 'Enter the order date.',
        ]);

        try {
            $quotation->changeStatus($data['status'], $data, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = "Quotation \"{$quotation->number()}\" marked ".$quotation->statusLabel().'.';
        if ($data['status'] === 'won') {
            $message .= " Added to {$quotation->customer->company_name}'s order history.";
        }

        return redirect()->route('sales.quotations.show', $quotation)->with('success', $message);
    }

    public function sample(Request $request, Quotation $quotation)
    {
        $data = $request->validate([
            'sample_status' => ['required', Rule::in(array_keys(Quotation::SAMPLE_STATUSES))],
            'sample_sent_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $quotation->update([
            'sample_status' => $data['sample_status'],
            'sample_sent_on' => $data['sample_sent_on'] ?? ($data['sample_status'] === 'sent' ? today() : $quotation->sample_sent_on),
        ]);
        $quotation->logStatus($quotation->status, $quotation->status, 'Sample: '.$quotation->sampleLabel(), $request->user());

        return back()->with('success', 'Sample status updated.');
    }

    public function revise(Request $request, Quotation $quotation)
    {
        try {
            $next = $quotation->revise($request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.quotations.edit', $next)->with('success', "Revision {$next->number()} started. Change what's needed and save.");
    }

    protected function syncItems(Quotation $quotation, array $rows): void
    {
        foreach ($rows as $row) {
            $quotation->items()->create($this->productLineAttributes($row) + [
                'unit' => $row['unit'],
                'rate' => $row['rate'],
                'moq' => $row['moq'] ?? null,
                'mould_charge' => $row['mould_charge'] ?? 0,
            ]);
        }
        $quotation->recalculate();
    }

    protected function formData(Request $request, Quotation $quotation, iterable $rows, ?string $nextCode): array
    {
        return [
            'quotation' => $quotation,
            'nextCode' => $nextCode,
            'rows' => $this->formRows($request, $rows),
            // Disqualified, lost and won leads can't get a new quotation, but one already attached stays listed.
            'leads' => Lead::whereNotIn('status', Lead::CLOSED_STATUSES)->orWhere('id', $quotation->lead_id)->orderByDesc('id')->get(['id', 'code', 'company_name']),
            'customers' => Customer::where('status', 'active')->orWhere('id', $quotation->customer_id)->orderBy('company_name')->get(['id', 'code', 'company_name']),
            'assignees' => $this->assignees(),
        ] + $this->productLineOptions();
    }

    protected function validated(Request $request): array
    {
        $request->validate([
            'buyer_kind' => ['required', 'in:lead,customer'],
            'lead_id' => ['nullable', 'required_if:buyer_kind,lead', 'integer', 'exists:leads,id'],
            'customer_id' => ['nullable', 'required_if:buyer_kind,customer', 'integer', 'exists:customers,id'],
            'quote_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:quote_date'],
            'price_basis' => ['required', Rule::in(array_keys(Quotation::PRICE_BASES))],
            'payment_terms' => ['nullable', Rule::in(array_keys(BuyerScore::options('payment_terms')))],
            'delivery_days' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'mould_lead_days' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'packing' => ['nullable', 'string', 'max:150'],
            'sample_status' => ['required', Rule::in(array_keys(Quotation::SAMPLE_STATUSES))],
            'sample_sent_on' => ['nullable', 'date'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'gst_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'freight_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            ...$this->productLineRules(),
            'items.*.unit' => ['required', Rule::in(array_keys(Product::UNITS))],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'items.*.moq' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'items.*.mould_charge' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ], [
            'lead_id.required_if' => 'Pick the lead this quotation is for.',
            'customer_id.required_if' => 'Pick the customer this quotation is for.',
            'valid_until.after_or_equal' => 'Valid-until must not be before the quotation date.',
            ...$this->productLineMessages(),
        ], [
            'items.*.rate' => 'rate',
            'items.*.quantity' => 'quantity',
        ]);

        $isLead = $request->input('buyer_kind') === 'lead';
        $lead = $isLead ? Lead::find($request->input('lead_id')) : null;

        if ($lead && ! $lead->canBeQuoted()) {
            throw ValidationException::withMessages(['lead_id' => 'This lead is disqualified, so it cannot be quoted.']);
        }

        return $request->only([
            'quote_date', 'valid_until', 'price_basis', 'payment_terms', 'delivery_days', 'mould_lead_days', 'packing',
            'sample_status', 'sample_sent_on', 'gst_percent', 'terms', 'remarks', 'assigned_to',
        ]) + [
            'lead_id' => $lead?->id,
            'customer_id' => $isLead ? $lead->customer_id : $request->input('customer_id'),
            'discount_percent' => $request->input('discount_percent') ?: 0,
            'freight_amount' => $request->input('freight_amount') ?: 0,
        ];
    }
}
