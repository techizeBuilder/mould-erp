@extends('layouts.app')

@section('title', 'Buyer Qualification')
@section('breadcrumb', 'Sales & CRM / Buyer Qualification')

@use('App\Models\BuyerQualification')
@use('App\Support\BuyerScore')
@use('App\Support\Money')

@section('content')
    @module('sales_qualification', 'view')
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @include('sales._stat', ['label' => 'To be assessed', 'value' => $counts['pending'], 'tone' => 'text-amber-700', 'href' => route('sales.qualification.index', ['decision' => 'pending'])])
            @include('sales._stat', ['label' => 'Qualified', 'value' => $counts['qualified'], 'tone' => 'text-emerald-700', 'href' => route('sales.qualification.index', ['decision' => 'qualified', 'scope' => 'all'])])
            @include('sales._stat', ['label' => 'Nurture later', 'value' => $counts['nurture'], 'href' => route('sales.qualification.index', ['decision' => 'nurture', 'scope' => 'all'])])
            @include('sales._stat', ['label' => 'Disqualified', 'value' => $counts['disqualified'], 'tone' => 'text-red-700', 'href' => route('sales.qualification.index', ['decision' => 'disqualified', 'scope' => 'all'])])
        </div>
    @endmodule

    <div class="card">
        <div class="card-header"><h2 class="card-title">Buyers to qualify</h2></div>

        @module('sales_qualification', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search code, company or mobile">
                    </div>
                </div>
                <select name="decision" class="form-input w-auto" aria-label="Decision">
                    <option value="">All decisions</option>
                    @foreach (BuyerQualification::DECISIONS as $value => $label)
                        <option value="{{ $value }}" @selected(request('decision') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="grade" class="form-input w-auto" aria-label="Grade">
                    <option value="">All grades</option>
                    @foreach (BuyerScore::GRADES as $value => $grade)
                        <option value="{{ $value }}" @selected(request('grade') === $value)>{{ $grade['label'] }}</option>
                    @endforeach
                </select>
                <select name="scope" class="form-input w-auto" aria-label="Lead status">
                    <option value="open" @selected(request('scope', 'open') === 'open')>Open leads</option>
                    <option value="all" @selected(request('scope') === 'all')>All leads</option>
                </select>
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'decision', 'grade', 'scope']))
                    <a href="{{ route('sales.qualification.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Buyer</th>
                        <th>Products</th>
                        <th class="text-right">Pcs / month</th>
                        <th>Mould fit</th>
                        <th>Score</th>
                        <th>Decision</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($leads as $lead)
                        @php $q = $lead->qualification; @endphp
                        <tr>
                            <td class="font-mono text-xs font-semibold whitespace-nowrap">
                                <a href="{{ route('sales.leads.show', $lead) }}" class="text-navy-800 hover:underline">{{ $lead->code }}</a>
                            </td>
                            <td>
                                <div class="font-semibold text-slate-800">{{ $lead->company_name }}</div>
                                <div class="text-xs text-slate-500">{{ collect([$lead->industryLabel(), $q ? BuyerScore::label('buyer_type', $q->buyer_type) : null])->filter()->join(' · ') }}</div>
                            </td>
                            <td class="max-w-xs">
                                <div class="truncate" title="{{ $lead->items->pluck('description')->join(', ') }}">{{ $lead->items->first()?->description }}</div>
                                @if ($lead->items->count() > 1)<div class="text-xs text-slate-500">+{{ $lead->items->count() - 1 }} more</div>@endif
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ Money::format($q?->monthly_volume ?? $lead->estimatedMonthlyVolume(), 0) }}</td>
                            <td class="whitespace-nowrap">{{ $q ? BuyerScore::label('mould_fit', $q->mould_fit) : BuyerScore::label('mould_fit', $lead->suggestedMouldFit()) }}@unless ($q)<div class="text-xs text-slate-400">Suggested</div>@endunless</td>
                            <td class="whitespace-nowrap">
                                @if ($q)
                                    <span class="font-semibold tabular-nums">{{ $q->score }}</span>
                                    <span class="badge ml-1 {{ $q->gradeTone() }}">{{ $q->gradeLabel() }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td><span class="badge {{ BuyerQualification::decisionTone($q?->decision) }}">{{ $q?->decisionLabel() ?? 'Not assessed' }}</span></td>
                            <td class="text-right">
                                <a href="{{ route('sales.qualification.edit', $lead) }}" class="btn btn-secondary btn-sm whitespace-nowrap"><x-icon name="shield" class="h-4 w-4" /> {{ $q ? 'Review' : 'Qualify' }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-12 text-center text-sm text-slate-500">No buyers match these filters.</td></tr>
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
