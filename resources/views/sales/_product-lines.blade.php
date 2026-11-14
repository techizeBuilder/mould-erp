{{-- The repeating product lines of a lead or quotation. Expects $rows, $mode, $products, $shapes, $neckTypes. --}}
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">{{ $mode === 'lead' ? 'Products required' : 'Products quoted' }}</h2>
            <p class="text-xs text-slate-500">Pick from Product Master, or enter a custom spec. Custom specs are checked against Mould Master when saved.</p>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-line-add><x-icon name="plus" class="h-4 w-4" /> Add product</button>
    </div>
    <div class="space-y-4 p-5" data-lines>
        @error('items') <p class="form-error">{{ $message }}</p> @enderror
        @foreach ($rows as $i => $row)
            @include('sales._product-line', ['i' => $i, 'row' => $row])
        @endforeach
    </div>
    <datalist id="neck-types">@foreach ($neckTypes as $n)<option value="{{ $n }}">@endforeach</datalist>
    <template data-line-template>
        @include('sales._product-line', ['i' => '__I__', 'row' => []])
    </template>
</div>

@push('scripts')
<script>
    (() => {
        const box = document.querySelector('[data-lines]');
        const template = document.querySelector('[data-line-template]');
        let next = box.querySelectorAll('[data-line]').length + 1000;

        // A catalogue product brings its own specs, so the spec fields are hidden and not posted.
        const syncLine = (line) => {
            const picked = line.querySelector('[data-line-product]').value !== '';
            const spec = line.querySelector('[data-line-spec]');
            spec.classList.toggle('hidden', picked);
            spec.querySelectorAll('input, select').forEach((f) => { f.disabled = picked; });
        };

        box.querySelectorAll('[data-line]').forEach(syncLine);
        box.addEventListener('change', (e) => {
            if (e.target.matches('[data-line-product]')) syncLine(e.target.closest('[data-line]'));
        });
        box.addEventListener('click', (e) => {
            const remove = e.target.closest('[data-line-remove]');
            if (!remove) return;
            if (box.querySelectorAll('[data-line]').length === 1) { alert('Keep at least one product.'); return; }
            remove.closest('[data-line]').remove();
            box.dispatchEvent(new Event('input', { bubbles: true }));
        });
        document.querySelector('[data-line-add]').addEventListener('click', () => {
            const holder = document.createElement('div');
            holder.innerHTML = template.innerHTML.replaceAll('__I__', next++);
            const line = holder.firstElementChild;
            box.appendChild(line);
            syncLine(line);
        });
    })();
</script>
@endpush
