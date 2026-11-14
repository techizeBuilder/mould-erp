@extends('layouts.app')

@section('title', 'Customer Master')
@section('breadcrumb', 'Order Management / Customer Master')

@use('App\Models\Customer')
@use('App\Models\Lead')
@use('App\Support\Money')
@use('App\Support\VoucherLines')

@section('content')
    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Customers @module('orders_customers', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $customers->total() }})</span>@endmodule</h2>
            @module('orders_customers', 'create')
                <a href="{{ route('orders.customers.create') }}" class="btn btn-primary"><x-icon name="user-plus" class="h-4 w-4" /> Add Customer</a>
            @endmodule
        </div>

        @module('orders_customers', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search code, company, contact, mobile, city or GSTIN">
                    </div>
                </div>
                <select name="industry" class="form-input w-auto" aria-label="Industry">
                    <option value="">All industries</option>
                    @foreach (Lead::INDUSTRIES as $value => $label)
                        <option value="{{ $value }}" @selected(request('industry') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-input w-auto" aria-label="Status">
                    @foreach (Customer::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status', 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'industry', 'status']))
                    <a href="{{ route('orders.customers.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Customer</th>
                        <th>GSTIN</th>
                        <th class="text-right">Credit limit (₹)</th>
                        <th class="text-right">Ledger balance (₹)</th>
                        <th class="text-center">Orders</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="font-mono text-xs font-semibold whitespace-nowrap text-navy-800">{{ $customer->code }}</td>
                            <td>
                                <a href="{{ route('orders.customers.show', $customer) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $customer->company_name }}</a>
                                <div class="text-xs text-slate-500">{{ collect([$customer->contact_person, $customer->phone, $customer->city])->filter()->join(' · ') }}</div>
                            </td>
                            <td class="font-mono text-xs whitespace-nowrap">{{ $customer->gstin ?: '—' }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ (float) $customer->credit_limit ? Money::format($customer->credit_limit, 0) : '—' }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ VoucherLines::drCr($customer->ledger?->closing()) }}</td>
                            <td class="text-center tabular-nums">{{ $customer->sales_orders_count }}</td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('orders.customers.show', $customer) }}" class="btn btn-secondary btn-sm" title="View"><x-icon name="eye" class="h-4 w-4" /></a>
                                    @module('orders_customers', 'edit')
                                        <a href="{{ route('orders.customers.edit', $customer) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    @endmodule
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-12 text-center text-sm text-slate-500">No customers found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $customers->links() }}</div>@endif
        @else
            <p class="px-5 py-10 text-center text-sm text-slate-500">You don't have permission to view these records.</p>
        @endmodule
    </div>
@endsection
