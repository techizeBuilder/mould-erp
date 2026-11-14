@extends('layouts.app')

@section('title', 'Quotations')
@section('breadcrumb', 'Sales & CRM / Quotations')

@use('App\Models\Product')
@use('App\Models\Quotation')
@use('App\Support\Money')

@section('content')
    @module('sales_quotations', 'view')
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @include('sales._stat', ['label' => 'Open quotations', 'value' => '₹'.Money::format($stats['open_value'], 0), 'hint' => $stats['open_count'].' open'])
            @include('sales._stat', ['label' => 'Won value', 'value' => '₹'.Money::format($stats['won_value'], 0), 'tone' => 'text-emerald-700', 'href' => route('sales.quotations.index', ['status' => 'won'])])
            @include('sales._stat', ['label' => 'Win rate', 'value' => $stats['win_rate'] !== null ? $stats['win_rate'].'%' : '—', 'hint' => 'Won ÷ (won + lost)'])
            @include('sales._stat', ['label' => 'Expiring in 7 days', 'value' => $stats['expiring'], 'tone' => 'text-amber-700'])
            @include('sales._stat', ['label' => 'Expired, still open', 'value' => $stats['expired'], 'tone' => 'text-red-700', 'href' => route('sales.quotations.index', ['status' => 'expired'])])
        </div>
    @endmodule

    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Quotations @module('sales_quotations', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $quotations->total() }})</span>@endmodule</h2>
            @module('sales_quotations', 'create')
                <a href="{{ route('sales.quotations.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> New Quotation</a>
            @endmodule
        </div>

        @module('sales_quotations', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search quotation no. or buyer">
                    </div>
                </div>
                <select name="status" class="form-input w-auto" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach (Quotation::STATUSES + ['expired' => 'Expired'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="sample" class="form-input w-auto" aria-label="Sample status">
                    <option value="">All samples</option>
                    @foreach (Quotation::SAMPLE_STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('sample') === $value)>Sample: {{ $label }}</option>
                    @endforeach
                </select>
                <select name="product_type" class="form-input w-auto" aria-label="Product type">
                    <option value="">All product types</option>
                    @foreach (Product::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('product_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="product_id" class="form-input w-auto max-w-56" aria-label="Product">
                    <option value="">All products</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((int) request('product_id') === $p->id)>{{ $p->code }} – {{ $p->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'status', 'sample', 'product_type', 'product_id']))
                    <a href="{{ route('sales.quotations.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Quotation</th>
                        <th>Buyer</th>
                        <th>Products</th>
                        <th class="text-right">Total</th>
                        <th>Sample</th>
                        <th>Validity</th>
                        <th>Next follow-up</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($quotations as $q)
                        @php $next = $q->followUps->first(); @endphp
                        <tr>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('sales.quotations.show', $q) }}" class="font-mono text-xs font-semibold text-navy-800 hover:underline">{{ $q->number() }}</a>
                                <div class="text-xs text-slate-500">{{ $q->quote_date->format('d M Y') }}</div>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-800">{{ $q->buyerName() }}</div>
                                <div class="text-xs text-slate-500">{{ $q->assignee?->name }}</div>
                            </td>
                            <td class="max-w-xs">
                                <div class="truncate" title="{{ $q->items->pluck('description')->join(', ') }}">{{ $q->items->first()?->description }}</div>
                                @if ($q->items->count() > 1)<div class="text-xs text-slate-500">+{{ $q->items->count() - 1 }} more</div>@endif
                            </td>
                            <td class="text-right font-semibold tabular-nums whitespace-nowrap">₹{{ Money::format($q->total, 0) }}</td>
                            <td><span class="badge {{ Quotation::sampleTone($q->sample_status) }}">{{ $q->sampleLabel() }}</span></td>
                            <td class="whitespace-nowrap {{ $q->isExpired() ? 'font-semibold text-red-700' : '' }}">{{ $q->validityLabel() }}</td>
                            <td class="whitespace-nowrap">
                                @if ($next)
                                    <span class="{{ $next->isOverdue() ? 'font-semibold text-red-700' : '' }}">{{ $next->due_at->format('d M') }}</span>
                                @elseif ($q->isOpen())
                                    <span class="text-xs text-amber-700">None booked</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $q->statusTone() }}">{{ $q->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-sm text-slate-500">
                                No quotations found.
                                @module('sales_quotations', 'create')<a href="{{ route('sales.quotations.create') }}" class="font-semibold text-brand-600 hover:underline">Create the first one</a>.@endmodule
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($quotations->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $quotations->links() }}</div>@endif
        @else
            <p class="px-5 py-10 text-center text-sm text-slate-500">You don't have permission to view these records.</p>
        @endmodule
    </div>
@endsection
