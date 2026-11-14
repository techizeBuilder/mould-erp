@extends('layouts.app')

@section('title', 'Leads')
@section('breadcrumb', 'Sales & CRM / Leads')

@use('App\Models\Lead')
@use('App\Models\Product')
@use('App\Support\Capacity')

@section('content')
    @module('sales_leads', 'view')
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @include('sales._stat', ['label' => 'New leads', 'value' => $statusCounts['new'] ?? 0, 'href' => route('sales.leads.index', ['status' => 'new'])])
            @include('sales._stat', ['label' => 'Qualified', 'value' => $statusCounts['qualified'] ?? 0, 'tone' => 'text-emerald-700', 'href' => route('sales.leads.index', ['status' => 'qualified'])])
            @include('sales._stat', ['label' => 'Quoted', 'value' => $statusCounts['quoted'] ?? 0, 'tone' => 'text-amber-700', 'href' => route('sales.leads.index', ['status' => 'quoted'])])
            @include('sales._stat', ['label' => 'Won', 'value' => $statusCounts['won'] ?? 0, 'tone' => 'text-emerald-700', 'href' => route('sales.leads.index', ['status' => 'won'])])
        </div>
    @endmodule

    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Leads @module('sales_leads', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $leads->total() }})</span>@endmodule</h2>
            @module('sales_leads', 'create')
                <a href="{{ route('sales.leads.create') }}" class="btn btn-primary"><x-icon name="user-plus" class="h-4 w-4" /> Add Lead</a>
            @endmodule
        </div>

        @module('sales_leads', 'view')
            @if ($sourceCounts->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-5 py-3 text-xs">
                    <span class="font-semibold tracking-wide text-slate-500 uppercase">By source</span>
                    @foreach ($sourceCounts as $source => $total)
                        <a href="{{ route('sales.leads.index', ['source' => $source]) }}" class="badge {{ request('source') === $source ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">{{ Lead::SOURCES[$source] ?? $source }}: {{ $total }}</a>
                    @endforeach
                </div>
            @endif

            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search code, company, contact, mobile or city">
                    </div>
                </div>
                <select name="source" class="form-input w-auto" aria-label="Source">
                    <option value="">All sources</option>
                    @foreach (Lead::SOURCES as $value => $label)
                        <option value="{{ $value }}" @selected(request('source') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-input w-auto" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach (Lead::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="industry" class="form-input w-auto" aria-label="Industry">
                    <option value="">All industries</option>
                    @foreach (Lead::INDUSTRIES as $value => $label)
                        <option value="{{ $value }}" @selected(request('industry') === $value)>{{ $label }}</option>
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
                @if (request()->hasAny(['search', 'source', 'status', 'industry', 'product_type', 'product_id']))
                    <a href="{{ route('sales.leads.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Buyer</th>
                        <th>Source</th>
                        <th>Products required</th>
                        <th class="text-right">Pcs / month</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($leads as $lead)
                        <tr>
                            <td class="font-mono text-xs font-semibold whitespace-nowrap text-navy-800">{{ $lead->code }}</td>
                            <td>
                                <a href="{{ route('sales.leads.show', $lead) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $lead->company_name }}</a>
                                <div class="text-xs text-slate-500">{{ collect([$lead->contact_person, $lead->phone, $lead->city])->filter()->join(' · ') }}</div>
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $lead->sourceLabel() }}
                                @if ($lead->source_detail)<div class="text-xs text-slate-500">{{ $lead->source_detail }}</div>@endif
                            </td>
                            <td class="max-w-xs">
                                @foreach ($lead->items->take(2) as $item)
                                    <div class="truncate" title="{{ $item->description }}">{{ $item->description }}</div>
                                @endforeach
                                @if ($lead->items->count() > 2)<div class="text-xs text-slate-500">+{{ $lead->items->count() - 2 }} more</div>@endif
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ \App\Support\Money::format($lead->estimatedMonthlyVolume(), 0) }}</td>
                            <td class="whitespace-nowrap">
                                @if ($lead->qualification)
                                    <span class="badge {{ $lead->qualification->gradeTone() }}">{{ $lead->qualification->gradeLabel() }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $lead->statusTone() }}">{{ $lead->statusLabel() }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('sales.leads.show', $lead) }}" class="btn btn-secondary btn-sm" title="View"><x-icon name="eye" class="h-4 w-4" /></a>
                                    @module('sales_leads', 'edit')
                                        <a href="{{ route('sales.leads.edit', $lead) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    @endmodule
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-sm text-slate-500">
                                No leads found.
                                @module('sales_leads', 'create')<a href="{{ route('sales.leads.create') }}" class="font-semibold text-brand-600 hover:underline">Add the first one</a>.@endmodule
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($leads->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $leads->links() }}</div>@endif
        @else
            <p class="px-5 py-10 text-center text-sm text-slate-500">You don't have permission to view these records.</p>
        @endmodule
    </div>
@endsection
