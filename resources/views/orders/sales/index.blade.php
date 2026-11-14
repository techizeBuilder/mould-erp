@extends('layouts.app')

@section('title', 'Sales Orders')
@section('breadcrumb', 'Order Management / Sales Orders')

@use('App\Models\SalesOrder')
@use('App\Support\Money')

@section('content')
    @module('orders_sales', 'view')
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @include('sales._stat', ['label' => 'Draft or sent back', 'value' => $stats['draft'], 'href' => route('orders.sales.index', ['status' => 'draft'])])
            @include('sales._stat', ['label' => 'Pending approval', 'value' => $stats['pending'], 'tone' => 'text-amber-700', 'href' => route('orders.sales.index', ['status' => 'pending'])])
            @include('sales._stat', ['label' => 'Approved', 'value' => $stats['approved'], 'tone' => 'text-emerald-700', 'href' => route('orders.sales.index', ['status' => 'approved'])])
            @include('sales._stat', ['label' => 'Approved value', 'value' => '₹'.Money::format($stats['approved_value'], 0)])
        </div>
    @endmodule

    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Sales Orders @module('orders_sales', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $orders->total() }})</span>@endmodule</h2>
            @module('orders_sales', 'create')
                <a href="{{ route('orders.sales.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> New Sales Order</a>
            @endmodule
        </div>

        @module('orders_sales', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[180px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search order no., PO no. or customer">
                    </div>
                </div>
                <select name="status" class="form-input w-auto" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach (SalesOrder::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="customer_id" class="form-input w-auto max-w-56" aria-label="Customer">
                    <option value="">All customers</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" @selected((int) request('customer_id') === $c->id)>{{ $c->company_name }}</option>
                    @endforeach
                </select>
                <select name="product_id" class="form-input w-auto max-w-56" aria-label="Product">
                    <option value="">All products</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((int) request('product_id') === $p->id)>{{ $p->code }} – {{ $p->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ request('from') }}" class="form-input w-auto" aria-label="From date">
                <input type="date" name="to" value="{{ request('to') }}" class="form-input w-auto" aria-label="To date">
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'status', 'customer_id', 'product_id', 'from', 'to']))
                    <a href="{{ route('orders.sales.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Products</th>
                        <th>Required by</th>
                        <th class="text-right">Total (₹)</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('orders.sales.show', $order) }}" class="font-mono text-xs font-semibold text-navy-800 hover:underline">{{ $order->code }}</a>
                                <div class="text-xs text-slate-500">{{ $order->order_date->format('d M Y') }}</div>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-800">{{ $order->customer->company_name }}</div>
                                @if ($order->po_number)<div class="text-xs text-slate-500">PO: {{ $order->po_number }}</div>@endif
                            </td>
                            <td class="max-w-xs">
                                <div class="truncate" title="{{ $order->items->pluck('description')->join(', ') }}">{{ $order->items->first()?->description }}</div>
                                @if ($order->items->count() > 1)<div class="text-xs text-slate-500">+{{ $order->items->count() - 1 }} more</div>@endif
                            </td>
                            <td class="whitespace-nowrap">{{ $order->required_by?->format('d M Y') ?? '—' }}</td>
                            <td class="text-right font-semibold tabular-nums whitespace-nowrap">{{ Money::format($order->total) }}</td>
                            <td><span class="badge whitespace-nowrap {{ $order->statusTone() }}">{{ $order->statusLabel() }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('orders.sales.show', $order) }}" class="btn btn-secondary btn-sm" title="View"><x-icon name="eye" class="h-4 w-4" /></a>
                                    @if ($order->isEditable())
                                        @module('orders_sales', 'edit')
                                            <a href="{{ route('orders.sales.edit', $order) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                        @endmodule
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-12 text-center text-sm text-slate-500">No sales orders found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $orders->links() }}</div>@endif
        @else
            <p class="px-5 py-10 text-center text-sm text-slate-500">You don't have permission to view these records.</p>
        @endmodule
    </div>
@endsection
