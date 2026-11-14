@extends('layouts.app')

@section('title', 'Order Approval')
@section('breadcrumb', 'Order Management / Order Approval')

@use('App\Support\Money')

@section('content')
    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Order Approval</h2>
            @module('orders_approval', 'view')
                <div class="flex flex-wrap gap-1.5">
                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
                        <a href="{{ route('orders.approval.index', ['tab' => $key]) }}" class="btn btn-sm {{ $tab === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
                    @endforeach
                </div>
            @endmodule
        </div>

        @module('orders_approval', 'view')
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Products</th>
                        <th class="text-right">Total (₹)</th>
                        @if ($tab === 'pending')
                            <th>Credit</th>
                            <th>Entered by</th>
                        @else
                            <th>Decided by</th>
                            <th>Note</th>
                        @endif
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('orders.approval.show', $order) }}" class="font-mono text-xs font-semibold text-navy-800 hover:underline">{{ $order->code }}</a>
                                <div class="text-xs text-slate-500">{{ $order->order_date->format('d M Y') }}</div>
                            </td>
                            <td class="font-semibold text-slate-800">{{ $order->customer->company_name }}</td>
                            <td class="max-w-xs">
                                <div class="truncate" title="{{ $order->items->pluck('description')->join(', ') }}">{{ $order->items->first()?->description }}</div>
                                @if ($order->items->count() > 1)<div class="text-xs text-slate-500">+{{ $order->items->count() - 1 }} more</div>@endif
                            </td>
                            <td class="text-right font-semibold tabular-nums whitespace-nowrap">{{ Money::format($order->total) }}</td>
                            @if ($tab === 'pending')
                                @php $credit = $order->creditCheck(); @endphp
                                <td class="whitespace-nowrap">
                                    @if (! $credit['limited'])
                                        <span class="badge bg-slate-100 text-slate-600">No limit set</span>
                                    @elseif ($credit['exceeded'])
                                        <span class="badge bg-red-50 text-red-700">Over by ₹{{ Money::format($credit['over_by'], 0) }}</span>
                                    @else
                                        <span class="badge bg-emerald-50 text-emerald-700">Within limit</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">{{ $order->creator?->name ?? '—' }}</td>
                            @else
                                <td class="whitespace-nowrap">
                                    {{ $order->decider?->name ?? '—' }}
                                    <div class="text-xs text-slate-500">{{ $order->decided_at?->format('d M Y') }}</div>
                                </td>
                                <td class="max-w-xs truncate">{{ $order->decision_note ?: '—' }}</td>
                            @endif
                            <td class="text-right">
                                <a href="{{ route('orders.approval.show', $order) }}" class="btn btn-secondary btn-sm whitespace-nowrap"><x-icon name="shield" class="h-4 w-4" /> {{ $tab === 'pending' ? 'Review' : 'View' }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-12 text-center text-sm text-slate-500">{{ $tab === 'pending' ? 'Nothing is waiting for approval.' : 'Nothing here yet.' }}</td></tr>
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
