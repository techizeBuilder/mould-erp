@extends('layouts.app')

@section('title', $order->exists ? 'Edit Sales Order' : 'New Sales Order')
@section('breadcrumb', 'Order Management / Sales Orders / '.($order->code ?? 'New'))

@use('App\Models\Quotation')
@use('App\Support\BuyerScore')

@php
    $number = fn ($v) => $v !== null && (float) $v != 0 ? (float) $v : null;
    $customerData = $customers->mapWithKeys(fn ($c) => [$c->id => ['address' => $c->deliveryAddress(), 'terms' => $c->payment_terms]]);
    $fromQuotation = old('quotation_id', $order->quotation_id);
@endphp

@section('content')
    <form method="POST" action="{{ $order->exists ? route('orders.sales.update', $order) : route('orders.sales.store') }}" class="max-w-5xl space-y-6" data-order-form>
        @csrf
        @if ($order->exists) @method('PUT') @endif
        @if ($fromQuotation)<input type="hidden" name="quotation_id" value="{{ $fromQuotation }}">@endif

        @if ($order->status === 'rejected')
            <div class="rounded border border-red-200 border-l-4 border-l-red-600 bg-red-50 px-4 py-3 text-sm text-red-800">
                Sent back by the approver: <strong>{{ $order->decision_note }}</strong>. Correct it, save, and submit it again.
            </div>
        @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Order</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <span class="form-label">Sales order no.</span>
                    <div class="form-input bg-slate-50 font-mono text-slate-600">{{ $order->code ?? $nextCode }}</div>
                </div>
                <div class="md:col-span-2">
                    <label for="customer_id" class="form-label">Customer <span class="text-red-600">*</span></label>
                    <select id="customer_id" name="customer_id" required class="form-input">
                        <option value="">— Select customer —</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_id', $order->customer_id) === $customer->id)>{{ $customer->code }} – {{ $customer->company_name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <x-form.input name="order_date" label="Order date" type="date" :value="$order->order_date?->format('Y-m-d')" required />
                <x-form.input name="required_by" label="Required by" type="date" :value="$order->required_by?->format('Y-m-d')" />
                <div>
                    <label for="assigned_to" class="form-label">Handled by</label>
                    <select id="assigned_to" name="assigned_to" class="form-input">
                        <option value="">— Unassigned —</option>
                        @foreach ($assignees as $user)
                            <option value="{{ $user->id }}" @selected((int) old('assigned_to', $order->assigned_to) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <x-form.input name="po_number" label="Customer's PO no." :value="$order->po_number" maxlength="60" />
                <x-form.input name="po_date" label="PO date" type="date" :value="$order->po_date?->format('Y-m-d')" />
                <x-form.select name="payment_terms" label="Payment terms" :options="BuyerScore::options('payment_terms')" :value="$order->payment_terms" placeholder="— Select —" />

                <div class="md:col-span-2">
                    <label for="shipping_address" class="form-label">Ship to</label>
                    <textarea id="shipping_address" name="shipping_address" rows="2" maxlength="1000" class="form-input" placeholder="Fills in from Customer Master">{{ old('shipping_address', $order->shipping_address) }}</textarea>
                    @error('shipping_address') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="space-y-5">
                    <x-form.select name="price_basis" label="Price basis" :options="Quotation::PRICE_BASES" :value="$order->price_basis" required />
                    <x-form.input name="packing" label="Packing" :value="$order->packing" maxlength="150" placeholder="e.g. Box of 100" />
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Products ordered</h2>
                    <p class="text-xs text-slate-500">From Product Master, so each line carries an exact serial number (neck and weight included).</p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-line-add><x-icon name="plus" class="h-4 w-4" /> Add product</button>
            </div>
            <div class="space-y-4 p-5" data-lines>
                @error('items') <p class="form-error">{{ $message }}</p> @enderror
                @foreach ($rows as $i => $row)
                    @include('orders.sales._line', ['i' => $i, 'row' => $row])
                @endforeach
            </div>
            <template data-line-template>
                @include('orders.sales._line', ['i' => '__I__', 'row' => []])
            </template>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Totals</h2></div>
            <div class="grid gap-6 p-5 md:grid-cols-2">
                <div class="space-y-4">
                    <x-form.input name="discount_percent" label="Discount on goods (%)" type="number" min="0" max="100" step="0.01" :value="$number($order->discount_percent)" />
                    <x-form.input name="freight_amount" label="Freight (₹)" type="number" min="0" step="0.01" :value="$number($order->freight_amount)" />
                    <x-form.input name="gst_percent" label="GST (%)" type="number" min="0" max="100" step="0.01" :value="$order->gst_percent !== null ? (float) $order->gst_percent : 18" required />
                </div>
                <dl class="space-y-2 self-end text-sm">
                    @foreach (['goods' => 'Goods', 'discount' => 'Discount', 'mould' => 'Mould charges', 'freight' => 'Freight', 'tax' => 'GST'] as $key => $label)
                        <div class="flex justify-between"><dt class="text-slate-500">{{ $label }}</dt><dd class="tabular-nums" data-total="{{ $key }}">₹0</dd></div>
                    @endforeach
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold text-navy-900"><dt>Total</dt><dd class="tabular-nums" data-total="grand">₹0</dd></div>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="p-5">
                <label for="remarks" class="form-label">Remarks</label>
                <textarea id="remarks" name="remarks" rows="2" maxlength="2000" class="form-input">{{ old('remarks', $order->remarks) }}</textarea>
                @error('remarks') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $order->exists ? route('orders.sales.show', $order) : route('orders.sales.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $order->exists ? 'Save changes' : 'Save as draft' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.querySelector('[data-order-form]');
        const box = form.querySelector('[data-lines]');
        const template = form.querySelector('[data-line-template]');
        const customers = @json($customerData);
        const inr = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const money = (v) => (v < 0 ? '−' : '') + '₹' + inr.format(Math.abs(v));
        const round = (v) => Math.round((v + Number.EPSILON) * 100) / 100;
        const num = (el) => parseFloat(el?.value) || 0;
        const put = (key, v) => { form.querySelector(`[data-total="${key}"]`).textContent = money(v); };
        let next = 1000;

        // Same arithmetic as App\Support\QuotationTotals.
        const refresh = () => {
            let goods = 0, mould = 0;
            box.querySelectorAll('[data-line]').forEach((line) => {
                const amount = round(num(line.querySelector('[data-line-qty]')) * num(line.querySelector('[data-line-rate]')));
                line.querySelector('[data-line-amount]').textContent = money(amount);
                goods += amount;
                mould += num(line.querySelector('[data-line-mould]'));
            });
            const discount = round(goods * num(document.getElementById('discount_percent')) / 100);
            const freight = num(document.getElementById('freight_amount'));
            const taxable = goods - discount + mould + freight;
            const tax = round(taxable * num(document.getElementById('gst_percent')) / 100);
            put('goods', goods); put('discount', -discount); put('mould', mould); put('freight', freight); put('tax', tax); put('grand', taxable + tax);
        };

        // Picking a customer fills what Customer Master knows about them.
        document.getElementById('customer_id').addEventListener('change', (e) => {
            const customer = customers[e.target.value];
            if (!customer) return;
            document.getElementById('shipping_address').value = customer.address || '';
            if (customer.terms) document.getElementById('payment_terms').value = customer.terms;
        });

        box.addEventListener('change', (e) => {
            if (!e.target.matches('[data-line-product]')) return;
            const unit = e.target.selectedOptions[0]?.dataset.unit;
            if (unit) e.target.closest('[data-line]').querySelector('[data-line-unit]').value = unit;
        });
        box.addEventListener('click', (e) => {
            const remove = e.target.closest('[data-line-remove]');
            if (!remove) return;
            if (box.querySelectorAll('[data-line]').length === 1) { alert('Keep at least one product.'); return; }
            remove.closest('[data-line]').remove();
            refresh();
        });
        form.querySelector('[data-line-add]').addEventListener('click', () => {
            const holder = document.createElement('div');
            holder.innerHTML = template.innerHTML.replaceAll('__I__', next++);
            box.appendChild(holder.firstElementChild);
            refresh();
        });
        form.addEventListener('input', refresh);
        refresh();
    })();
</script>
@endpush
