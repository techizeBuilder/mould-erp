{{-- One label/value pair on a "view details" page. Empty values show a dash. --}}
@props(['label'])
<div {{ $attributes }}>
    <dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $label }}</dt>
    <dd class="mt-0.5 text-sm text-slate-800">{{ trim($slot) !== '' ? $slot : '—' }}</dd>
</div>
