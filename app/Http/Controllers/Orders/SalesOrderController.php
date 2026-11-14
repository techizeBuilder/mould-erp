<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Support\BuyerScore;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = SalesOrder::with(['customer:id,code,company_name', 'items', 'assignee:id,name'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('po_number', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->filled('product_id'), fn ($q) => $q->whereHas('items', fn ($i) => $i->where('product_id', $request->product_id)))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('order_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('order_date', '<=', $request->to))
            ->latest('order_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('orders.sales.index', [
            'orders' => $orders,
            'customers' => Customer::orderBy('company_name')->get(['id', 'code', 'company_name']),
            'products' => Product::orderBy('code')->get(['id', 'code', 'name']),
            'stats' => [
                'draft' => SalesOrder::whereIn('status', ['draft', 'rejected'])->count(),
                'pending' => SalesOrder::where('status', 'pending')->count(),
                'approved' => SalesOrder::where('status', 'approved')->count(),
                'approved_value' => (float) SalesOrder::where('status', 'approved')->sum('total'),
            ],
        ]);
    }

    public function show(SalesOrder $salesOrder)
    {
        return view('orders.sales.show', [
            'order' => $salesOrder->load(['customer', 'items.product', 'quotation', 'assignee:id,name', 'creator:id,name', 'decider:id,name', 'logs.user:id,name']),
        ]);
    }

    /** ?customer=ID preselects the customer; ?quotation=ID starts from a won quotation. */
    public function create(Request $request)
    {
        $quotation = $request->filled('quotation') ? Quotation::with('items')->findOrFail($request->quotation) : null;

        if ($quotation && ($problem = $this->quotationProblem($quotation))) {
            return redirect()->route('sales.quotations.show', $quotation)->with('error', $problem);
        }

        $customer = $quotation?->customer ?? ($request->filled('customer') ? Customer::findOrFail($request->customer) : null);

        $order = new SalesOrder($quotation ? $quotation->only(['customer_id', 'price_basis', 'payment_terms', 'packing', 'discount_percent', 'gst_percent', 'freight_amount', 'assigned_to']) + [
            'quotation_id' => $quotation->id,
            'order_date' => $quotation->won_on ?? today(),
            'po_number' => $quotation->po_number,
        ] : [
            'customer_id' => $customer?->id,
            'order_date' => today(),
            'price_basis' => 'ex_works',
            'payment_terms' => $customer?->payment_terms,
            'gst_percent' => 18,
            'assigned_to' => $customer?->assigned_to ?? $request->user()->id,
        ]);
        $order->shipping_address = $customer?->deliveryAddress();

        // A catalogue line carries over; a custom spec needs a product in the master first, as it has no serial yet.
        $rows = $quotation?->items->map(fn ($i) => [
            'product_id' => $i->product_id,
            'custom_note' => $i->product_id ? null : $i->description,
            'quantity' => $i->quantity,
            'unit' => $i->unit,
            'rate' => (float) $i->rate,
            'mould_charge' => (float) $i->mould_charge ?: null,
        ])->all() ?? [[]];

        return view('orders.sales.form', $this->formData($request, $order, $rows, SalesOrder::nextCode()));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $order = DB::transaction(function () use ($request, $data) {
            $order = new SalesOrder($data);
            $order->status = 'draft';
            $order->created_by = $request->user()->id;
            $order->save();
            $this->syncItems($order, $request->input('items'));
            $order->logs()->create(['to_status' => 'draft', 'user_id' => $request->user()->id]);

            return $order;
        });

        return redirect()->route('orders.sales.show', $order)->with('success', "Sales order {$order->code} saved as a draft. Submit it when it's ready for approval.");
    }

    public function edit(Request $request, SalesOrder $salesOrder)
    {
        if (! $salesOrder->isEditable()) {
            return redirect()->route('orders.sales.show', $salesOrder)->with('error', 'Only a draft or rejected order can be changed.');
        }

        $rows = $salesOrder->items->map(fn (SalesOrderItem $i) => [
            'product_id' => $i->product_id,
            'quantity' => $i->quantity,
            'unit' => $i->unit,
            'rate' => (float) $i->rate,
            'mould_charge' => (float) $i->mould_charge ?: null,
        ])->all();

        return view('orders.sales.form', $this->formData($request, $salesOrder, $rows, null));
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        if (! $salesOrder->isEditable()) {
            return redirect()->route('orders.sales.show', $salesOrder)->with('error', 'Only a draft or rejected order can be changed.');
        }

        $data = $this->validated($request, $salesOrder);

        DB::transaction(function () use ($request, $salesOrder, $data) {
            $salesOrder->update($data);
            $salesOrder->items()->delete();
            $this->syncItems($salesOrder, $request->input('items'));
        });

        return redirect()->route('orders.sales.show', $salesOrder)->with('success', "Sales order {$salesOrder->code} updated.");
    }

    public function destroy(SalesOrder $salesOrder)
    {
        if (! $salesOrder->canDelete()) {
            return back()->with('error', 'Only a draft can be deleted. Cancel this order instead.');
        }

        $salesOrder->delete();

        return redirect()->route('orders.sales.index')->with('success', "Sales order {$salesOrder->code} deleted.");
    }

    public function submit(Request $request, SalesOrder $salesOrder)
    {
        try {
            $salesOrder->submit($request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.sales.show', $salesOrder)->with('success', "Sales order {$salesOrder->code} sent for approval.");
    }

    /** An approved order was decided by an approver, so only an approver can undo it. */
    public function cancel(Request $request, SalesOrder $salesOrder)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Say why this order is being cancelled.']);

        abort_if($salesOrder->status === 'approved' && ! $request->user()->canModule('orders_approval', 'edit'), 403);

        try {
            $salesOrder->cancel($request->user(), $data['reason']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.sales.show', $salesOrder)->with('success', "Sales order {$salesOrder->code} cancelled.");
    }

    protected function syncItems(SalesOrder $order, array $rows): void
    {
        foreach ($rows as $row) {
            $order->items()->create([
                'product_id' => $row['product_id'],
                'quantity' => $row['quantity'],
                'unit' => $row['unit'],
                'rate' => $row['rate'],
                'mould_charge' => $row['mould_charge'] ?? 0,
            ]);
        }
        $order->recalculate();
    }

    protected function formData(Request $request, SalesOrder $order, array $rows, ?string $nextCode): array
    {
        $usedProducts = $order->exists ? $order->items()->pluck('product_id')->all() : [];

        return [
            'order' => $order,
            'nextCode' => $nextCode,
            'rows' => $request->old('items', $rows ?: [[]]),
            'customers' => Customer::where(fn ($q) => $q->where('status', 'active')->orWhere('id', $order->customer_id))->orderBy('company_name')->get(),
            'products' => Product::where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $usedProducts))->orderBy('code')->get(['id', 'code', 'name', 'unit']),
            'assignees' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** Why a quotation can't become an order, if it can't. */
    protected function quotationProblem(Quotation $quotation, ?SalesOrder $except = null): ?string
    {
        if ($quotation->status !== 'won') {
            return 'Only a won quotation can become a sales order.';
        }

        $existing = $quotation->salesOrders()->live()->when($except, fn ($q) => $q->whereKeyNot($except->id))->value('code');

        return $existing ? "This quotation already has sales order $existing." : null;
    }

    protected function validated(Request $request, ?SalesOrder $order = null): array
    {
        $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'order_date' => ['required', 'date'],
            'required_by' => ['nullable', 'date', 'after_or_equal:order_date'],
            'po_number' => ['nullable', 'string', 'max:60'],
            'po_date' => ['nullable', 'date'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'price_basis' => ['required', Rule::in(array_keys(Quotation::PRICE_BASES))],
            'payment_terms' => ['nullable', Rule::in(array_keys(BuyerScore::options('payment_terms')))],
            'packing' => ['nullable', 'string', 'max:150'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'gst_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'freight_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'items.*.unit' => ['required', Rule::in(array_keys(Product::UNITS))],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'items.*.mould_charge' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ], [
            'items.required' => 'Add at least one product.',
            'items.*.product_id.required' => 'Pick the product from Product Master.',
            'required_by.after_or_equal' => 'The required-by date must not be before the order date.',
        ], [
            'customer_id' => 'customer',
            'items.*.quantity' => 'quantity',
            'items.*.rate' => 'rate',
        ]);

        $customerId = $request->integer('customer_id');
        $customer = Customer::find($customerId);

        if ($customer->status !== 'active' && $order?->customer_id !== $customerId) {
            throw ValidationException::withMessages(['customer_id' => "{$customer->company_name} is inactive."]);
        }

        $productIds = collect($request->input('items'))->pluck('product_id')->map(fn ($id) => (int) $id);
        if ($productIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'The same product is on the order twice. Combine the quantities on one line.']);
        }

        $used = $order ? $order->items()->pluck('product_id')->map(fn ($id) => (int) $id) : collect();
        $inactive = Product::whereIn('id', $productIds->diff($used))->where('status', '!=', 'active')->pluck('code');
        if ($inactive->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => $inactive->join(', ').' is inactive in Product Master.']);
        }

        // One PO shouldn't be entered as two orders.
        if ($request->filled('po_number')) {
            $sameOrder = SalesOrder::live()->where('customer_id', $customerId)->where('po_number', $request->po_number)
                ->when($order, fn ($q) => $q->whereKeyNot($order->id))->value('code');
            if ($sameOrder) {
                throw ValidationException::withMessages(['po_number' => "This customer's PO is already on sales order $sameOrder."]);
            }
        }

        $quotationId = $order ? $order->quotation_id : ($request->input('quotation_id') ?: null);
        if ($quotationId && ! $order) {
            $quotation = Quotation::find($quotationId);
            $problem = $this->quotationProblem($quotation);
            if (! $problem && (int) $quotation->customer_id !== $customerId) {
                $problem = 'This quotation belongs to a different customer.';
            }
            if ($problem) {
                throw ValidationException::withMessages(['customer_id' => $problem]);
            }
        }

        return $request->only(['order_date', 'required_by', 'po_number', 'po_date', 'price_basis', 'payment_terms', 'packing', 'gst_percent', 'remarks', 'assigned_to']) + [
            'customer_id' => $customerId,
            'quotation_id' => $quotationId,
            'shipping_address' => $request->input('shipping_address') ?: $customer->deliveryAddress(),
            'discount_percent' => $request->input('discount_percent') ?: 0,
            'freight_amount' => $request->input('freight_amount') ?: 0,
        ];
    }
}
