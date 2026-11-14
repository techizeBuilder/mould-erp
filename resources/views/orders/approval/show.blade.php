@extends('layouts.app')

@section('title', 'Review '.$order->code)
@section('breadcrumb', 'Order Management / Order Approval / '.$order->code)

@php
    $tone = [
        'ok' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'check'],
        'warning' => ['border-amber-200 bg-amber-50 text-amber-800', 'alert'],
        'danger' => ['border-red-200 bg-red-50 text-red-800', 'alert'],
    ];
    $overLimit = collect($checks)->contains(fn ($c) => $c[0] === 'danger');
    $mayDecide = $order->canBeDecidedBy(auth()->user());
@endphp

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <div class="font-mono text-xs font-semibold text-navy-700">{{ $order->code }}</div>
                    <h2 class="card-title">Sales order <span class="badge ml-1 {{ $order->statusTone() }}">{{ $order->statusLabel() }}</span></h2>
                </div>
                @module('orders_sales', 'view')
                    <a href="{{ route('orders.sales.show', $order) }}" class="btn btn-secondary"><x-icon name="eye" class="h-4 w-4" /> Open order</a>
                @endmodule
            </div>
            @include('orders.sales._summary', ['lastRates' => $lastRates])
        </div>

        @if ($order->isPending())
            <div class="card">
                <div class="card-header"><h2 class="card-title">Checks before approving</h2></div>
                <div class="space-y-2 p-5">
                    @foreach ($checks as [$level, $message])
                        <div class="flex items-start gap-2 rounded border px-3 py-2 text-sm {{ $tone[$level][0] }}">
                            <x-icon :name="$tone[$level][1]" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $message }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @module('orders_approval', 'edit')
                @if ($mayDecide)
                    <div class="grid gap-6 lg:grid-cols-2">
                        <form method="POST" action="{{ route('orders.approval.approve', $order) }}" class="card">
                            @csrf
                            <div class="card-header"><h2 class="card-title">Approve</h2></div>
                            <div class="p-5">
                                <x-form.input name="note" :label="$overLimit ? 'Why approve over the limit?' : 'Note'" :required="$overLimit" maxlength="500" :placeholder="$overLimit ? '' : 'Optional'" />
                            </div>
                            <div class="flex justify-end border-t border-slate-200 px-5 py-3"><button class="btn btn-primary"><x-icon name="check" class="h-4 w-4" /> Approve order</button></div>
                        </form>
                        <form method="POST" action="{{ route('orders.approval.reject', $order) }}" class="card">
                            @csrf
                            <div class="card-header"><h2 class="card-title">Reject</h2></div>
                            <div class="p-5">
                                <x-form.input name="reason" label="Reason" required maxlength="500" placeholder="What needs correcting" />
                            </div>
                            <div class="flex justify-end border-t border-slate-200 px-5 py-3"><button class="btn btn-danger"><x-icon name="x" class="h-4 w-4" /> Reject and send back</button></div>
                        </form>
                    </div>
                @else
                    <div class="rounded border border-sky-200 border-l-4 border-l-sky-600 bg-sky-50 px-4 py-3 text-sm text-sky-800">You entered this order, so someone else has to approve it.</div>
                @endif
            @endmodule
        @elseif ($order->decided_at)
            <div class="rounded border px-4 py-3 text-sm {{ $order->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
                {{ $order->statusLabel() }}. Decided by {{ $order->decider?->name ?? '—' }} on {{ $order->decided_at->format('d M Y, h:i A') }}@if ($order->decision_note): {{ $order->decision_note }}@endif
            </div>
        @endif

        @include('orders.sales._trail')

        <a href="{{ route('orders.approval.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline">← Back to order approval</a>
    </div>
@endsection
