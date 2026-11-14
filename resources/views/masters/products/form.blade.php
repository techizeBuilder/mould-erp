@extends('layouts.app')

@section('title', $product->exists ? 'Edit Product' : 'Add Product')
@section('breadcrumb', 'Masters / Product Master / '.($product->exists ? $product->code : 'Add'))

@use('App\Support\Capacity')
@use('App\Models\Product')
@php
    // Each mould carries the specs a product inherits from it (see Product::MOULD_SPECS).
    $mouldData = $moulds->mapWithKeys(function ($m) {
        [$capValue, $capUnit] = Capacity::forInput($m->capacity_ml);

        return [$m->id => [
            'kind' => $m->mould_type,
            'specs' => array_filter([
                'capacity_value' => $m->capacity_ml !== null ? $capValue : null,
                'capacity_unit' => $m->capacity_ml !== null ? $capUnit : null,
                'shape' => $m->shape,
            ], fn ($v) => $v !== null),
            // Starting values only: the product can use a different neck or weight.
            'neck_id' => $m->neck_id,
            'neck_label' => $m->neck?->code,
            'weight' => $m->weight_grams !== null ? Capacity::trim($m->weight_grams) : null,
        ]];
    });
    $mouldId = (int) old('mould_id', $product->mould_id);
    $num = fn ($field) => old($field, $product->{$field} !== null ? Capacity::trim($product->{$field}) : null);
    $owner = old('owner_type', $product->owner_type ?? 'company');
@endphp

@section('content')
    @if ($source)
        <div class="mb-5 max-w-5xl rounded border border-sky-200 border-l-4 border-l-sky-600 bg-sky-50 px-4 py-3 text-sm text-sky-800">
            New variant based on <strong>{{ $source->code }} – {{ $source->name }}</strong>. Change what differs (neck, weight, colour, client…) and save — it gets its own serial number.
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('masters.products.update', $product) : route('masters.products.store') }}" class="max-w-5xl space-y-6" data-product-form>
        @csrf
        @if ($product->exists) @method('PUT') @endif

        {{-- Step 1: mould first, so its specs can be filled in below. --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><span class="mr-1 text-gold-600">1.</span> Mould</h2>
                    <p class="text-xs text-slate-500">Select the mould first — its volume and shape are locked, and its neck and weight fill in as a starting point you can change.</p>
                </div>
            </div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <label for="business_type" class="form-label">Business type <span class="text-red-600">*</span></label>
                    <select id="business_type" name="business_type" required class="form-input">
                        @foreach (Product::BUSINESS_TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('business_type', $product->business_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Trading = bought from a supplier and resold (no mould).</p>
                    @error('business_type') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2" data-mould-field>
                    <label for="mould_id" class="form-label">Mould <span class="text-red-600">*</span></label>
                    <select id="mould_id" name="mould_id" class="form-input">
                        <option value="">— Select the mould this product is made with —</option>
                        @foreach ($moulds->groupBy('mould_type') as $type => $group)
                            <optgroup label="{{ \App\Models\Mould::TYPES[$type] ?? 'Type not set' }}">
                                @foreach ($group as $m)
                                    <option value="{{ $m->id }}" @selected($mouldId === $m->id)>{{ $m->code }} – {{ $m->name }}{{ $m->status !== 'active' ? ' ('.$m->statusLabel().')' : '' }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">One mould per product. Bottles and jars use a blow mould; preforms use a preform mould.</p>
                    @error('mould_id') <p class="form-error">{{ $message }}</p> @enderror
                    @if ($moulds->isEmpty())<p class="mt-1 text-sm text-amber-700">No moulds yet — add them in Mould Master.</p>@endif
                </div>
                <p class="hidden rounded border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500 md:col-span-2" data-trading-note>
                    Trading products are bought from suppliers, so no mould is needed — enter the specification yourself.
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title"><span class="mr-1 text-gold-600">2.</span> Product details</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <span class="form-label">Product serial no.</span>
                    <div class="form-input bg-slate-50 font-mono text-slate-600">{{ $product->code ?? $nextCode }}</div>
                    @unless ($product->exists)<p class="mt-1 text-xs text-slate-500">Assigned automatically when saved.</p>@endunless
                </div>
                <div class="md:col-span-2">
                    <span class="form-label">Product name (system) <span class="text-red-600">*</span></span>
                    <div class="form-input bg-slate-50 font-semibold text-slate-800" data-product-name>{{ $product->name ?: '—' }}</div>
                    <p class="mt-1 text-xs text-slate-500">Generated automatically from the specification, colour and client, so each product is identified by its name.</p>
                </div>

                <div class="md:col-span-3">
                    <label for="bill_name" class="form-label">Name on bill <span class="text-red-600">*</span></label>
                    <input id="bill_name" name="bill_name" value="{{ old('bill_name', $product->bill_name) }}" required maxlength="255" class="form-input" placeholder="e.g. 1 Ltr PET Bottle Clear">
                    <p class="mt-1 text-xs text-slate-500">Printed on invoices and challans — write it the way the customer knows it.</p>
                    @error('bill_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <span class="form-label">Product owner <span class="text-red-600">*</span></span>
                    <div class="flex flex-wrap gap-4 pt-2">
                        @foreach (Product::OWNER_TYPES as $value => $label)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="owner_type" value="{{ $value }}" class="h-4 w-4 accent-brand-600" @checked($owner === $value)> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('owner_type') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div data-client-field>
                    <label for="client_name" class="form-label">Client name <span class="text-red-600">*</span></label>
                    <input id="client_name" name="client_name" value="{{ old('client_name', $product->client_name) }}" maxlength="150" class="form-input" placeholder="Client this product is made for">
                    @error('client_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="product_type" class="form-label">Product type <span class="text-red-600">*</span></label>
                    <select id="product_type" name="product_type" required class="form-input">
                        @foreach (Product::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('product_type', $product->product_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('product_type') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status" class="form-label">Status <span class="text-red-600">*</span></label>
                    <select id="status" name="status" required class="form-input">
                        @foreach (Product::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $product->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><span class="mr-1 text-gold-600">3.</span> Specification</h2>
                    <p class="text-xs text-slate-500" data-locked-note>Fields marked <x-icon name="lock" class="inline h-3 w-3" /> come from the mould and change only in Mould Master.</p>
                </div>
            </div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <x-capacity-input name="capacity" label="Bottle / jar volume" :ml="$product->capacity_ml" placeholder="e.g. 1" hint="Leave blank for preforms." data-spec="capacity" />
                <x-shape-select :value="$product->shape" data-spec="shape" />
                <x-neck-select class="md:col-span-2" :value="$product->neck_id" hint="Starts with the mould's neck. Pick another, or add a new one, if the client asks." />
                <div>
                    <label for="weight_grams" class="form-label">Weight (grams)</label>
                    <input id="weight_grams" name="weight_grams" type="number" step="0.01" min="0" value="{{ $num('weight_grams') }}" class="form-input" placeholder="e.g. 32">
                    <p class="mt-1 hidden text-xs text-slate-500" data-weight-default></p>
                    @error('weight_grams') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                @if ($product->exists)
                    <div class="hidden rounded border border-amber-200 border-l-4 border-l-amber-600 bg-amber-50 px-4 py-3 text-sm text-amber-800 md:col-span-3" data-new-serial
                         data-neck="{{ $product->neck_id }}" data-weight="{{ $product->weight_grams !== null ? (float) $product->weight_grams : '' }}">
                        The neck or weight is different, so saving creates a <strong>new product with a new serial number</strong>. {{ $product->code }} stays as it is.
                    </div>
                @endif

                <div>
                    <label for="material" class="form-label">Material</label>
                    <select id="material" name="material" class="form-input">
                        <option value="">— Select —</option>
                        @foreach (Product::MATERIALS as $material)
                            <option @selected(old('material', $product->material) === $material)>{{ $material }}</option>
                        @endforeach
                    </select>
                    @error('material') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="colour" class="form-label">Colour</label>
                    <input id="colour" name="colour" value="{{ old('colour', $product->colour) }}" maxlength="50" class="form-input" placeholder="e.g. Clear, Amber, White">
                    @error('colour') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="hsn_code" class="form-label">HSN code</label>
                    <input id="hsn_code" name="hsn_code" value="{{ old('hsn_code', $product->hsn_code) }}" maxlength="20" class="form-input" placeholder="For GST invoices, e.g. 3923">
                    @error('hsn_code') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="unit" class="form-label">Unit <span class="text-red-600">*</span></label>
                    <select id="unit" name="unit" required class="form-input">
                        @foreach (Product::UNITS as $value => $label)
                            <option value="{{ $value }}" @selected(old('unit', $product->unit) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('unit') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div data-pack-field>
                    <label for="pieces_per_pack" class="form-label">Pieces per pack / carton <span class="text-red-600">*</span></label>
                    <input id="pieces_per_pack" name="pieces_per_pack" type="number" min="1" value="{{ old('pieces_per_pack', $product->pieces_per_pack) }}" class="form-input" placeholder="e.g. 100">
                    @error('pieces_per_pack') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="hidden md:block"></div>

                <x-image-input label="Product image" :url="$product->imageUrl()" class="md:col-span-2" />
                <div class="md:col-span-3">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3" maxlength="2000" class="form-input">{{ old('remarks', $product->remarks) }}</textarea>
                    @error('remarks') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $product->exists ? route('masters.products.show', $product) : route('masters.products.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $product->exists ? 'Save changes' : 'Create product' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.querySelector('[data-product-form]');
        const el = form.elements;
        const moulds = @json($mouldData);
        const allowedTypes = @json(Product::MOULD_TYPES_FOR);
        const typeLabels = @json(Product::NAME_TYPES);
        const newShape = @json(\App\Models\Shape::NEW);
        const lockIcon = ' <svg class="lock-icon inline h-3 w-3 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>';

        // Spec wrapper → the form fields it holds (capacity has a value + a unit).
        const specFields = {
            capacity: ['capacity_value', 'capacity_unit'],
            shape: ['shape'],
        };

        const setLocked = (wrapper, locked) => {
            wrapper.querySelectorAll('input, select').forEach((f) => {
                if (f.name === 'new_shape') return;
                // Selects can't be read-only, so locked ones are disabled; the server applies the mould's values anyway.
                if (f.tagName === 'SELECT') f.disabled = locked; else f.readOnly = locked;
                f.classList.toggle('bg-slate-100', locked);
                f.classList.toggle('text-slate-600', locked);
            });
            const label = wrapper.querySelector('.form-label');
            label.querySelector('.lock-icon')?.remove();
            if (locked) label.insertAdjacentHTML('beforeend', lockIcon);
        };

        // The mould's neck and weight are only a starting point, so they fill in when the mould is picked.
        const applyMouldDefaults = () => {
            const mould = el.business_type.value === 'trading' ? null : moulds[el.mould_id.value];
            if (!mould) return;
            if (mould.neck_id) el.neck_id.value = mould.neck_id;
            el.neck_id.dispatchEvent(new Event('change', { bubbles: true }));
            if (mould.weight !== null) el.weight_grams.value = mould.weight;
        };

        const showMouldDefaults = () => {
            const mould = el.business_type.value === 'trading' ? null : moulds[el.mould_id.value];
            const note = form.querySelector('[data-weight-default]');
            note.classList.toggle('hidden', !mould || mould.weight === null);
            note.textContent = mould && mould.weight !== null ? `Mould default: ${mould.weight} g` : '';
        };

        const flagNewSerial = () => {
            const note = form.querySelector('[data-new-serial]');
            if (!note) return;
            const number = (v) => (v === '' ? null : parseFloat(v));
            const changed = el.neck_id.value !== note.dataset.neck || number(el.weight_grams.value) !== number(note.dataset.weight);
            note.classList.toggle('hidden', !changed);
        };

        const applyMould = () => {
            const trading = el.business_type.value === 'trading';
            const mould = trading ? null : moulds[el.mould_id.value];

            form.querySelector('[data-mould-field]').classList.toggle('hidden', trading);
            form.querySelector('[data-trading-note]').classList.toggle('hidden', !trading);
            el.mould_id.required = !trading;

            // Product types the mould can make; jump to the first allowed one if needed.
            [...el.product_type.options].forEach((o) => {
                o.disabled = !!mould && !(allowedTypes[o.value] || []).includes(mould.kind);
            });
            if (el.product_type.selectedOptions[0]?.disabled) {
                el.product_type.value = [...el.product_type.options].find((o) => !o.disabled)?.value;
            }

            let anyLocked = false;
            Object.entries(specFields).forEach(([spec, names]) => {
                const wrapper = form.querySelector(`[data-spec="${spec}"]`);
                const locked = !!mould && names.some((n) => mould.specs[n] !== undefined);
                // Specs the mould leaves blank (or with no mould) keep their values and stay editable.
                if (locked) {
                    names.forEach((n) => { if (mould.specs[n] !== undefined) el[n].value = mould.specs[n]; });
                    if (spec === 'shape') el.shape.dispatchEvent(new Event('change'));
                }
                setLocked(wrapper, locked);
                anyLocked ||= locked;
            });
            form.querySelector('[data-locked-note]').classList.toggle('invisible', !anyLocked);
        };

        const syncOwnerAndUnit = () => {
            const client = form.querySelector('input[name="owner_type"]:checked')?.value === 'client';
            const clientField = form.querySelector('[data-client-field]');
            clientField.classList.toggle('invisible', !client);
            el.client_name.required = client;

            const isPack = el.unit.value !== 'pcs';
            const pack = form.querySelector('[data-pack-field]');
            pack.classList.toggle('hidden', !isPack);
            el.pieces_per_pack.required = isPack;
        };

        // Live preview of the generated name — mirrors Product::composeName().
        const trim = (n) => String(Math.round(parseFloat(n) * 100) / 100);
        const val = (name) => (el[name]?.value || '').trim();
        const renderName = () => {
            let ml = parseFloat(val('capacity_value'));
            ml = isNaN(ml) ? null : (val('capacity_unit') === 'l' ? ml * 1000 : ml);
            const volume = ml ? (ml >= 1000 ? trim(ml / 1000) + ' L' : trim(ml) + ' ml') : '';
            const shape = el.shape.value === newShape
                ? val('new_shape').replace(/\s+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
                : (el.shape.value ? el.shape.selectedOptions[0].text : '');
            const neck = window.selectedNeck(), size = neck.size, weight = val('weight_grams');
            const client = form.querySelector('input[name="owner_type"]:checked')?.value === 'client' ? val('client_name') : '';

            form.querySelector('[data-product-name]').textContent = [
                [volume, shape, val('material'), typeLabels[val('product_type')] || 'Product'].filter(Boolean).join(' '),
                [size && parseFloat(size) ? trim(size) + ' mm' : '', neck.type].filter(Boolean).join(' '),
                weight && parseFloat(weight) ? trim(weight) + ' g' : '',
                val('colour'),
                client ? 'Client: ' + client : '',
            ].filter(Boolean).join(' – ');
        };

        // Disabled selects aren't posted, and the volume's unit is required with its value. The server applies the mould's values anyway.
        form.addEventListener('submit', () => form.querySelectorAll('[data-spec] select:disabled').forEach((f) => { f.disabled = false; }));
        el.mould_id.addEventListener('change', applyMould);
        el.mould_id.addEventListener('change', applyMouldDefaults);
        el.business_type.addEventListener('change', applyMould);
        form.addEventListener('change', showMouldDefaults);
        form.addEventListener('change', flagNewSerial);
        form.addEventListener('input', flagNewSerial);
        form.addEventListener('change', syncOwnerAndUnit);
        form.addEventListener('input', renderName);
        form.addEventListener('change', renderName);
        applyMould();
        showMouldDefaults();
        flagNewSerial();
        syncOwnerAndUnit();
        renderName();
    })();
</script>
@endpush
