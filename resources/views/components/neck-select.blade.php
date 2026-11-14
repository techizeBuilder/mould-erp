{{--
    Neck dropdown from Neck Master. People who may add necks also get "+ Add new neck…", which reveals
    its parameters (posts neck_id=__new + new_neck_*) and saves it with its own serial number.
--}}
@props(['value' => null, 'label' => 'Neck', 'hint' => null])
@php
    use App\Models\Neck;

    $new = Neck::NEW;
    $current = old('neck_id', $value);
    $necks = Neck::where('status', 'active')->when($value, fn ($q) => $q->orWhere('id', $value))->orderBy('code')->get();
    $canAdd = auth()->user()?->canModule('neck_master', 'create');
    $adding = (string) $current === $new;
@endphp
<div {{ $attributes }} data-neck-select>
    <label for="neck_id" class="form-label">{{ $label }}</label>
    <select id="neck_id" name="neck_id" class="form-input">
        <option value="">— No neck —</option>
        @foreach ($necks as $neck)
            <option value="{{ $neck->id }}" data-size="{{ $neck->neck_size_mm !== null ? (float) $neck->neck_size_mm : '' }}" data-type="{{ $neck->neck_type }}" @selected((string) $current === (string) $neck->id)>{{ $neck->code }} – {{ $neck->name }}</option>
        @endforeach
        @if ($canAdd)<option value="{{ $new }}" @selected($adding)>+ Add new neck…</option>@endif
    </select>
    @if ($hint)<p class="mt-1 text-xs text-slate-500" data-neck-hint>{{ $hint }}</p>@endif
    @error('neck_id') <p class="form-error">{{ $message }}</p> @enderror

    @if ($canAdd)
        <div class="mt-3 grid gap-3 rounded border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 {{ $adding ? '' : 'hidden' }}" data-neck-new>
            <div>
                <label for="new_neck_size_mm" class="form-label">Neck size (mm) <span class="text-red-600">*</span></label>
                <input id="new_neck_size_mm" name="new_neck_size_mm" type="number" step="0.01" min="0" value="{{ old('new_neck_size_mm') }}" class="form-input" placeholder="e.g. 28" @disabled(! $adding)>
                @error('new_neck_size_mm') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="new_neck_type" class="form-label">Neck type</label>
                <input id="new_neck_type" name="new_neck_type" list="new-neck-types" maxlength="50" value="{{ old('new_neck_type') }}" class="form-input" placeholder="e.g. PCO 1881" @disabled(! $adding)>
                <datalist id="new-neck-types">@foreach (\App\Models\Mould::COMMON_NECK_TYPES as $type)<option value="{{ $type }}">@endforeach</datalist>
            </div>
            <div>
                <label for="new_neck_height_mm" class="form-label">Height (mm)</label>
                <input id="new_neck_height_mm" name="new_neck_height_mm" type="number" step="0.01" min="0" value="{{ old('new_neck_height_mm') }}" class="form-input" @disabled(! $adding)>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="new_inner_dia_mm" class="form-label">Inner dia</label>
                    <input id="new_inner_dia_mm" name="new_inner_dia_mm" type="number" step="0.01" min="0" value="{{ old('new_inner_dia_mm') }}" class="form-input" @disabled(! $adding)>
                </div>
                <div>
                    <label for="new_outer_dia_mm" class="form-label">Outer dia</label>
                    <input id="new_outer_dia_mm" name="new_outer_dia_mm" type="number" step="0.01" min="0" value="{{ old('new_outer_dia_mm') }}" class="form-input" @disabled(! $adding)>
                </div>
            </div>
        </div>
    @endif
</div>

@once
    @push('scripts')
    <script>
        // The chosen or typed neck, for the name previews on the mould and product forms.
        window.selectedNeck = () => {
            const select = document.getElementById('neck_id');
            if (select.value === @json($new)) {
                return { size: document.getElementById('new_neck_size_mm').value, type: document.getElementById('new_neck_type').value.trim() };
            }
            const option = select.selectedOptions[0];
            return { size: option?.dataset.size || '', type: option?.dataset.type || '' };
        };

        document.querySelectorAll('[data-neck-select]').forEach((box) => {
            const select = box.querySelector('select');
            const panel = box.querySelector('[data-neck-new]');
            if (!panel) return;
            const sync = () => {
                const adding = select.value === @json($new);
                panel.classList.toggle('hidden', !adding);
                panel.querySelectorAll('input').forEach((input) => { input.disabled = !adding; });
                document.getElementById('new_neck_size_mm').required = adding;
            };
            select.addEventListener('change', sync);
            sync();
        });
    </script>
    @endpush
@endonce
