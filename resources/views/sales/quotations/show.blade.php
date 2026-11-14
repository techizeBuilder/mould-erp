@extends('layouts.app')

@section('title', 'Quotation '.$quotation->number())
@section('breadcrumb', 'Sales & CRM / Quotations / '.$quotation->number())

@use('App\Models\Lead')
@use('App\Models\Quotation')
@use('App\Support\BuyerScore')
@use('App\Support\Money')

@php $party = $quotation->buyerParty(); @endphp

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <div class="font-mono text-xs font-semibold text-navy-700">{{ $quotation->number() }}</div>
                    <h2 class="card-title">{{ $quotation->buyerName() }}
                        <span class="badge ml-1 {{ $quotation->statusTone() }}">{{ $quotation->statusLabel() }}</span>
                        @if ($quotation->isExpired())<span class="badge bg-red-50 text-red-700">Expired</span>@endif
                    </h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($quotation->status === 'won')
                        @php $salesOrder = $quotation->salesOrders()->live()->first(); @endphp
                        @if ($salesOrder)
                            @module('orders_sales', 'view')
                                <a href="{{ route('orders.sales.show', $salesOrder) }}" class="btn btn-secondary"><x-icon name="document" class="h-4 w-4" /> {{ $salesOrder->code }}</a>
                            @endmodule
                        @else
                            @module('orders_sales', 'create')
                                <a href="{{ route('orders.sales.create', ['quotation' => $quotation->id]) }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Create sales order</a>
                            @endmodule
                        @endif
                    @endif
                    <a href="{{ route('sales.quotations.print', $quotation) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="printer" class="h-4 w-4" /> Print</a>
                    @if ($quotation->isDraft())
                        @module('sales_quotations', 'edit')
                            <a href="{{ route('sales.quotations.edit', $quotation) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                        @endmodule
                    @endif
                    @if ($quotation->canRevise())
                        @module('sales_quotations', 'create')
                            <form method="POST" action="{{ route('sales.quotations.revise', $quotation) }}" data-confirm="Start a new revision of {{ $quotation->number() }}? This version will be marked Revised.">
                                @csrf
                                <button class="btn btn-secondary"><x-icon name="copy" class="h-4 w-4" /> Revise</button>
                            </form>
                        @endmodule
                    @endif
                    @if ($quotation->canDelete())
                        @module('sales_quotations', 'delete')
                            <form method="POST" action="{{ route('sales.quotations.destroy', $quotation) }}" data-confirm="Delete draft quotation {{ $quotation->number() }}?">
                                @csrf @method('DELETE')
                                <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                            </form>
                        @endmodule
                    @endif
                </div>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-detail label="Quotation date">{{ $quotation->quote_date->format('d M Y') }}</x-detail>
                <x-detail label="Valid until">{{ $quotation->valid_until->format('d M Y') }}{{ $quotation->isOpen() ? ' ('.$quotation->validityLabel().')' : '' }}</x-detail>
                <x-detail label="Sent on">{{ $quotation->sent_on?->format('d M Y') }}</x-detail>
                <x-detail label="Handled by">{{ $quotation->assignee?->name }}</x-detail>
                <x-detail label="Contact">{{ collect([$party?->contact_person, $party?->phone])->filter()->join(' · ') }}</x-detail>
                <x-detail label="Lead">
                    @if ($quotation->lead)
                        @module('sales_leads', 'view')
                            <a href="{{ route('sales.leads.show', $quotation->lead) }}" class="font-semibold text-brand-600 hover:underline">{{ $quotation->lead->code }}</a>
                        @else
                            {{ $quotation->lead->code }}
                        @endmodule
                        · {{ $quotation->lead->sourceLabel() }}
                    @endif
                </x-detail>
                <x-detail label="Customer">
                    @if ($quotation->customer)
                        @module('sales_retention', 'view')
                            <a href="{{ route('sales.retention.show', $quotation->customer) }}" class="font-semibold text-brand-600 hover:underline">{{ $quotation->customer->code }}</a>
                        @else
                            {{ $quotation->customer->code }}
                        @endmodule
                    @endif
                </x-detail>
                <x-detail label="Buyer grade">
                    @if ($quotation->lead?->qualification)
                        <span class="badge {{ $quotation->lead->qualification->gradeTone() }}">{{ $quotation->lead->qualification->gradeLabel() }}</span>
                    @endif
                </x-detail>
                <x-detail label="Price basis">{{ Quotation::PRICE_BASES[$quotation->price_basis] ?? '' }}</x-detail>
                <x-detail label="Payment terms">{{ BuyerScore::label('payment_terms', $quotation->payment_terms) }}</x-detail>
                <x-detail label="Delivery">{{ $quotation->delivery_days !== null ? $quotation->delivery_days.' days after order' : '' }}</x-detail>
                <x-detail label="New mould lead time">{{ $quotation->mould_lead_days !== null ? $quotation->mould_lead_days.' days' : '' }}</x-detail>
                <x-detail label="Packing">{{ $quotation->packing }}</x-detail>
                @if ($quotation->status === 'won')
                    <x-detail label="Buyer's PO">{{ $quotation->po_number }}</x-detail>
                    <x-detail label="Order date">{{ $quotation->won_on?->format('d M Y') }}</x-detail>
                @endif
                @if ($quotation->lost_reason)
                    <x-detail label="Reason lost">{{ Lead::LOST_REASONS[$quotation->lost_reason] ?? '' }}</x-detail>
                    <x-detail label="Lost to">{{ $quotation->competitor }}</x-detail>
                @endif
                @if ($quotation->terms)<x-detail label="Other terms" class="sm:col-span-2 lg:col-span-4"><span class="whitespace-pre-line">{{ $quotation->terms }}</span></x-detail>@endif
                @if ($quotation->remarks)<x-detail label="Internal remarks" class="sm:col-span-2 lg:col-span-4"><span class="whitespace-pre-line">{{ $quotation->remarks }}</span></x-detail>@endif
            </dl>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Products quoted</h2></div>
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead><tr><th>Product</th><th class="text-right">Qty</th><th class="text-right">MOQ</th><th class="text-right">Rate</th><th class="text-right">Mould charge</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                    @foreach ($quotation->items as $line)
                        <tr>
                            <td>@include('sales._line-spec', ['line' => $line])</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ Money::format($line->quantity, 0) }} <span class="text-xs text-slate-500">{{ $line->unitLabel() }}</span></td>
                            <td class="text-right tabular-nums">{{ $line->moq ? Money::format($line->moq, 0) : '—' }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">₹{{ Money::format($line->rate, 3) }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ (float) $line->mould_charge ? '₹'.Money::format($line->mould_charge) : '—' }}</td>
                            <td class="text-right font-semibold tabular-nums whitespace-nowrap">₹{{ Money::format($line->amount) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <dl class="ml-auto max-w-sm space-y-1.5 p-5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Goods</dt><dd class="tabular-nums">₹{{ Money::format($quotation->subtotal) }}</dd></div>
                @if ((float) $quotation->discount_amount)<div class="flex justify-between"><dt class="text-slate-500">Discount ({{ (float) $quotation->discount_percent }}%)</dt><dd class="tabular-nums">−₹{{ Money::format($quotation->discount_amount) }}</dd></div>@endif
                @if ((float) $quotation->mould_total)<div class="flex justify-between"><dt class="text-slate-500">Mould charges</dt><dd class="tabular-nums">₹{{ Money::format($quotation->mould_total) }}</dd></div>@endif
                @if ((float) $quotation->freight_amount)<div class="flex justify-between"><dt class="text-slate-500">Freight</dt><dd class="tabular-nums">₹{{ Money::format($quotation->freight_amount) }}</dd></div>@endif
                <div class="flex justify-between"><dt class="text-slate-500">GST ({{ (float) $quotation->gst_percent }}%)</dt><dd class="tabular-nums">₹{{ Money::format($quotation->tax_amount) }}</dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold text-navy-900"><dt>Total</dt><dd class="tabular-nums">₹{{ Money::format($quotation->total) }}</dd></div>
            </dl>
        </div>

        @module('sales_quotations', 'edit')
            @if ($quotation->nextStatuses())
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Update status</h2></div>
                    <div class="flex flex-wrap items-start gap-3 p-5">
                        @foreach (array_diff($quotation->nextStatuses(), ['won', 'lost']) as $next)
                            <form method="POST" action="{{ route('sales.quotations.status', $quotation) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $next }}">
                                <button class="btn btn-secondary">Mark {{ Quotation::STATUSES[$next] }}</button>
                            </form>
                        @endforeach

                        @if (in_array('won', $quotation->nextStatuses(), true))
                            <details class="w-full rounded border border-emerald-200 bg-emerald-50/40" @if ($errors->has('won_on')) open @endif>
                                <summary class="cursor-pointer px-4 py-2 text-sm font-semibold text-emerald-800">Mark Won — buyer placed the order</summary>
                                <form method="POST" action="{{ route('sales.quotations.status', $quotation) }}" class="grid gap-4 border-t border-emerald-200 p-4 md:grid-cols-3">
                                    @csrf
                                    <input type="hidden" name="status" value="won">
                                    <x-form.input name="po_number" label="Buyer's PO no." maxlength="60" />
                                    <x-form.input name="won_on" label="Order date" type="date" :value="now()->format('Y-m-d')" required />
                                    <div class="flex items-end"><button class="btn btn-primary">Confirm order</button></div>
                                    <p class="text-xs text-slate-600 md:col-span-3">{{ $quotation->customer_id ? 'The products are added to this customer\'s order history.' : 'A customer is created from the lead (or matched by mobile / GSTIN) and the products go into their order history.' }}</p>
                                </form>
                            </details>
                        @endif

                        @if (in_array('lost', $quotation->nextStatuses(), true))
                            <details class="w-full rounded border border-red-200 bg-red-50/40" @if ($errors->has('lost_reason')) open @endif>
                                <summary class="cursor-pointer px-4 py-2 text-sm font-semibold text-red-800">Mark Lost</summary>
                                <form method="POST" action="{{ route('sales.quotations.status', $quotation) }}" class="grid gap-4 border-t border-red-200 p-4 md:grid-cols-3">
                                    @csrf
                                    <input type="hidden" name="status" value="lost">
                                    <x-form.select name="lost_reason" label="Reason" :options="Lead::LOST_REASONS" required placeholder="— Select —" />
                                    <x-form.input name="competitor" label="Lost to (competitor)" maxlength="150" />
                                    <div class="flex items-end"><button class="btn btn-danger">Mark lost</button></div>
                                </form>
                            </details>
                        @endif
                    </div>
                </div>
            @endif
        @endmodule

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Sample</h2>
                    <span class="badge {{ Quotation::sampleTone($quotation->sample_status) }}">{{ $quotation->sampleLabel() }}</span>
                </div>
                <div class="p-5 text-sm">
                    @if ($quotation->sample_sent_on)<p class="mb-3 text-slate-600">Sent on {{ $quotation->sample_sent_on->format('d M Y') }}.</p>@endif
                    @module('sales_quotations', 'edit')
                        <form method="POST" action="{{ route('sales.quotations.sample', $quotation) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="flex-1">
                                <label for="sample_status" class="form-label">Sample status</label>
                                <select id="sample_status" name="sample_status" class="form-input">
                                    @foreach (Quotation::SAMPLE_STATUSES as $value => $label)
                                        <option value="{{ $value }}" @selected($quotation->sample_status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-secondary">Update</button>
                        </form>
                    @endmodule
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Timeline</h2></div>
                <ol class="divide-y divide-slate-100">
                    @foreach ($quotation->statusLogs->sortByDesc('id') as $log)
                        <li class="px-5 py-2.5 text-sm">
                            <span class="font-semibold text-slate-800">{{ Quotation::STATUSES[$log->to_status] ?? $log->to_status }}</span>
                            @if ($log->note)<span class="text-slate-600">— {{ $log->note }}</span>@endif
                            <div class="text-xs text-slate-500">{{ $log->created_at->format('d M Y, h:i A') }}{{ $log->user ? ' · '.$log->user->name : '' }}</div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        @if ($revisions->count() > 1)
            <div class="card">
                <div class="card-header"><h2 class="card-title">All versions</h2></div>
                <div class="overflow-x-auto">
                    <table class="table-classic">
                        <thead><tr><th>Version</th><th>Date</th><th class="text-right">Total</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($revisions as $revision)
                            <tr>
                                <td><a href="{{ route('sales.quotations.show', $revision) }}" class="font-mono text-xs font-semibold text-brand-600 hover:underline">{{ $revision->number() }}</a></td>
                                <td>{{ $revision->quote_date->format('d M Y') }}</td>
                                <td class="text-right tabular-nums">₹{{ Money::format($revision->total) }}</td>
                                <td><span class="badge {{ $revision->statusTone() }}">{{ $revision->statusLabel() }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @module('sales_followups', 'show')
            @include('sales._followup-list', ['createQuery' => ['quotation' => $quotation->id]])
        @endmodule

        <a href="{{ route('sales.quotations.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline">← Back to quotations</a>
    </div>
@endsection
