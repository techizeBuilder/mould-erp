{{-- Capacity entered as number + ml/L; posts {name}_value and {name}_unit. --}}
@props(['name', 'label', 'ml' => null, 'required' => false, 'placeholder' => null, 'hint' => null])
@php
    [$value, $unit] = \App\Support\Capacity::forInput($ml);
    $value = old("{$name}_value", $value);
    $unit = old("{$name}_unit", $unit);
@endphp
<div {{ $attributes }}>
    <label for="{{ $name }}_value" class="form-label">{{ $label }} @if ($required)<span class="text-red-600">*</span>@endif</label>
    <div class="flex">
        <input id="{{ $name }}_value" name="{{ $name }}_value" type="number" step="any" min="0" value="{{ $value }}"
               @required($required) placeholder="{{ $placeholder }}" class="form-input rounded-r-none">
        <select name="{{ $name }}_unit" aria-label="{{ $label }} unit" class="form-input w-20 rounded-l-none border-l-0 bg-slate-50">
            <option value="ml" @selected($unit === 'ml')>ml</option>
            <option value="l" @selected($unit === 'l')>L</option>
        </select>
    </div>
    @if ($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error("{$name}_value") <p class="form-error">{{ $message }}</p> @enderror
    @error("{$name}_unit") <p class="form-error">{{ $message }}</p> @enderror
</div>
