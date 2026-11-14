@extends('layouts.app')

@section('title', 'Qualify Buyer')
@section('breadcrumb', 'Sales & CRM / Buyer Qualification / '.$lead->code)

@use('App\Models\BuyerQualification')
@use('App\Support\BuyerScore')
@use('App\Support\Money')

@php $canSave = auth()->user()->canModule('sales_qualification', $qualification->exists ? 'edit' : 'create'); @endphp

@section('content')
    <form method="POST" action="{{ route('sales.qualification.update', $lead) }}" class="max-w-5xl space-y-6">
        @csrf @method('PUT')

        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <div class="font-mono text-xs font-semibold text-navy-700">{{ $lead->code }} · {{ $lead->sourceLabel() }}</div>
                    <h2 class="card-title">{{ $lead->company_name }}</h2>
                    <div class="text-sm text-slate-500">{{ collect([$lead->industryLabel(), $lead->contact_person, $lead->phone])->filter()->join(' · ') }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs font-semibold text-slate-500 uppercase">Score</div>
                    <div class="text-2xl font-bold text-navy-900 tabular-nums"><span data-score>{{ $qualification->score ?? 0 }}</span><span class="text-sm font-normal text-slate-500"> / 100</span></div>
                    <div class="text-xs font-semibold text-slate-600" data-grade></div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead><tr><th>Product required</th><th class="text-right">Quantity</th><th class="text-right">Target price</th></tr></thead>
                    <tbody>
                    @foreach ($lead->items as $line)
                        <tr>
                            <td>@include('sales._line-spec', ['line' => $line])</td>
                            <td class="text-right whitespace-nowrap tabular-nums">{{ Money::format($line->quantity, 0) }} <span class="text-xs text-slate-500">{{ $line->qtyBasisLabel() }}</span></td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ $line->target_price !== null ? '₹'.Money::format($line->target_price, 3) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Assessment</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <x-form.input name="monthly_volume" label="Expected volume (pcs / month)" type="number" min="0" step="1" :value="$qualification->monthly_volume" required data-score-input
                              hint="Worked out from the quantities above. Change it if you know better." />
                @foreach (BuyerScore::CRITERIA as $criterion => $definition)
                    <x-form.select :name="$criterion" :label="$definition['label']" :options="BuyerScore::options($criterion)" :value="$qualification->{$criterion}" required data-score-input
                                   :hint="$criterion === 'mould_fit' ? 'Suggested from Mould Master for the products above.' : null" />
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Other checks</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <x-form.input name="compliance_required" label="Compliance required" list="compliance-options" :value="$qualification->compliance_required" maxlength="150" placeholder="e.g. Food grade, FDA" />
                <datalist id="compliance-options">
                    @foreach (['None', 'Food grade', 'Pharma grade / GMP', 'FSSAI', 'FDA', 'BIS', 'ISO 9001', 'Tamper-evident neck', 'UN certified (chemicals)'] as $option)<option value="{{ $option }}">@endforeach
                </datalist>
                <x-form.input name="current_supplier" label="Current supplier" :value="$qualification->current_supplier" maxlength="150" placeholder="Who they buy from today" />
                <div class="space-y-2 pt-1">
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="gst_verified" value="0"><input type="checkbox" name="gst_verified" value="1" class="h-4 w-4 accent-brand-600" @checked(old('gst_verified', $qualification->gst_verified))> GST number verified</label>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="sample_required" value="0"><input type="checkbox" name="sample_required" value="1" class="h-4 w-4 accent-brand-600" @checked(old('sample_required', $qualification->sample_required))> Buyer needs a sample before ordering</label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Decision</h2>
                <span class="text-sm text-slate-500">Score suggests: <span class="font-semibold text-slate-800" data-recommendation></span></span>
            </div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <x-form.select name="decision" label="Decision" :options="BuyerQualification::DECISIONS" :value="$qualification->decision" required />
                <div class="md:col-span-2">
                    <x-form.input name="decision_reason" label="Reason" :value="$qualification->decision_reason" maxlength="500" placeholder="Required if disqualified" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            @if ($qualification->exists)
                @module('sales_qualification', 'delete')
                    <button form="remove-qualification" class="btn mr-auto border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Remove assessment</button>
                @endmodule
            @endif
            <a href="{{ route('sales.qualification.index') }}" class="btn btn-secondary">{{ $canSave ? 'Cancel' : 'Back' }}</a>
            @if ($canSave)<button class="btn btn-primary">Save qualification</button>@endif
        </div>
    </form>

    @if ($qualification->exists)
        <form id="remove-qualification" method="POST" action="{{ route('sales.qualification.destroy', $lead) }}" data-confirm="Remove this assessment? The lead goes back to not assessed.">
            @csrf @method('DELETE')
        </form>
    @endif
@endsection

@push('scripts')
<script>
    (() => {
        const criteria = @json(collect(BuyerScore::CRITERIA)->map(fn ($c) => collect($c['options'])->map(fn ($o) => $o[1])));
        const bands = @json(collect(BuyerScore::VOLUME_BANDS)->map(fn ($points, $min) => [$min, $points])->values());
        const grades = @json(collect(BuyerScore::GRADES)->map(fn ($g) => ['min' => $g['min'], 'label' => $g['label']]));
        const decisions = @json(BuyerQualification::DECISIONS);
        const value = (id) => document.getElementById(id).value;

        const refresh = () => {
            const volume = parseFloat(value('monthly_volume')) || 0;
            let score = bands.find(([min]) => volume >= min)?.[1] ?? 0;
            for (const name in criteria) score += criteria[name][value(name)] ?? 0;
            score = Math.min(100, score);
            const grade = Object.keys(grades).find((g) => score >= grades[g].min);
            const suggested = value('mould_fit') === 'not_feasible' ? 'disqualified' : { A: 'qualified', B: 'qualified', C: 'nurture', D: 'disqualified' }[grade];
            document.querySelector('[data-score]').textContent = score;
            document.querySelector('[data-grade]').textContent = grades[grade].label;
            document.querySelector('[data-recommendation]').textContent = decisions[suggested];
        };

        document.querySelectorAll('[data-score-input]').forEach((el) => el.addEventListener('input', refresh));
        refresh();
    })();
</script>
@endpush
