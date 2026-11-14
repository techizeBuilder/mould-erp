<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\HandlesCustomerDetails;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;

/** The full customer record. Customer Retention works with the same customers. */
class CustomerController extends Controller
{
    use HandlesCustomerDetails;

    public function index(Request $request)
    {
        $customers = Customer::query()
            ->with(['ledger' => fn ($q) => $q->withTotals()])
            ->withCount(['salesOrders' => fn ($q) => $q->live()])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('company_name', 'like', $term)->orWhere('contact_person', 'like', $term)
                    ->orWhere('phone', 'like', $term)->orWhere('city', 'like', $term)->orWhere('gstin', 'like', $term));
            })
            ->when($request->filled('industry'), fn ($q) => $q->where('industry', $request->industry))
            ->where('status', $request->input('status', 'active'))
            ->orderBy('company_name')
            ->paginate(20)
            ->withQueryString();

        return view('orders.customers.index', ['customers' => $customers]);
    }

    public function show(Customer $customer)
    {
        return view('orders.customers.show', [
            'customer' => $customer->load('assignee:id,name', 'creator:id,name'),
            'balance' => $customer->ledgerBalance(),
            'orders' => $customer->salesOrders()->latest('order_date')->latest('id')->take(10)->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('orders.customers.form', $this->formData(new Customer(['status' => 'active', 'assigned_to' => $request->user()->id]), Customer::nextCode()));
    }

    public function store(Request $request)
    {
        $customer = new Customer($this->validated($request));
        $customer->created_by = $request->user()->id;
        $customer->save();

        return redirect()->route('orders.customers.show', $customer)->with('success', "Customer \"{$customer->code} – {$customer->company_name}\" added.");
    }

    public function edit(Customer $customer)
    {
        return view('orders.customers.form', $this->formData($customer, null));
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));

        return redirect()->route('orders.customers.show', $customer)->with('success', "Customer \"{$customer->code}\" updated.");
    }

    public function destroy(Customer $customer)
    {
        if (! $customer->canBeDeleted()) {
            return back()->with('error', "{$customer->company_name} has orders, quotations or accounting entries, so the record can't be deleted. Set the status to Inactive instead.");
        }

        $customer->delete();

        return redirect()->route('orders.customers.index')->with('success', "Customer \"{$customer->code}\" deleted.");
    }

    protected function formData(Customer $customer, ?string $nextCode): array
    {
        return [
            'customer' => $customer,
            'nextCode' => $nextCode,
            'assignees' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function validated(Request $request): array
    {
        $request->validate($this->customerRules() + [
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'pan' => ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ], $this->customerMessages() + ['pan.regex' => 'PAN must look like ABCDE1234F.'], $this->customerAttributes() + [
            'pan' => 'PAN',
            'credit_limit' => 'credit limit',
            'credit_days' => 'credit days',
        ]);

        return $this->customerData($request) + [
            'shipping_address' => $request->input('shipping_address'),
            'pan' => $request->filled('pan') ? strtoupper($request->pan) : null,
            'credit_limit' => $request->input('credit_limit'),
            'credit_days' => $request->input('credit_days'),
        ];
    }
}
