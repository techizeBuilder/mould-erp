{{-- Shape dropdown from the shapes list, with "+ Add new shape…" revealing a text box (posts shape=__new + new_shape). --}}
@props(['value' => null, 'label' => 'Shape'])
@php
    $new = \App\Models\Shape::NEW;
    $value = old('shape', $value);
@endphp
<div {{ $attributes }} data-shape-select>
    <label for="shape" class="form-label">{{ $label }}</label>
    <select id="shape" name="shape" class="form-input">
        <option value="">— Select —</option>
        @foreach (\App\Models\Shape::options() as $key => $name)
            <option value="{{ $key }}" @selected($value === $key)>{{ $name }}</option>
        @endforeach
        <option value="{{ $new }}" @selected($value === $new)>+ Add new shape…</option>
    </select>
    <input id="new_shape" name="new_shape" value="{{ old('new_shape') }}" maxlength="50" placeholder="New shape name, e.g. Hexagonal"
           class="form-input mt-2 {{ $value === $new ? '' : 'hidden' }}" @disabled($value !== $new)>
    @error('shape') <p class="form-error">{{ $message }}</p> @enderror
    @error('new_shape') <p class="form-error">{{ $message }}</p> @enderror
</div>

@once
    @push('scripts')
    <script>
        document.querySelectorAll('[data-shape-select]').forEach((box) => {
            const select = box.querySelector('select');
            const input = box.querySelector('input');
            select.addEventListener('change', () => {
                const adding = select.value === @json($new);
                input.classList.toggle('hidden', !adding);
                input.disabled = !adding;
                input.required = adding;
                if (adding) input.focus();
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    </script>
    @endpush
@endonce
