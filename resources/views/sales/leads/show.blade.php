@extends('layouts.app')

@section('title', $lead->company_name)
@section('breadcrumb', 'Sales & CRM / Leads / '.$lead->code)

@use('App\Support\Money')
@use('App\Support\Capacity')

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <div class="font-mono text-xs font-semibold text-navy-700">{{ $lead->code }}</div>
                    <h2 class="card-title">{{ $lead->company_name }} <span class="badge ml-1 {{ $lead->statusTone() }}">{{ $lead->statusLabel() }}</span></h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    @module('sales_qualification', 'view')
                        <a href="{{ route('sales.qualification.edit', $lead) }}" class="btn btn-secondary"><x-icon name="shield" class="h-4 w-4" /> {{ $lead->qualification ? 'Qualification' : 'Qualify buyer' }}</a>
                    @endmodule
                    @module('sales_quotations', 'create')
                        @if ($lead->canBeQuoted())
                            <a href="{{ route('sales.quotations.create', ['lead' => $lead->id]) }}" class="btn btn-primary"><x-icon name="document" class="h-4 w-4" /> Create quotation</a>
                        @endif
                    @endmodule
                    @module('sales_leads', 'edit')
                        <a href="{{ route('sales.leads.edit', $lead) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                    @endmodule
                    @module('sales_leads', 'delete')
                        <form method="POST" action="{{ route('sales.leads.destroy', $lead) }}" data-confirm="Delete lead &quot;{{ $lead->code }} – {{ $lead->company_name }}&quot; and its follow-ups?">
                            @csrf @method('DELETE')
                            <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                        </form>
                    @endmodule
                </div>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-detail label="Lead source">{{ $lead->sourceLabel() }}</x-detail>
                <x-detail label="Source details">{{ $lead->source_detail }}</x-detail>
                <x-detail label="Enquiry date">{{ $lead->enquiry_date->format('d M Y') }}</x-detail>
                <x-detail label="Expected order date">{{ $lead->expected_close_date?->format('d M Y') }}</x-detail>
                <x-detail label="Industry">{{ $lead->industryLabel() }}</x-detail>
                <x-detail label="Contact person">{{ $lead->contact_person }}</x-detail>
                <x-detail label="Mobile">{{ $lead->phone }}</x-detail>
                <x-detail label="Email">{{ $lead->email }}</x-detail>
                <x-detail label="City / state">{{ collect([$lead->city, $lead->state])->filter()->join(', ') }}</x-detail>
                <x-detail label="GSTIN">{{ $lead->gstin }}</x-detail>
                <x-detail label="Assigned to">{{ $lead->assignee?->name }}</x-detail>
                <x-detail label="Added by">{{ $lead->creator?->name }}{{ $lead->creator ? ' · ' : '' }}{{ $lead->created_at?->format('d M Y') }}</x-detail>
                @if ($lead->status === 'lost')
                    <x-detail label="Reason lost">{{ $lead->lostReasonLabel() }}</x-detail>
                @endif
                @if ($lead->customer)
                    <x-detail label="Customer">
                        @module('sales_retention', 'view')
                            <a href="{{ route('sales.retention.show', $lead->customer) }}" class="font-semibold text-brand-600 hover:underline">{{ $lead->customer->code }}</a>
                        @else
                            {{ $lead->customer->code }}
                        @endmodule
                    </x-detail>
                @endif
                @if ($lead->address)
                    <x-detail label="Address" class="sm:col-span-2"><span class="whitespace-pre-line">{{ $lead->address }}</span></x-detail>
                @endif
                @if ($lead->notes)
                    <x-detail label="Notes" class="sm:col-span-2 lg:col-span-4"><span class="whitespace-pre-line">{{ $lead->notes }}</span></x-detail>
                @endif
            </dl>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Products required</h2>
                <span class="text-sm text-slate-500">About <span class="font-bold text-navy-900 tabular-nums">{{ Money::format($lead->estimatedMonthlyVolume(), 0) }}</span> pcs / month</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead><tr><th>Product</th><th class="text-right">Quantity</th><th class="text-right">Target price</th></tr></thead>
                    <tbody>
                    @foreach ($lead->items as $line)
                        <tr>
                            <td>@include('sales._line-spec', ['line' => $line])</td>
                            <td class="text-right whitespace-nowrap">
                                <span class="tabular-nums">{{ Money::format($line->quantity, 0) }}</span>
                                <div class="text-xs text-slate-500">{{ $line->qtyBasisLabel() }}</div>
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ $line->target_price !== null ? '₹'.Money::format($line->target_price, 3) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            @module('sales_qualification', 'view')
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Buyer qualification</h2></div>
                    @if ($q = $lead->qualification)
                        <dl class="grid gap-5 p-5 sm:grid-cols-2">
                            <x-detail label="Score">{{ $q->score }} / 100 <span class="badge ml-1 {{ $q->gradeTone() }}">{{ $q->gradeLabel() }}</span></x-detail>
                            <x-detail label="Decision"><span class="badge {{ $q::decisionTone($q->decision) }}">{{ $q->decisionLabel() }}</span></x-detail>
                            <x-detail label="Assessed by">{{ $q->assessor?->name }}</x-detail>
                            <x-detail label="Sample required">{{ $q->sample_required ? 'Yes' : 'No' }}</x-detail>
                            @if ($q->decision_reason)<x-detail label="Reason" class="sm:col-span-2">{{ $q->decision_reason }}</x-detail>@endif
                        </dl>
                    @else
                        <p class="px-5 py-4 text-sm text-slate-500">Not assessed yet.</p>
                    @endif
                </div>
            @endmodule

            @module('sales_quotations', 'view')
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Quotations</h2></div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($quotations as $q)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                <a href="{{ route('sales.quotations.show', $q) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $q->number() }}</a>
                                <span class="tabular-nums">₹{{ Money::format($q->total, 0) }}</span>
                                <span class="badge {{ $q->statusTone() }}">{{ $q->statusLabel() }}</span>
                            </li>
                        @empty
                            <li class="px-5 py-4 text-sm text-slate-500">No quotations yet.</li>
                        @endforelse
                    </ul>
                </div>
            @endmodule
        </div>

        @module('sales_followups', 'show')
            @include('sales._followup-list', ['createQuery' => ['lead' => $lead->id]])
        @endmodule

        <a href="{{ route('sales.leads.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline">← Back to leads</a>
    </div>
@endsection
