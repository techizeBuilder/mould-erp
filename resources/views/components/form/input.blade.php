{{-- Labelled input with old-value handling and its validation error. --}}
@props(['name', 'label', 'value' => null, 'type' => 'text', 'required' => false, 'hint' => null, 'list' => null, 'inputClass' => ''])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="form-label">{{ $label }} @if ($required)<span class="text-red-600">*</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) @if ($list) list="{{ $list }}" @endif
           {{ $attributes->except('class')->merge(['class' => "form-input $inputClass"]) }}>
    @if ($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name) <p class="form-error">{{ $message }}</p> @enderror
</div>
