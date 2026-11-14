{{-- One product on a sales order. Expects $i ("__I__" in the template), $row, $products. --}}
@use('App\Models\Product')
@php
    $val = fn ($field, $default = null) => $row[$field] ?? $default;
    $err = fn ($field) => $errors->first("items.$i.$field");
@endphp
<div class="rounded border border-slate-200 bg-slate-50/50 p-4" data-line>
    @if ($val('custom_note'))
        <p class="mb-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            On the quotation as a custom spec: <strong>{{ $val('custom_note') }}</strong>. Pick its product, or add it to Product Master first so it has a serial number.
        </p>
    @endif
    <div class="grid gap-4 md:grid-cols-12">
        <div class="md:col-span-11">
            <label class="form-label">Product <span class="text-red-600">*</span></label>
            <select name="items[{{ $i }}][product_id]" class="form-input" data-line-product>
                <option value="">— Select from Product Master —</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" data-unit="{{ $product->unit }}" @selected((int) $val('product_id') === $product->id)>{{ $product->code }} – {{ $product->name }}</option>
                @endforeach
            </select>
            @if ($err('product_id'))<p class="form-error">{{ $err('product_id') }}</p>@endif
        </div>
        <div class="flex items-end justify-end md:col-span-1">
            <button type="button" class="btn btn-secondary btn-sm text-red-600" data-line-remove title="Remove this product" aria-label="Remove this product"><x-icon name="trash" class="h-4 w-4" /></button>
        </div>
    </div>
    <div class="mt-4 grid gap-4 md:grid-cols-5">
        <div>
            <label class="form-label">Quantity <span class="text-red-600">*</span></label>
            <input name="items[{{ $i }}][quantity]" type="number" min="1" step="1" value="{{ $val('quantity') }}" class="form-input" data-line-qty>
            @if ($err('quantity'))<p class="form-error">{{ $err('quantity') }}</p>@endif
        </div>
        <div>
            <label class="form-label">Unit <span class="text-red-600">*</span></label>
            <select name="items[{{ $i }}][unit]" class="form-input" data-line-unit>
                @foreach (Product::UNITS as $value => $label)
                    <option value="{{ $value }}" @selected($val('unit', 'pcs') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($err('unit'))<p class="form-error">{{ $err('unit') }}</p>@endif
        </div>
        <div>
            <label class="form-label">Rate (₹ / unit) <span class="text-red-600">*</span></label>
            <input name="items[{{ $i }}][rate]" type="number" min="0" step="0.001" value="{{ $val('rate') }}" class="form-input" data-line-rate>
            @if ($err('rate'))<p class="form-error">{{ $err('rate') }}</p>@endif
        </div>
        <div>
            <label class="form-label">Mould charge (₹)</label>
            <input name="items[{{ $i }}][mould_charge]" type="number" min="0" step="0.01" value="{{ $val('mould_charge') }}" class="form-input" placeholder="One-time, if any" data-line-mould>
            @if ($err('mould_charge'))<p class="form-error">{{ $err('mould_charge') }}</p>@endif
        </div>
        <div>
            <span class="form-label">Amount</span>
            <div class="form-input bg-slate-100 text-right tabular-nums" data-line-amount>₹0</div>
        </div>
    </div>
</div>
