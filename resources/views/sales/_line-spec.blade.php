{{-- A saved line's product as shown in tables. Expects $line. --}}
<div class="font-semibold text-slate-800">
    @if ($line->product_id)
        @module('product_master', 'view')
            <a href="{{ route('masters.products.show', $line->product_id) }}" class="hover:text-brand-600 hover:underline">{{ $line->description }}</a>
        @else
            {{ $line->description }}
        @endmodule
    @else
        {{ $line->description }}
    @endif
</div>
<div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
    <span class="badge {{ $line->mouldStatusTone() }}">{{ $line->mouldStatusLabel() }}</span>
    @if ($line->isCustomSpec())<span class="badge bg-slate-100 text-slate-600">Custom spec</span>@endif
    @if ($line->mould_id && $line->mould)<span class="font-mono">{{ $line->mould->code }}</span>@endif
    @if ($line->requirementNote())<span>{{ $line->requirementNote() }}</span>@endif
</div>
