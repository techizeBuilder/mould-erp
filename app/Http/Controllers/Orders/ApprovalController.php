<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApprovalController extends Controller
{
    /** The queue: pending orders first in line, oldest first; decided orders newest first. */
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'pending');
        abort_unless(in_array($tab, ['pending', 'approved', 'rejected'], true), 404);

        $orders = SalesOrder::with(['customer', 'items.product', 'creator:id,name', 'decider:id,name'])
            ->where('status', $tab)
            ->when($tab === 'pending', fn ($q) => $q->orderBy('order_date')->orderBy('id'), fn ($q) => $q->latest('decided_at'))
            ->paginate(20)
            ->withQueryString();

        return view('orders.approval.index', [
            'orders' => $orders,
            'tab' => $tab,
            'counts' => SalesOrder::whereIn('status', ['pending', 'approved', 'rejected'])->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'items.product', 'quotation', 'creator:id,name', 'decider:id,name', 'logs.user:id,name']);

        return view('orders.approval.show', [
            'order' => $salesOrder,
            'checks' => $salesOrder->isPending() ? $salesOrder->approvalChecks() : [],
            'lastRates' => $salesOrder->customer->lastRates($salesOrder->items->pluck('product_id')->filter()->all(), $salesOrder->id),
        ]);
    }

    public function approve(Request $request, SalesOrder $salesOrder)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->guardDecider($request, $salesOrder);

        // Going over the credit limit is allowed, but someone has to say why.
        if ($salesOrder->isPending() && $salesOrder->load('customer')->exceedsCredit() && blank($data['note'] ?? null)) {
            throw ValidationException::withMessages(['note' => 'This order is over the credit limit. Note why you are approving it anyway.']);
        }

        try {
            $salesOrder->load('items')->approve($request->user(), $data['note'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.approval.index')->with('success', "Sales order {$salesOrder->code} approved.");
    }

    public function reject(Request $request, SalesOrder $salesOrder)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Say why this order is being rejected.']);
        $this->guardDecider($request, $salesOrder);

        try {
            $salesOrder->reject($request->user(), $data['reason']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.approval.index')->with('success', "Sales order {$salesOrder->code} rejected and sent back.");
    }

    protected function guardDecider(Request $request, SalesOrder $order): void
    {
        abort_unless($order->canBeDecidedBy($request->user()), 403, 'You entered this order, so someone else has to approve it.');
    }
}
