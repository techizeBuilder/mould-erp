@extends('layouts.app')

@section('title', $customer->company_name)
@section('breadcrumb', 'Order Management / Customer Master / '.$customer->code)

@use('App\Models\SalesOrder')
@use('App\Support\Money')
@use('App\Support\VoucherLines')

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <div class="font-mono text-xs font-semibold text-navy-700">{{ $customer->code }}</div>
                    <h2 class="card-title">{{ $customer->company_name }}
                        @if ($customer->status !== 'active')<span class="badge ml-1 {{ $customer->statusTone() }}">{{ $customer->statusLabel() }}</span>@endif
                    </h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    @module('orders_sales', 'create')
                        @if ($customer->status === 'active')
                            <a href="{{ route('orders.sales.create', ['customer' => $customer->id]) }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> New sales order</a>
                        @endif
                    @endmodule
                    @module('sales_retention', 'view')
                        <a href="{{ route('sales.retention.show', $customer) }}" class="btn btn-secondary"><x-icon name="refresh" class="h-4 w-4" /> Retention</a>
                    @endmodule
                    @module('orders_customers', 'edit')
                        <a href="{{ route('orders.customers.edit', $customer) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                    @endmodule
                    @module('orders_customers', 'delete')
                        <form method="POST" action="{{ route('orders.customers.destroy', $customer) }}" data-confirm="Delete customer &quot;{{ $customer->company_name }}&quot;?">
                            @csrf @method('DELETE')
                            <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                        </form>
                    @endmodule
                </div>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-detail label="Contact person">{{ $customer->contact_person }}</x-detail>
                <x-detail label="Mobile">{{ $customer->phone }}</x-detail>
                <x-detail label="Email">{{ $customer->email }}</x-detail>
                <x-detail label="City / state">{{ collect([$customer->city, $customer->state])->filter()->join(', ') }}</x-detail>
                <x-detail label="GSTIN">{{ $customer->gstin }}</x-detail>
                <x-detail label="PAN">{{ $customer->pan }}</x-detail>
                <x-detail label="Industry">{{ $customer->industryLabel() }}</x-detail>
                <x-detail label="Buyer type">{{ $customer->buyerTypeLabel() }}</x-detail>
                <x-detail label="Credit limit">{{ (float) $customer->credit_limit ? '₹'.Money::format($customer->credit_limit) : '' }}</x-detail>
                <x-detail label="Credit days">{{ $customer->credit_days }}</x-detail>
                <x-detail label="Payment terms">{{ $customer->paymentTermsLabel() }}</x-detail>
                <x-detail label="Ledger balance">₹{{ VoucherLines::drCr($balance) }}</x-detail>
                <x-detail label="Billing address" class="sm:col-span-2"><span class="whitespace-pre-line">{{ $customer->address }}</span></x-detail>
                <x-detail label="Shipping address" class="sm:col-span-2"><span class="whitespace-pre-line">{{ $customer->shipping_address ?: ($customer->address ? 'Same as billing' : '') }}</span></x-detail>
                @if ($customer->remarks)<x-detail label="Remarks" class="sm:col-span-2 lg:col-span-4"><span class="whitespace-pre-line">{{ $customer->remarks }}</span></x-detail>@endif
            </dl>
        </div>

        @module('orders_sales', 'view')
            <div class="card">
                <div class="card-header"><h2 class="card-title">Recent sales orders</h2></div>
                <div class="overflow-x-auto">
                    <table class="table-classic">
                        <thead><tr><th>Order</th><th>Date</th><th>Customer's PO</th><th class="text-right">Total (₹)</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td><a href="{{ route('orders.sales.show', $order) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $order->code }}</a></td>
                                <td class="whitespace-nowrap">{{ $order->order_date->format('d M Y') }}</td>
                                <td>{{ $order->po_number ?: '—' }}</td>
                                <td class="text-right tabular-nums">{{ Money::format($order->total) }}</td>
                                <td><span class="badge {{ $order->statusTone() }}">{{ $order->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-sm text-slate-500">No sales orders yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endmodule

        <a href="{{ route('orders.customers.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline">← Back to customers</a>
    </div>
@endsection
