{{--
    Searchable multi-select as a checkbox list.
    options: [['id' => 1, 'label' => 'MLD-0001 – 1L Round', 'meta' => 'Blow · 1 L', 'kind' => 'blow'], ...]
    filterBy / allowed: optionally hide options whose "kind" doesn't suit the value of another
    field, e.g. filterBy="machine_type" allowed='{"preform":["preform"],"blow":["blow"]}'.
--}}
@props(['name', 'options', 'selected' => [], 'empty' => 'Nothing to choose from yet.', 'filterBy' => null, 'allowed' => null])
@php $selected = array_map('intval', old(rtrim($name, '[]'), $selected)); @endphp
<div class="rounded border border-slate-300" data-checklist
     @if ($filterBy) data-filter-by="{{ $filterBy }}" data-allowed="{{ json_encode($allowed) }}" @endif>
    @if (count($options))
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2">
            <x-icon name="search" class="h-4 w-4 shrink-0 text-slate-400" />
            <input type="search" data-search placeholder="Search…" class="w-full bg-transparent text-sm outline-none">
            <span class="text-xs whitespace-nowrap text-slate-500"><span data-count>{{ count($selected) }}</span> selected</span>
        </div>
        <div class="max-h-64 divide-y divide-slate-100 overflow-y-auto">
            @foreach ($options as $option)
                <label class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-brand-50/50" data-option data-kind="{{ $option['kind'] ?? '' }}">
                    <input type="checkbox" name="{{ $name }}" value="{{ $option['id'] }}" class="h-4 w-4 accent-brand-600" @checked(in_array($option['id'], $selected, true))>
                    <span class="min-w-0 flex-1">
                        <span class="font-medium text-slate-800">{{ $option['label'] }}</span>
                        @if (! empty($option['meta']))<span class="ml-1 text-xs text-slate-500">{{ $option['meta'] }}</span>@endif
                    </span>
                    @if (! empty($option['badge']))<span class="badge shrink-0 {{ $option['badgeTone'] ?? 'bg-slate-100 text-slate-600' }}">{{ $option['badge'] }}</span>@endif
                </label>
            @endforeach
            <p class="hidden px-3 py-4 text-center text-sm text-slate-500" data-none>No matching items.</p>
        </div>
    @else
        <p class="px-3 py-4 text-center text-sm text-slate-500">{{ $empty }}</p>
    @endif
</div>

@once
    @push('scripts')
    <script>
        document.querySelectorAll('[data-checklist]').forEach((list) => {
            const options = [...list.querySelectorAll('[data-option]')];
            const search = list.querySelector('[data-search]');
            const filterField = list.dataset.filterBy ? document.getElementById(list.dataset.filterBy) : null;
            const allowed = list.dataset.allowed ? JSON.parse(list.dataset.allowed) : null;

            const refresh = (fromFilterChange) => {
                const term = (search?.value || '').toLowerCase();
                const kinds = filterField && allowed ? allowed[filterField.value] : null;
                let visible = 0;
                options.forEach((opt) => {
                    const fits = !kinds || kinds.includes(opt.dataset.kind);
                    const box = opt.querySelector('input');
                    // Unsuitable options are unticked when the type changes, so they can't be saved by mistake.
                    if (!fits && fromFilterChange) box.checked = false;
                    const show = fits && opt.textContent.toLowerCase().includes(term);
                    opt.classList.toggle('hidden', !show);
                    if (show) visible++;
                });
                list.querySelector('[data-none]')?.classList.toggle('hidden', visible > 0);
                const count = list.querySelector('[data-count]');
                if (count) count.textContent = options.filter((o) => o.querySelector('input').checked).length;
            };

            search?.addEventListener('input', () => refresh(false));
            list.addEventListener('change', () => refresh(false));
            filterField?.addEventListener('change', () => refresh(true));
            refresh(false);
        });
    </script>
    @endpush
@endonce
