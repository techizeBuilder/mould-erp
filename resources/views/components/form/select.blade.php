{{-- Labelled select from value => label options, with old-value handling and its validation error. --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'hint' => null])
@php $current = (string) old($name, $value); @endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="form-label">{{ $label }} @if ($required)<span class="text-red-600">*</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'form-input']) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name) <p class="form-error">{{ $message }}</p> @enderror
</div>
