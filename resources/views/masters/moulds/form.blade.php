@extends('layouts.app')

@section('title', $mould->exists ? 'Edit Mould' : 'Add Mould')
@section('breadcrumb', 'Masters / Mould Master / '.($mould->exists ? $mould->code : 'Add'))

@php
    $machineOptions = $machines->map(fn ($m) => [
        'id' => $m->id,
        'label' => "{$m->code} – {$m->name}",
        'meta' => collect([$m->make, \App\Support\Capacity::range($m->min_capacity_ml, $m->max_capacity_ml)])->filter()->join(' · '),
        'kind' => $m->machine_type,
        'badge' => $m->typeLabel(),
        'badgeTone' => match ($m->machine_type) { 'preform' => 'bg-sky-50 text-sky-700', 'blow' => 'bg-violet-50 text-violet-700', default => 'bg-slate-100 text-slate-600' },
    ])->all();
@endphp

@section('content')
    <form method="POST" enctype="multipart/form-data" action="{{ $mould->exists ? route('masters.moulds.update', $mould) : route('masters.moulds.store') }}" class="max-w-5xl space-y-6">
        @csrf
        @if ($mould->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Mould details</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <span class="form-label">Mould serial no.</span>
                    <div class="form-input bg-slate-50 font-mono text-slate-600">{{ $mould->code ?? $nextCode }}</div>
                    @unless ($mould->exists)<p class="mt-1 text-xs text-slate-500">Assigned automatically when saved.</p>@endunless
                </div>
                <div class="md:col-span-2">
                    <span class="form-label">Mould name <span class="text-red-600">*</span></span>
                    <div class="form-input bg-slate-50 font-semibold text-slate-800" data-mould-name>{{ $mould->name ?: '—' }}</div>
                    <p class="mt-1 text-xs text-slate-500">Generated automatically from type, volume, shape, neck, weight and cavities, so each mould is identified by its name. Identical specs get a #2, #3… suffix.</p>
                </div>

                <div>
                    <label for="mould_type" class="form-label">Mould type <span class="text-red-600">*</span></label>
                    <select id="mould_type" name="mould_type" required class="form-input">
                        <option value="">— Select —</option>
                        @foreach (\App\Models\Mould::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('mould_type', $mould->mould_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Preform mould makes the preform; blow mould gives the final bottle/jar shape.</p>
                    @error('mould_type') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <x-shape-select label="Bottle / jar shape" :value="$mould->shape" />
                <x-capacity-input name="capacity" label="Bottle / jar volume" :ml="$mould->capacity_ml" placeholder="e.g. 500" />

                <x-neck-select class="md:col-span-2" label="Default neck" :value="$mould->neck_id" hint="From Neck Master. Products start with this neck, and can use another one if a client asks." />
                <div>
                    <label for="weight_grams" class="form-label">Default weight (grams)</label>
                    <input id="weight_grams" name="weight_grams" type="number" step="0.01" min="0" value="{{ old('weight_grams', $mould->weight_grams !== null ? \App\Support\Capacity::trim($mould->weight_grams) : null) }}" class="form-input" placeholder="e.g. 18">
                    <p class="mt-1 text-xs text-slate-500">Gram weight products start with. Changing it doesn't alter existing products.</p>
                    @error('weight_grams') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="cavities" class="form-label">No. of cavities <span class="text-red-600">*</span></label>
                    <input id="cavities" name="cavities" type="number" min="1" max="1000" value="{{ old('cavities', $mould->cavities) }}" required class="form-input">
                    @error('cavities') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status" class="form-label">Status <span class="text-red-600">*</span></label>
                    <select id="status" name="status" required class="form-input">
                        @foreach (\App\Models\Mould::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $mould->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="location" class="form-label">Storage location</label>
                    <input id="location" name="location" value="{{ old('location', $mould->location) }}" maxlength="100" class="form-input" placeholder="e.g. Mould rack A-3">
                    <p class="mt-1 text-xs text-slate-500">Where the operator collects it from.</p>
                    @error('location') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <x-image-input label="Mould image" :url="$mould->imageUrl()" class="md:col-span-2" />

                <div class="md:col-span-3">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3" maxlength="2000" class="form-input">{{ old('remarks', $mould->remarks) }}</textarea>
                    @error('remarks') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Compatible machines</h2>
                    <p class="text-xs text-slate-500">Machines this mould can be fitted on. Only machines that suit the selected mould type are listed.</p>
                </div>
            </div>
            <div class="p-5">
                <x-checklist name="machine_ids[]" :options="$machineOptions" :selected="$selected"
                             filter-by="mould_type" :allowed="\App\Models\Mould::MACHINE_TYPES_FOR" empty="No machines yet — add them in Machine Master." />
                @error('machine_ids') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $mould->exists ? route('masters.moulds.show', $mould) : route('masters.moulds.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $mould->exists ? 'Save changes' : 'Create mould' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // Live preview of the generated name — mirrors Mould::composeName().
    (() => {
        const form = document.querySelector('[data-mould-name]').closest('form');
        const out = form.querySelector('[data-mould-name]');
        const val = (name) => (form.elements[name]?.disabled ? '' : (form.elements[name]?.value || '').trim());
        const trim = (n) => String(Math.round(parseFloat(n) * 100) / 100);
        const types = @json(\App\Models\Mould::TYPES);

        const render = () => {
            let ml = parseFloat(val('capacity_value'));
            ml = isNaN(ml) ? null : (val('capacity_unit') === 'l' ? ml * 1000 : ml);
            const volume = ml ? (ml >= 1000 ? trim(ml / 1000) + ' L' : trim(ml) + ' ml') : '';
            const shapeSel = form.elements.shape;
            const shape = shapeSel.value === @json(\App\Models\Shape::NEW)
                ? val('new_shape').replace(/\s+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
                : (shapeSel.value ? shapeSel.selectedOptions[0].text : '');
            const neck = window.selectedNeck(), size = neck.size, weight = val('weight_grams'), cav = val('cavities');

            const name = [
                [volume, shape, types[val('mould_type')] || 'Mould'].filter(Boolean).join(' '),
                [size && parseFloat(size) ? trim(size) + ' mm' : '', neck.type].filter(Boolean).join(' '),
                weight && parseFloat(weight) ? trim(weight) + ' g' : '',
                cav && parseInt(cav) ? parseInt(cav) + ' Cav' : '',
            ].filter(Boolean).join(' – ');
            out.textContent = name;
        };

        form.addEventListener('input', render);
        form.addEventListener('change', render);
        render();
    })();
</script>
@endpush
