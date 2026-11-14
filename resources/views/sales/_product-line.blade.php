{{--
    One product line on a lead or quotation form. $i is the row index ("__I__" in the template),
    $row its values, $mode "lead" or "quotation". Either a Product Master record is picked (specs
    come from it) or the spec section is filled in by hand.
--}}
@use('App\Models\LeadItem')
@use('App\Models\Product')
@php
    $name = fn ($field) => "items[$i][$field]";
    $val = fn ($field, $default = null) => $row[$field] ?? $default;
    $err = fn ($field) => $errors->first("items.$i.$field");
@endphp
<div class="rounded border border-slate-200 bg-slate-50/50 p-4" data-line>
    <div class="grid gap-4 md:grid-cols-12">
        <div class="md:col-span-11">
            <label class="form-label">Product</label>
            <select name="{{ $name('product_id') }}" class="form-input" data-line-product>
                <option value="">— Custom spec (not in Product Master) —</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected((int) $val('product_id') === $product->id)>{{ $product->code }} – {{ $product->name }}</option>
                @endforeach
            </select>
            @if ($err('product_id'))<p class="form-error">{{ $err('product_id') }}</p>@endif
        </div>
        <div class="flex items-end justify-end md:col-span-1">
            <button type="button" class="btn btn-secondary btn-sm text-red-600" data-line-remove title="Remove this product" aria-label="Remove this product"><x-icon name="trash" class="h-4 w-4" /></button>
        </div>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-4" data-line-spec>
        <div>
            <label class="form-label">Product type <span class="text-red-600">*</span></label>
            <select name="{{ $name('product_type') }}" class="form-input">
                <option value="">— Select —</option>
                @foreach (Product::TYPES as $value => $label)
                    <option value="{{ $value }}" @selected($val('product_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($err('product_type'))<p class="form-error">{{ $err('product_type') }}</p>@endif
        </div>
        <div>
            <label class="form-label">Volume <span class="text-red-600">*</span></label>
            <div class="flex">
                <input name="{{ $name('capacity_value') }}" type="number" step="any" min="0" value="{{ $val('capacity_value') }}" class="form-input rounded-r-none" placeholder="e.g. 500">
                <select name="{{ $name('capacity_unit') }}" class="form-input w-20 rounded-l-none border-l-0 bg-slate-50" aria-label="Volume unit">
                    <option value="ml" @selected($val('capacity_unit', 'ml') === 'ml')>ml</option>
                    <option value="l" @selected($val('capacity_unit') === 'l')>L</option>
                </select>
            </div>
            @if ($err('capacity_value'))<p class="form-error">{{ $err('capacity_value') }}</p>@endif
        </div>
        <div>
            <label class="form-label">Material <span class="text-red-600">*</span></label>
            <select name="{{ $name('material') }}" class="form-input">
                <option value="">— Select —</option>
                @foreach (Product::MATERIALS as $material)
                    <option @selected($val('material') === $material)>{{ $material }}</option>
                @endforeach
            </select>
            @if ($err('material'))<p class="form-error">{{ $err('material') }}</p>@endif
        </div>
        <div>
            <label class="form-label">Shape</label>
            <select name="{{ $name('shape') }}" class="form-input">
                <option value="">— Any —</option>
                @foreach ($shapes as $key => $shape)
                    <option value="{{ $key }}" @selected($val('shape') === $key)>{{ $shape }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Neck size (mm)</label>
            <input name="{{ $name('neck_size_mm') }}" type="number" step="any" min="0" value="{{ $val('neck_size_mm') }}" class="form-input" placeholder="e.g. 28">
            @if ($err('neck_size_mm'))<p class="form-error">{{ $err('neck_size_mm') }}</p>@endif
        </div>
        <div>
            <label class="form-label">Neck type</label>
            <input name="{{ $name('neck_type') }}" list="neck-types" value="{{ $val('neck_type') }}" maxlength="50" class="form-input" placeholder="e.g. PCO 1881">
        </div>
        <div>
            <label class="form-label">Gram weight (g)</label>
            <input name="{{ $name('weight_grams') }}" type="number" step="any" min="0" value="{{ $val('weight_grams') }}" class="form-input" placeholder="e.g. 18">
            @if ($err('weight_grams'))<p class="form-error">{{ $err('weight_grams') }}</p>@endif
        </div>
        <div>
            <label class="form-label">Colour</label>
            <input name="{{ $name('colour') }}" value="{{ $val('colour') }}" maxlength="50" class="form-input" placeholder="e.g. Clear, Amber">
        </div>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-3">
        <div>
            <label class="form-label">Cap / closure</label>
            <select name="{{ $name('closure') }}" class="form-input">
                @foreach (LeadItem::CLOSURES as $value => $label)
                    <option value="{{ $value }}" @selected($val('closure', 'none') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Printing / decoration</label>
            <select name="{{ $name('decoration') }}" class="form-input">
                @foreach (LeadItem::DECORATIONS as $value => $label)
                    <option value="{{ $value }}" @selected($val('decoration', 'plain') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">What will be filled</label>
            <input name="{{ $name('fill_product') }}" value="{{ $val('fill_product') }}" maxlength="100" class="form-input" placeholder="e.g. Hair oil, cough syrup">
        </div>
    </div>

    <div class="mt-4 grid gap-4 {{ $mode === 'lead' ? 'md:grid-cols-3' : 'md:grid-cols-5' }}">
        <div>
            <label class="form-label">Quantity <span class="text-red-600">*</span></label>
            <input name="{{ $name('quantity') }}" type="number" min="1" step="1" value="{{ $val('quantity') }}" class="form-input" data-line-qty>
            @if ($err('quantity'))<p class="form-error">{{ $err('quantity') }}</p>@endif
        </div>
        @if ($mode === 'lead')
            <div>
                <label class="form-label">Quantity is <span class="text-red-600">*</span></label>
                <select name="{{ $name('qty_basis') }}" class="form-input">
                    @foreach (LeadItem::QTY_BASES as $value => $label)
                        <option value="{{ $value }}" @selected($val('qty_basis', 'per_month') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Buyer's target price (₹ / pc)</label>
                <input name="{{ $name('target_price') }}" type="number" min="0" step="0.001" value="{{ $val('target_price') }}" class="form-input" placeholder="Optional">
                @if ($err('target_price'))<p class="form-error">{{ $err('target_price') }}</p>@endif
            </div>
        @else
            <div>
                <label class="form-label">Unit <span class="text-red-600">*</span></label>
                <select name="{{ $name('unit') }}" class="form-input">
                    @foreach (Product::UNITS as $value => $label)
                        <option value="{{ $value }}" @selected($val('unit', 'pcs') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Rate (₹ / unit) <span class="text-red-600">*</span></label>
                <input name="{{ $name('rate') }}" type="number" min="0" step="0.001" value="{{ $val('rate') }}" class="form-input" data-line-rate>
                @if ($err('rate'))<p class="form-error">{{ $err('rate') }}</p>@endif
            </div>
            <div>
                <label class="form-label">MOQ</label>
                <input name="{{ $name('moq') }}" type="number" min="1" step="1" value="{{ $val('moq') }}" class="form-input" placeholder="Minimum order">
                @if ($err('moq'))<p class="form-error">{{ $err('moq') }}</p>@endif
            </div>
            <div>
                <label class="form-label">Mould charge (₹)</label>
                <input name="{{ $name('mould_charge') }}" type="number" min="0" step="0.01" value="{{ $val('mould_charge') }}" class="form-input" placeholder="One-time, if any" data-line-mould>
                @if ($err('mould_charge'))<p class="form-error">{{ $err('mould_charge') }}</p>@endif
            </div>
        @endif
    </div>
</div>
