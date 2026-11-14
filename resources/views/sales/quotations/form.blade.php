@extends('layouts.app')

@section('title', $quotation->exists ? 'Edit Quotation' : 'New Quotation')
@section('breadcrumb', 'Sales & CRM / Quotations / '.($quotation->exists ? $quotation->number() : 'New'))

@use('App\Models\Quotation')
@use('App\Support\BuyerScore')

@php
    $buyerKind = old('buyer_kind', $quotation->lead_id || ! $quotation->customer_id ? 'lead' : 'customer');
    $number = fn ($v) => $v !== null && (float) $v != 0 ? (float) $v : null;
@endphp

@section('content')
    <form method="POST" action="{{ $quotation->exists ? route('sales.quotations.update', $quotation) : route('sales.quotations.store') }}" class="max-w-5xl space-y-6">
        @csrf
        @if ($quotation->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Buyer</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <span class="form-label">Quotation no.</span>
                    <div class="form-input bg-slate-50 font-mono text-slate-600">{{ $quotation->exists ? $quotation->number() : $nextCode }}</div>
                </div>
                <div>
                    <label for="buyer_kind" class="form-label">Quoting to <span class="text-red-600">*</span></label>
                    <select id="buyer_kind" name="buyer_kind" class="form-input">
                        <option value="lead" @selected($buyerKind === 'lead')>A lead</option>
                        <option value="customer" @selected($buyerKind === 'customer')>An existing customer</option>
                    </select>
                </div>
                <div data-buyer="lead">
                    <label for="lead_id" class="form-label">Lead <span class="text-red-600">*</span></label>
                    <select id="lead_id" name="lead_id" class="form-input">
                        <option value="">— Select lead —</option>
                        @foreach ($leads as $lead)
                            <option value="{{ $lead->id }}" @selected((int) old('lead_id', $quotation->lead_id) === $lead->id)>{{ $lead->code }} – {{ $lead->company_name }}</option>
                        @endforeach
                    </select>
                    @error('lead_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div data-buyer="customer">
                    <label for="customer_id" class="form-label">Customer <span class="text-red-600">*</span></label>
                    <select id="customer_id" name="customer_id" class="form-input">
                        <option value="">— Select customer —</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_id', $quotation->customer_id) === $customer->id)>{{ $customer->code }} – {{ $customer->company_name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        @include('sales._product-lines', ['mode' => 'quotation'])

        <div class="card">
            <div class="card-header"><h2 class="card-title">Commercial terms</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <x-form.input name="quote_date" label="Quotation date" type="date" :value="$quotation->quote_date?->format('Y-m-d')" required />
                <x-form.input name="valid_until" label="Valid until" type="date" :value="$quotation->valid_until?->format('Y-m-d')" required />
                <x-form.select name="price_basis" label="Price basis" :options="Quotation::PRICE_BASES" :value="$quotation->price_basis" required />

                <x-form.select name="payment_terms" label="Payment terms" :options="BuyerScore::options('payment_terms')" :value="$quotation->payment_terms" placeholder="— Select —" />
                <x-form.input name="delivery_days" label="Delivery time (days after order)" type="number" min="0" step="1" :value="$quotation->delivery_days" />
                <x-form.input name="mould_lead_days" label="New mould lead time (days)" type="number" min="0" step="1" :value="$quotation->mould_lead_days" hint="Only when a new mould has to be made." />

                <x-form.input name="packing" label="Packing" :value="$quotation->packing" maxlength="150" placeholder="e.g. Corrugated box, 100 pcs per box" />
                <x-form.select name="sample_status" label="Sample" :options="Quotation::SAMPLE_STATUSES" :value="$quotation->sample_status" required />
                <x-form.input name="sample_sent_on" label="Sample sent on" type="date" :value="$quotation->sample_sent_on?->format('Y-m-d')" />

                <div>
                    <label for="assigned_to" class="form-label">Handled by</label>
                    <select id="assigned_to" name="assigned_to" class="form-input">
                        <option value="">— Unassigned —</option>
                        @foreach ($assignees as $user)
                            <option value="{{ $user->id }}" @selected((int) old('assigned_to', $quotation->assigned_to) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="terms" class="form-label">Other terms (printed)</label>
                    <textarea id="terms" name="terms" rows="2" maxlength="2000" class="form-input">{{ old('terms', $quotation->terms) }}</textarea>
                    @error('terms') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-3">
                    <label for="remarks" class="form-label">Internal remarks (not printed)</label>
                    <textarea id="remarks" name="remarks" rows="2" maxlength="2000" class="form-input">{{ old('remarks', $quotation->remarks) }}</textarea>
                    @error('remarks') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Totals</h2></div>
            <div class="grid gap-6 p-5 md:grid-cols-2">
                <div class="space-y-4">
                    <x-form.input name="discount_percent" label="Discount on goods (%)" type="number" min="0" max="100" step="0.01" :value="$number($quotation->discount_percent)" data-total-input />
                    <x-form.input name="freight_amount" label="Freight (₹)" type="number" min="0" step="0.01" :value="$number($quotation->freight_amount)" data-total-input />
                    <x-form.input name="gst_percent" label="GST (%)" type="number" min="0" max="100" step="0.01" :value="$quotation->gst_percent !== null ? (float) $quotation->gst_percent : 18" required data-total-input />
                </div>
                <dl class="space-y-2 self-end text-sm">
                    @foreach (['goods' => 'Goods', 'discount' => 'Discount', 'mould' => 'Mould charges', 'freight' => 'Freight', 'tax' => 'GST'] as $key => $label)
                        <div class="flex justify-between"><dt class="text-slate-500">{{ $label }}</dt><dd class="tabular-nums" data-total="{{ $key }}">₹0</dd></div>
                    @endforeach
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold text-navy-900"><dt>Total</dt><dd class="tabular-nums" data-total="grand">₹0</dd></div>
                </dl>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $quotation->exists ? route('sales.quotations.show', $quotation) : route('sales.quotations.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $quotation->exists ? 'Save changes' : 'Save as draft' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        // Who the quotation is for.
        const kind = document.getElementById('buyer_kind');
        const syncBuyer = () => document.querySelectorAll('[data-buyer]').forEach((box) => {
            const on = box.dataset.buyer === kind.value;
            box.classList.toggle('hidden', !on);
            box.querySelector('select').required = on;
        });
        kind.addEventListener('change', syncBuyer);
        syncBuyer();

        // Live totals — same arithmetic as App\Support\QuotationTotals.
        const inr = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });
        const num = (el) => parseFloat(el?.value) || 0;
        const round = (v) => Math.round((v + Number.EPSILON) * 100) / 100;
        const put = (key, v) => { document.querySelector(`[data-total="${key}"]`).textContent = (v < 0 ? '−' : '') + '₹' + inr.format(Math.abs(v)); };

        const refresh = () => {
            let goods = 0, mould = 0;
            document.querySelectorAll('[data-lines] [data-line]').forEach((line) => {
                goods += round(num(line.querySelector('[data-line-qty]')) * num(line.querySelector('[data-line-rate]')));
                mould += num(line.querySelector('[data-line-mould]'));
            });
            const discount = round(goods * num(document.getElementById('discount_percent')) / 100);
            const freight = num(document.getElementById('freight_amount'));
            const taxable = goods - discount + mould + freight;
            const tax = round(taxable * num(document.getElementById('gst_percent')) / 100);
            put('goods', goods); put('discount', -discount); put('mould', mould); put('freight', freight); put('tax', tax); put('grand', taxable + tax);
        };
        document.querySelector('[data-lines]').closest('form').addEventListener('input', refresh);
        refresh();
    })();
</script>
@endpush
