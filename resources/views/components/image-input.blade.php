@props(['url' => null, 'label' => 'Image'])
<div {{ $attributes }} data-image-input>
    <span class="form-label">{{ $label }}</span>
    <div class="flex items-start gap-4">
        <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50" data-image-preview>
            @if ($url)
                <img src="{{ $url }}" alt="" class="h-full w-full object-contain">
            @else
                <x-icon name="cube" class="h-8 w-8 text-slate-300" />
            @endif
        </div>
        <div class="min-w-0 flex-1 space-y-2">
            <input type="file" name="image" accept="image/png,image/jpeg,image/webp"
                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100"
                   onchange="const f = this.files[0]; if (!f) return; const img = new Image(); img.className = 'h-full w-full object-contain'; img.src = URL.createObjectURL(f); this.closest('[data-image-input]').querySelector('[data-image-preview]').replaceChildren(img);">
            <p class="text-xs text-slate-500">JPG, PNG or WebP, up to 2 MB.</p>
            @if ($url)
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remove_image" value="1" class="h-4 w-4 accent-red-600"> Remove current image
                </label>
            @endif
            @error('image') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
