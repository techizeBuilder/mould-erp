{{-- A summary tile, optionally linking to the filtered list. Expects $label, $value; optional $href, $tone, $hint. --}}
@php $tag = isset($href) ? 'a' : 'div'; @endphp
<{{ $tag }} @isset($href) href="{{ $href }}" @endisset class="card block p-4 {{ isset($href) ? 'transition hover:border-brand-500' : '' }}">
    <div class="text-2xl font-bold tabular-nums {{ $tone ?? 'text-navy-900' }}">{{ $value }}</div>
    <div class="text-sm text-slate-500">{{ $label }}</div>
    @isset($hint)<div class="mt-0.5 text-xs text-slate-400">{{ $hint }}</div>@endisset
</{{ $tag }}>
