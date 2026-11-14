<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\HandlesCustomerDetails;
use App\Http\Controllers\Sales\Concerns\HandlesProductLines;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Product;
use App\Support\QuotationTotals;
use App\Support\RetentionHealth;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RetentionController extends Controller
{
    use HandlesCustomerDetails, HandlesProductLines;

    public function index(Request $request)
    {
        // Health is worked out from each customer's order dates, so filter it after loading.
        $all = Customer::with(['orders', 'assignee:id,name', 'followUps' => fn ($q) => $q->pending()->orderBy('due_at')])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('company_name', 'like', $term)
                    ->orWhere('contact_person', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('city', 'like', $term));
            })
            ->when($request->filled('industry'), fn ($q) => $q->where('industry', $request->industry))
            ->when($request->filled('product_id'), fn ($q) => $q->whereHas('orders', fn ($o) => $o->where('product_id', $request->product_id)))
            ->where('status', $request->input('status', 'active'))
            ->orderBy('company_name')
            ->get();

        $byHealth = $all->countBy(fn (Customer $c) => $c->health());
        $withOrders = $all->filter(fn (Customer $c) => $c->orders->isNotEmpty());
        $repeat = $withOrders->filter(fn (Customer $c) => $c->orderCount() >= 2)->count();

        // Most urgent first: those furthest past their expected reorder date.
        $rows = $all->when($request->filled('health'), fn (Collection $c) => $c->filter(fn (Customer $x) => $x->health() === $request->health))
            ->sortBy(fn (Customer $c) => $c->retention()['next_expected']?->timestamp ?? PHP_INT_MAX)
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $customers = new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('sales.retention.index', [
            'customers' => $customers,
            'products' => Product::orderBy('code')->get(['id', 'code', 'name']),
            'byHealth' => $byHealth,
            'stats' => [
                'customers' => $all->count(),
                'repeat_rate' => $withOrders->count() ? (int) round($repeat * 100 / $withOrders->count()) : null,
                'value' => $all->sum(fn (Customer $c) => $c->lifetimeValue()),
            ],
        ]);
    }

    public function show(Customer $customer)
    {
        $customer->load(['orders.product:id,name', 'orders.quotation', 'assignee:id,name', 'creator:id,name']);

        return view('sales.retention.show', [
            'customer' => $customer,
            'history' => $customer->productHistory(),
            'orders' => $customer->orders->sortByDesc(fn (CustomerOrder $o) => [$o->order_date->timestamp, $o->id])->values(),
            'quotations' => $customer->quotations()->current()->latest('id')->take(10)->get(),
            'leads' => $customer->leads()->latest('id')->take(10)->get(),
            'followUps' => $customer->followUps()->with('assignee:id,name', 'product:id,name')->latest('due_at')->take(20)->get(),
            'products' => Product::where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        return view('sales.retention.form', [
            'customer' => new Customer(['status' => 'active', 'assigned_to' => $request->user()->id]),
            'nextCode' => Customer::nextCode(),
            'assignees' => $this->assignees(),
        ]);
    }

    public function store(Request $request)
    {
        $customer = new Customer($this->validated($request));
        $customer->created_by = $request->user()->id;
        $customer->save();

        return redirect()->route('sales.retention.show', $customer)->with('success', "Customer \"{$customer->code} – {$customer->company_name}\" added.");
    }

    public function edit(Customer $customer)
    {
        return view('sales.retention.form', ['customer' => $customer, 'nextCode' => null, 'assignees' => $this->assignees()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));

        return redirect()->route('sales.retention.show', $customer)->with('success', "Customer \"{$customer->code}\" updated.");
    }

    public function destroy(Customer $customer)
    {
        if (! $customer->canBeDeleted()) {
            return back()->with('error', "{$customer->company_name} has orders, quotations or accounting entries, so the record can't be deleted. Set the status to Inactive instead.");
        }

        $customer->delete();

        return redirect()->route('sales.retention.index')->with('success', "Customer \"{$customer->code}\" deleted.");
    }

    /** Records an order placed outside a quotation, e.g. a straight repeat at the same price. */
    public function storeOrder(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'description' => ['nullable', 'required_without:product_id', 'string', 'max:255'],
            'order_date' => ['required', 'date', 'before_or_equal:today'],
            'po_number' => ['nullable', 'string', 'max:60'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'unit' => ['required', Rule::in(array_keys(Product::UNITS))],
            'rate' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'description.required_without' => 'Pick a product, or describe what was ordered.',
        ]);

        if ($data['product_id'] ?? null) {
            $data['description'] = Product::whereKey($data['product_id'])->value('name');
        }

        $order = new CustomerOrder($data + ['amount' => QuotationTotals::lineAmount($data['quantity'], $data['rate'])]);
        $order->customer_id = $customer->id;
        $order->created_by = $request->user()->id;
        $order->save();

        return back()->with('success', 'Order recorded.');
    }

    public function destroyOrder(Customer $customer, CustomerOrder $order)
    {
        abort_unless($order->customer_id === $customer->id, 404);
        $order->delete();

        return back()->with('success', 'Order removed.');
    }

    /** Books a reorder call for every customer due or at risk who doesn't already have one pending. */
    public function reminders(Request $request)
    {
        $created = DB::transaction(function () use ($request) {
            $count = 0;

            Customer::with(['orders', 'followUps' => fn ($q) => $q->pending()->where('purpose', 'reorder')])
                ->where('status', 'active')
                ->get()
                ->filter(fn (Customer $c) => in_array($c->health(), ['due', 'at_risk'], true) && $c->followUps->isEmpty())
                ->each(function (Customer $customer) use ($request, &$count) {
                    $last = $customer->orders->sortByDesc(fn (CustomerOrder $o) => $o->order_date->timestamp)->first();
                    $retention = $customer->retention();

                    $followUp = new FollowUp([
                        'customer_id' => $customer->id,
                        'product_id' => $last->product_id,
                        'type' => 'call',
                        'purpose' => 'reorder',
                        'due_at' => today()->setTime(10, 0),
                        'assigned_to' => $customer->assigned_to ?? $request->user()->id,
                        'agenda' => "Reorder of {$last->description}. Last ordered {$retention['days_since']} days ago (usual cycle {$retention['cycle']} days).",
                    ]);
                    $followUp->created_by = $request->user()->id;
                    $followUp->save();
                    $count++;
                });

            return $count;
        });

        return redirect()->route('sales.followups.index', ['tab' => 'today', 'purpose' => 'reorder'])
            ->with('success', $created ? "$created reorder follow-up(s) scheduled for today." : 'Nobody new needs a reorder reminder.');
    }

    protected function validated(Request $request): array
    {
        $request->validate($this->customerRules(), $this->customerMessages(), $this->customerAttributes());

        return $this->customerData($request);
    }
}
