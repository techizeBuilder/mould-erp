@extends('layouts.app')

@php
    $definition = \App\Models\Voucher::TYPES[$voucher->type];
    $simpleAmount = $voucher->exists && ! $voucher->taxable_amount ? (float) $voucher->amount : null;
@endphp

@section('title', ($voucher->exists ? 'Edit ' : 'New ').$definition['label'].' Voucher')
@section('breadcrumb', 'Accounts / Vouchers / '.($voucher->code ?? $definition['label']))

@use('App\Models\Voucher')

@section('content')
    <form method="POST" action="{{ $voucher->exists ? route('accounts.vouchers.update', $voucher) : route('accounts.vouchers.store') }}" class="max-w-4xl space-y-6" data-voucher-form>
        @csrf
        @if ($voucher->exists) @method('PUT') @endif
        <input type="hidden" name="type" value="{{ $voucher->type }}">

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">{{ $definition['label'] }}</h2>
                    <p class="text-xs text-slate-500">{{ $definition['hint'] }}</p>
                </div>
                <span class="font-mono text-sm font-semibold text-navy-700">{{ $voucher->code ?? $nextCode }}</span>
            </div>
            <div class="grid gap-5 p-5 md:grid-cols-2">
                <x-form.input name="voucher_date" label="Date" type="date" :value="$voucher->voucher_date?->format('Y-m-d')" required />
                <x-form.input name="reference" :label="in_array($voucher->type, ['sales', 'purchase'], true) ? 'Bill / invoice no.' : 'Reference (cheque / UTR no.)'" :value="$voucher->reference" maxlength="60" />

                @if ($voucher->type !== 'journal')
                    @include('accounts.vouchers._ledger-select', ['name' => 'main_ledger_id', 'label' => $definition['main'], 'options' => $mainLedgers, 'value' => $voucher->main_ledger_id])
                    @include('accounts.vouchers._ledger-select', ['name' => 'counter_ledger_id', 'label' => $definition['counter'], 'options' => $counterLedgers, 'value' => $voucher->counter_ledger_id])
                @endif

                @if (in_array($voucher->type, ['receipt', 'payment', 'contra'], true))
                    <x-form.input name="amount" label="Amount (₹)" type="number" min="0.01" step="0.01" :value="$simpleAmount" required />
                @elseif (in_array($voucher->type, ['sales', 'purchase'], true))
                    <x-form.input name="taxable_amount" label="Amount before GST (₹)" type="number" min="0.01" step="0.01" :value="$voucher->taxable_amount !== null ? (float) $voucher->taxable_amount : null" required />
                    <div class="grid grid-cols-2 gap-5">
                        <x-form.select name="gst_rate" label="GST rate" :options="collect(Voucher::GST_RATES)->mapWithKeys(fn ($r) => [$r => $r.'%'])->all()" :value="$voucher->gst_rate !== null ? (int) $voucher->gst_rate : 18" required />
                        <x-form.select name="is_interstate" label="Supply" :options="['0' => 'Within state (CGST + SGST)', '1' => 'Other state (IGST)']" :value="(int) $voucher->is_interstate" required />
                    </div>
                    <dl class="flex flex-wrap justify-end gap-x-8 gap-y-1 rounded bg-slate-50 px-4 py-3 text-sm md:col-span-2">
                        <div class="flex gap-2"><dt class="text-slate-500">GST</dt><dd class="tabular-nums" data-gst>₹0</dd></div>
                        <div class="flex gap-2 font-bold text-navy-900"><dt>Total</dt><dd class="tabular-nums" data-total>₹0</dd></div>
                    </dl>
                @endif
            </div>
        </div>

        @if ($voucher->type === 'journal')
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Entries</h2>
                    <button type="button" class="btn btn-secondary btn-sm" data-line-add><x-icon name="plus" class="h-4 w-4" /> Add line</button>
                </div>
                <div class="space-y-3 p-5" data-lines>
                    @error('lines') <p class="form-error">{{ $message }}</p> @enderror
                    @foreach ($rows as $i => $row)
                        @include('accounts.vouchers._journal-line', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
                <template data-line-template>
                    @include('accounts.vouchers._journal-line', ['i' => '__I__', 'row' => []])
                </template>
                <dl class="flex flex-wrap justify-end gap-x-8 gap-y-1 border-t border-slate-200 px-5 py-3 text-sm">
                    <div class="flex gap-2"><dt class="text-slate-500">Debit</dt><dd class="font-semibold tabular-nums" data-sum-debit>₹0</dd></div>
                    <div class="flex gap-2"><dt class="text-slate-500">Credit</dt><dd class="font-semibold tabular-nums" data-sum-credit>₹0</dd></div>
                    <div class="flex gap-2"><dt class="text-slate-500">Difference</dt><dd class="font-semibold tabular-nums" data-sum-diff>₹0</dd></div>
                </dl>
            </div>
        @endif

        <div class="card">
            <div class="p-5">
                <label for="narration" class="form-label">Narration</label>
                <textarea id="narration" name="narration" rows="2" maxlength="500" class="form-input" placeholder="What this entry is for">{{ old('narration', $voucher->narration) }}</textarea>
                @error('narration') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $voucher->exists ? route('accounts.vouchers.show', $voucher) : route('accounts.vouchers.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $voucher->exists ? 'Save changes' : 'Save voucher' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.querySelector('[data-voucher-form]');
        const inr = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const money = (v) => '₹' + inr.format(v);
        const round = (v) => Math.round((v + Number.EPSILON) * 100) / 100;
        const num = (el) => parseFloat(el?.value) || 0;

        // Sales / purchase: same arithmetic as App\Support\VoucherLines::gst().
        const taxable = document.getElementById('taxable_amount');
        if (taxable) {
            const refresh = () => {
                const tax = round(round(num(taxable)) * num(document.getElementById('gst_rate')) / 100);
                form.querySelector('[data-gst]').textContent = money(tax);
                form.querySelector('[data-total]').textContent = money(round(num(taxable)) + tax);
            };
            form.addEventListener('input', refresh);
            refresh();
        }

        // Journal: repeating lines with a live debit / credit check.
        const box = form.querySelector('[data-lines]');
        if (box) {
            const template = form.querySelector('[data-line-template]');
            let next = 1000;
            const refresh = () => {
                let debit = 0, credit = 0;
                box.querySelectorAll('[data-line]').forEach((line) => {
                    debit += num(line.querySelector('[data-debit]'));
                    credit += num(line.querySelector('[data-credit]'));
                });
                const diff = round(debit - credit);
                form.querySelector('[data-sum-debit]').textContent = money(debit);
                form.querySelector('[data-sum-credit]').textContent = money(credit);
                const out = form.querySelector('[data-sum-diff]');
                out.textContent = money(Math.abs(diff));
                out.classList.toggle('text-red-600', diff !== 0);
            };
            form.querySelector('[data-line-add]').addEventListener('click', () => {
                const holder = document.createElement('div');
                holder.innerHTML = template.innerHTML.replaceAll('__I__', next++);
                box.appendChild(holder.firstElementChild);
            });
            box.addEventListener('click', (e) => {
                const remove = e.target.closest('[data-line-remove]');
                if (!remove) return;
                if (box.querySelectorAll('[data-line]').length <= 2) { alert('A journal needs at least two lines.'); return; }
                remove.closest('[data-line]').remove();
                refresh();
            });
            form.addEventListener('input', refresh);
            refresh();
        }
    })();
</script>
@endpush
