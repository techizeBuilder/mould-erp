@extends('layouts.app')

@section('title', 'Sales Order '.$order->code)
@section('breadcrumb', 'Order Management / Sales Orders / '.$order->code)

@php $canCancel = $order->canCancel() && ($order->status !== 'approved' || auth()->user()->canModule('orders_approval', 'edit')); @endphp

@section('content')
    <div class="max-w-5xl space-y-6">
        @if ($order->status === 'rejected')
            <div class="rounded border border-red-200 border-l-4 border-l-red-600 bg-red-50 px-4 py-3 text-sm text-red-800">
                Rejected by {{ $order->decider?->name ?? 'the approver' }}: <strong>{{ $order->decision_note }}</strong>
            </div>
        @elseif ($order->status === 'approved')
            <div class="rounded border border-emerald-200 border-l-4 border-l-emerald-600 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                Approved by {{ $order->decider?->name ?? 'the approver' }} on {{ $order->decided_at?->format('d M Y, h:i A') }}@if ($order->decision_note): {{ $order->decision_note }}@endif
            </div>
        @endif

        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <div class="font-mono text-xs font-semibold text-navy-700">{{ $order->code }}</div>
                    <h2 class="card-title">Sales order <span class="badge ml-1 {{ $order->statusTone() }}">{{ $order->statusLabel() }}</span></h2>
                </div>
                <div class="flex flex-wrap gap-2 print:hidden">
                    <button type="button" onclick="window.print()" class="btn btn-secondary"><x-icon name="printer" class="h-4 w-4" /> Print</button>
                    @if ($order->isPending())
                        @module('orders_approval', 'view')
                            <a href="{{ route('orders.approval.show', $order) }}" class="btn btn-primary"><x-icon name="shield" class="h-4 w-4" /> Review for approval</a>
                        @endmodule
                    @endif
                    @if ($order->isEditable())
                        @module('orders_sales', 'edit')
                            <a href="{{ route('orders.sales.edit', $order) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                            <form method="POST" action="{{ route('orders.sales.submit', $order) }}">
                                @csrf
                                <button class="btn btn-primary"><x-icon name="check" class="h-4 w-4" /> Submit for approval</button>
                            </form>
                        @endmodule
                    @endif
                    @if ($order->canDelete())
                        @module('orders_sales', 'delete')
                            <form method="POST" action="{{ route('orders.sales.destroy', $order) }}" data-confirm="Delete draft order {{ $order->code }}?">
                                @csrf @method('DELETE')
                                <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                            </form>
                        @endmodule
                    @endif
                </div>
            </div>
            @include('orders.sales._summary')
        </div>

        @if ($canCancel)
            @module('orders_sales', 'edit')
                <details class="rounded border border-red-200 bg-red-50/40 print:hidden" @if ($errors->has('reason')) open @endif>
                    <summary class="cursor-pointer px-4 py-2 text-sm font-semibold text-red-800">Cancel this order</summary>
                    <form method="POST" action="{{ route('orders.sales.cancel', $order) }}" class="flex flex-wrap items-end gap-3 border-t border-red-200 p-4" data-confirm="Cancel order {{ $order->code }}?">
                        @csrf
                        <x-form.input class="min-w-64 flex-1" name="reason" label="Reason" required maxlength="500" />
                        <button class="btn btn-danger">Cancel order</button>
                    </form>
                </details>
            @endmodule
        @endif

        @include('orders.sales._trail')

        <a href="{{ route('orders.sales.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline print:hidden">← Back to sales orders</a>
    </div>
@endsection
