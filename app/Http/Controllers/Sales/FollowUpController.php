<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\HandlesProductLines;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FollowUpController extends Controller
{
    use HandlesProductLines;

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'pending');

        $followUps = FollowUp::with(['lead', 'customer', 'quotation.lead', 'quotation.customer', 'product:id,name', 'assignee:id,name'])
            ->when($tab === 'pending', fn ($q) => $q->pending())
            ->when($tab === 'overdue', fn ($q) => $q->overdue())
            ->when($tab === 'today', fn ($q) => $q->dueToday())
            ->when($tab === 'upcoming', fn ($q) => $q->upcoming())
            ->when($tab === 'done', fn ($q) => $q->where('status', 'done'))
            ->when($request->filled('purpose'), fn ($q) => $q->where('purpose', $request->purpose))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->product_id))
            ->when($request->input('assigned') === 'me', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when(in_array($tab, ['done', 'all'], true), fn ($q) => $q->latest('due_at'), fn ($q) => $q->orderBy('due_at'))
            ->paginate(25)
            ->withQueryString();

        return view('sales.followups.index', [
            'followUps' => $followUps,
            'tab' => $tab,
            'products' => Product::orderBy('code')->get(['id', 'code', 'name']),
            'counts' => [
                'overdue' => FollowUp::overdue()->count(),
                'today' => FollowUp::dueToday()->count(),
                'upcoming' => FollowUp::upcoming()->count(),
            ],
        ]);
    }

    public function show(FollowUp $followUp)
    {
        return view('sales.followups.show', [
            'followUp' => $followUp->load(['lead', 'customer', 'quotation.lead', 'quotation.customer', 'product', 'assignee:id,name', 'completer:id,name']),
        ]);
    }

    public function create(Request $request)
    {
        $kind = collect(['quotation', 'lead', 'customer'])->first(fn ($k) => $request->filled($k));

        $followUp = new FollowUp([
            'type' => 'call',
            'purpose' => $request->input('purpose', $kind === 'quotation' ? 'quotation' : ($kind === 'customer' ? 'reorder' : 'requirement')),
            'due_at' => now()->addDay()->setTime(10, 0),
            'assigned_to' => $request->user()->id,
            'product_id' => $request->input('product'),
        ]);

        return view('sales.followups.form', $this->formData($followUp) + ['kind' => $kind ?? 'lead', 'relatedId' => $kind ? (int) $request->input($kind) : null]);
    }

    public function store(Request $request)
    {
        $followUp = new FollowUp($this->validated($request));
        $followUp->created_by = $request->user()->id;
        $followUp->save();

        return redirect()->route('sales.followups.index')->with('success', "Follow-up with {$followUp->buyerName()} scheduled for {$followUp->due_at->format('d M, h:i A')}.");
    }

    public function edit(FollowUp $followUp)
    {
        [$kind, $id] = match (true) {
            (bool) $followUp->quotation_id => ['quotation', $followUp->quotation_id],
            (bool) $followUp->lead_id => ['lead', $followUp->lead_id],
            default => ['customer', $followUp->customer_id],
        };

        return view('sales.followups.form', $this->formData($followUp) + ['kind' => $kind, 'relatedId' => $id]);
    }

    public function update(Request $request, FollowUp $followUp)
    {
        $followUp->update($this->validated($request));

        return redirect()->route('sales.followups.index')->with('success', 'Follow-up updated.');
    }

    public function destroy(FollowUp $followUp)
    {
        $followUp->delete();

        return redirect()->route('sales.followups.index')->with('success', 'Follow-up deleted.');
    }

    /** Records the outcome and, optionally, books the next one. */
    public function complete(Request $request, FollowUp $followUp)
    {
        $data = $request->validate([
            'outcome' => ['required', 'string', 'max:1000'],
            'next_due_at' => ['nullable', 'date', 'after:now'],
            'next_purpose' => ['nullable', Rule::in(array_keys(FollowUp::PURPOSES))],
            'next_agenda' => ['nullable', 'string', 'max:1000'],
        ], [
            'outcome.required' => 'Note what happened on this follow-up.',
            'next_due_at.after' => 'The next follow-up must be in the future.',
        ]);

        if (! $followUp->isPending()) {
            return back()->with('error', 'This follow-up is already closed.');
        }

        abort_if($request->filled('next_due_at') && ! $request->user()->canModule('sales_followups', 'create'), 403);

        DB::transaction(function () use ($request, $followUp, $data) {
            $followUp->complete($data['outcome'], $request->user());

            if ($request->filled('next_due_at')) {
                $followUp->scheduleNext([
                    'due_at' => $data['next_due_at'],
                    'purpose' => $data['next_purpose'] ?? $followUp->purpose,
                    'agenda' => $data['next_agenda'] ?? null,
                ], $request->user());
            }
        });

        return back()->with('success', 'Follow-up completed'.($request->filled('next_due_at') ? ' and the next one scheduled.' : '.'));
    }

    public function cancel(FollowUp $followUp)
    {
        if (! $followUp->isPending()) {
            return back()->with('error', 'This follow-up is already closed.');
        }

        $followUp->forceFill(['status' => 'cancelled'])->save();

        return back()->with('success', 'Follow-up cancelled.');
    }

    protected function formData(FollowUp $followUp): array
    {
        return [
            'followUp' => $followUp,
            'leads' => Lead::open()->orWhere('id', $followUp->lead_id)->orderByDesc('id')->get(['id', 'code', 'company_name']),
            'quotations' => Quotation::with('lead:id,company_name', 'customer:id,company_name')->open()->orWhere('id', $followUp->quotation_id)->orderByDesc('id')->get(),
            'customers' => Customer::where('status', 'active')->orWhere('id', $followUp->customer_id)->orderBy('company_name')->get(['id', 'code', 'company_name']),
            'products' => Product::where('status', 'active')->orWhere('id', $followUp->product_id)->orderBy('code')->get(['id', 'code', 'name']),
            'assignees' => $this->assignees(),
        ];
    }

    protected function validated(Request $request): array
    {
        $table = ['lead' => 'leads', 'quotation' => 'quotations', 'customer' => 'customers'];

        $request->validate([
            'related_type' => ['required', Rule::in(array_keys($table))],
            'related_id' => ['required', 'integer', 'exists:'.($table[$request->input('related_type')] ?? 'leads').',id'],
            'type' => ['required', Rule::in(array_keys(FollowUp::TYPES))],
            'purpose' => ['required', Rule::in(array_keys(FollowUp::PURPOSES))],
            'due_at' => ['required', 'date'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'agenda' => ['nullable', 'string', 'max:1000'],
        ], [
            'related_id.required' => 'Pick who this follow-up is with.',
        ], [
            'due_at' => 'follow-up date',
        ]);

        $id = $request->integer('related_id');

        $related = match ($request->input('related_type')) {
            'lead' => ['lead_id' => $id, 'quotation_id' => null, 'customer_id' => Lead::whereKey($id)->value('customer_id')],
            'customer' => ['lead_id' => null, 'quotation_id' => null, 'customer_id' => $id],
            'quotation' => ['quotation_id' => $id] + Quotation::findOrFail($id)->only(['lead_id', 'customer_id']),
        };

        return $related + $request->only(['type', 'purpose', 'due_at', 'product_id', 'assigned_to', 'agenda']);
    }
}
