@extends('layouts.app')

@section('title', $machine->exists ? 'Edit Machine' : 'Add Machine')
@section('breadcrumb', 'Masters / Machine Master / '.($machine->exists ? $machine->code : 'Add'))

@php
    // Which mould types each machine type can run.
    $mouldKindsFor = ['preform' => ['preform'], 'blow' => ['blow'], 'other' => ['preform', 'blow']];
    $mouldOptions = $moulds->map(fn ($m) => [
        'id' => $m->id,
        'label' => "{$m->code} – {$m->name}",
        'meta' => collect([\App\Support\Capacity::format($m->capacity_ml), $m->neck_size_mm ? \App\Support\Capacity::trim($m->neck_size_mm).' mm neck' : null])->filter()->join(' · '),
        'kind' => $m->mould_type,
        'badge' => $m->typeLabel(),
        'badgeTone' => $m->mould_type === 'preform' ? 'bg-sky-50 text-sky-700' : 'bg-violet-50 text-violet-700',
    ])->all();
@endphp

@section('content')
    <form method="POST" action="{{ $machine->exists ? route('masters.machines.update', $machine) : route('masters.machines.store') }}" class="max-w-5xl space-y-6">
        @csrf
        @if ($machine->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Machine details</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <span class="form-label">Machine serial no.</span>
                    <div class="form-input bg-slate-50 font-mono text-slate-600">{{ $machine->code ?? $nextCode }}</div>
                    @unless ($machine->exists)<p class="mt-1 text-xs text-slate-500">Assigned automatically when saved.</p>@endunless
                </div>
                <div class="md:col-span-2">
                    <span class="form-label">Machine name <span class="text-red-600">*</span></span>
                    <div class="form-input bg-slate-50 font-semibold text-slate-800" data-machine-name>{{ $machine->name ?: '—' }}</div>
                    <p class="mt-1 text-xs text-slate-500">Generated automatically from type, tonnage, brand, model, cavities and bottle size, so each machine is identified by its name. Identical specs get a #2, #3… suffix.</p>
                </div>

                <div>
                    <label for="machine_type" class="form-label">Machine type <span class="text-red-600">*</span></label>
                    <select id="machine_type" name="machine_type" required class="form-input">
                        <option value="">— Select —</option>
                        @foreach (\App\Models\Machine::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('machine_type', $machine->machine_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('machine_type') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="make" class="form-label">Brand / manufacturer</label>
                    <input id="make" name="make" value="{{ old('make', $machine->make) }}" maxlength="100" class="form-input">
                    @error('make') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="model_no" class="form-label">Model number</label>
                    <input id="model_no" name="model_no" value="{{ old('model_no', $machine->model_no) }}" maxlength="100" class="form-input">
                    @error('model_no') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="tonnage" class="form-label">Machine tonnage</label>
                    <div class="flex">
                        <input id="tonnage" name="tonnage" type="number" step="0.01" min="0" value="{{ old('tonnage', $machine->tonnage !== null ? \App\Support\Capacity::trim($machine->tonnage) : null) }}" class="form-input rounded-r-none" placeholder="e.g. 250">
                        <span class="form-input w-20 rounded-l-none border-l-0 bg-slate-50 text-center text-slate-600">tonnes</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Clamping force.</p>
                    @error('tonnage') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="cavities" class="form-label">No. of cavities</label>
                    <input id="cavities" name="cavities" type="number" min="1" value="{{ old('cavities', $machine->cavities) }}" class="form-input" placeholder="If applicable">
                    @error('cavities') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status" class="form-label">Status <span class="text-red-600">*</span></label>
                    <select id="status" name="status" required class="form-input">
                        @foreach (\App\Models\Machine::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $machine->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <x-capacity-input name="min_size" label="Smallest bottle size" :ml="$machine->min_capacity_ml" placeholder="e.g. 20" />
                <x-capacity-input name="max_size" label="Largest bottle size" :ml="$machine->max_capacity_ml" placeholder="e.g. 2" />
                <div>
                    <label for="location" class="form-label">Location</label>
                    <input id="location" name="location" value="{{ old('location', $machine->location) }}" maxlength="100" class="form-input" placeholder="e.g. Shop floor – Bay 2">
                    @error('location') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-3">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3" maxlength="2000" class="form-input">{{ old('remarks', $machine->remarks) }}</textarea>
                    @error('remarks') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Compatible moulds</h2>
                    <p class="text-xs text-slate-500">Preform machines run preform moulds; blow moulding machines run blow moulds. The list follows the machine type.</p>
                </div>
            </div>
            <div class="p-5">
                <x-checklist name="mould_ids[]" :options="$mouldOptions" :selected="$selected"
                             filter-by="machine_type" :allowed="$mouldKindsFor" empty="No moulds yet — add them in Mould Master." />
                @error('mould_ids') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $machine->exists ? route('masters.machines.show', $machine) : route('masters.machines.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $machine->exists ? 'Save changes' : 'Create machine' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // Live preview of the generated name — mirrors Machine::composeName().
    (() => {
        const out = document.querySelector('[data-machine-name]');
        const form = out.closest('form');
        const val = (name) => (form.elements[name]?.value || '').trim();
        const trim = (n) => String(Math.round(parseFloat(n) * 100) / 100);
        const types = @json(\App\Models\Machine::NAME_TYPES);
        const size = (prefix) => {
            const v = parseFloat(val(prefix + '_value'));
            return isNaN(v) ? null : (val(prefix + '_unit') === 'l' ? v * 1000 : v);
        };
        const fmt = (ml) => (ml >= 1000 ? trim(ml / 1000) + ' L' : trim(ml) + ' ml');

        const render = () => {
            const min = size('min_size'), max = size('max_size');
            const range = min !== null && max !== null ? fmt(min) + ' – ' + fmt(max)
                : min !== null ? 'from ' + fmt(min) : max !== null ? 'up to ' + fmt(max) : '';
            const tonnage = val('tonnage'), cav = val('cavities');

            out.textContent = [
                types[val('machine_type')] || 'Machine',
                tonnage && parseFloat(tonnage) ? trim(tonnage) + ' T' : '',
                [val('make'), val('model_no')].filter(Boolean).join(' '),
                cav && parseInt(cav) ? parseInt(cav) + ' Cav' : '',
                range,
            ].filter(Boolean).join(' – ');
        };

        form.addEventListener('input', render);
        form.addEventListener('change', render);
        render();
    })();
</script>
@endpush
