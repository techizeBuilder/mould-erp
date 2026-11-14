@extends('layouts.app')

@section('title', $neck->exists ? 'Edit Neck' : 'Add Neck')
@section('breadcrumb', 'Masters / Neck Master / '.($neck->exists ? $neck->code : 'Add'))

@use('App\Models\Mould')
@use('App\Models\Neck')
@use('App\Support\Capacity')

@php
    $mm = fn ($field) => old($field, $neck->{$field} !== null ? Capacity::trim($neck->{$field}) : null);
    $shown = fn ($field) => $neck->{$field} !== null && $neck->{$field} !== '' ? (is_numeric($neck->{$field}) ? Capacity::trim($neck->{$field}).' mm' : $neck->{$field}) : '—';
@endphp

@section('content')
    <form method="POST" action="{{ $neck->exists ? route('masters.necks.update', $neck) : route('masters.necks.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($neck->exists) @method('PUT') @endif

        @if ($locked)
            <div class="rounded border border-sky-200 border-l-4 border-l-sky-600 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                This neck is in use, so its parameters can't change. For a different neck, <a href="{{ route('masters.necks.create') }}" class="font-semibold underline">add a new one</a> with its own serial number.
            </div>
        @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Neck details</h2></div>
            <div class="grid gap-5 p-5 md:grid-cols-3">
                <div>
                    <span class="form-label">Neck serial no.</span>
                    <div class="form-input bg-slate-50 font-mono text-slate-600">{{ $neck->code ?? $nextCode }}</div>
                    @unless ($neck->exists)<p class="mt-1 text-xs text-slate-500">Assigned automatically when saved.</p>@endunless
                </div>
                <div class="md:col-span-2">
                    <span class="form-label">Neck name</span>
                    <div class="form-input bg-slate-50 font-semibold text-slate-800" data-neck-name>{{ $neck->name ?: '—' }}</div>
                    <p class="mt-1 text-xs text-slate-500">Generated automatically from the parameters below.</p>
                </div>

                @if ($locked)
                    @foreach (['neck_size_mm' => 'Neck size', 'neck_type' => 'Neck type', 'neck_height_mm' => 'Neck height', 'inner_dia_mm' => 'Inner diameter', 'outer_dia_mm' => 'Outer (thread) diameter'] as $field => $label)
                        <div>
                            <span class="form-label">{{ $label }} <x-icon name="lock" class="inline h-3 w-3 text-slate-400" /></span>
                            <div class="form-input bg-slate-100 text-slate-600">{{ $shown($field) }}</div>
                        </div>
                    @endforeach
                @else
                    <x-form.input name="neck_size_mm" label="Neck size (mm)" type="number" step="0.01" min="0" list="neck-sizes" :value="$mm('neck_size_mm')" required placeholder="e.g. 28" data-neck-spec />
                    <datalist id="neck-sizes">@foreach (Mould::COMMON_NECK_SIZES as $size)<option value="{{ $size }}">@endforeach</datalist>
                    <x-form.input name="neck_type" label="Neck type" list="neck-types" :value="$neck->neck_type" maxlength="50" placeholder="e.g. PCO 1881" data-neck-spec />
                    <datalist id="neck-types">@foreach (Mould::COMMON_NECK_TYPES as $type)<option value="{{ $type }}">@endforeach</datalist>
                    <x-form.input name="neck_height_mm" label="Neck height (mm)" type="number" step="0.01" min="0" :value="$mm('neck_height_mm')" placeholder="e.g. 21" data-neck-spec />
                    <x-form.input name="inner_dia_mm" label="Inner diameter (mm)" type="number" step="0.01" min="0" :value="$mm('inner_dia_mm')" placeholder="e.g. 21.7" data-neck-spec />
                    <x-form.input name="outer_dia_mm" label="Outer / thread diameter (mm)" type="number" step="0.01" min="0" :value="$mm('outer_dia_mm')" placeholder="e.g. 27.4" data-neck-spec />
                @endif

                <x-form.select name="status" label="Status" :options="Neck::STATUSES" :value="$neck->status" required hint="Inactive necks aren't offered for new moulds or products." />
                <div class="md:col-span-3">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" maxlength="2000" class="form-input">{{ old('remarks', $neck->remarks) }}</textarea>
                    @error('remarks') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $neck->exists ? route('masters.necks.show', $neck) : route('masters.necks.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">{{ $neck->exists ? 'Save changes' : 'Add neck' }}</button>
        </div>
    </form>
@endsection

@unless ($locked)
    @push('scripts')
    <script>
        // Live preview of the generated name — mirrors Neck::composeName().
        (() => {
            const value = (id) => document.getElementById(id).value.trim();
            const mm = (id) => (parseFloat(value(id)) > 0 ? String(Math.round(parseFloat(value(id)) * 100) / 100) + ' mm' : '');
            const render = () => {
                document.querySelector('[data-neck-name]').textContent = [
                    [mm('neck_size_mm'), value('neck_type')].filter(Boolean).join(' '),
                    mm('neck_height_mm') && 'H ' + mm('neck_height_mm'),
                    mm('inner_dia_mm') && 'ID ' + mm('inner_dia_mm'),
                    mm('outer_dia_mm') && 'OD ' + mm('outer_dia_mm'),
                ].filter(Boolean).join(' – ') || '—';
            };
            document.querySelectorAll('[data-neck-spec]').forEach((input) => input.addEventListener('input', render));
            render();
        })();
    </script>
    @endpush
@endunless
