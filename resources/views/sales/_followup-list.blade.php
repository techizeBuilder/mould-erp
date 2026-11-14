{{-- Follow-ups on a lead, quotation or customer page. Expects $followUps and $createQuery (e.g. ['lead' => 1]). --}}
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Follow-ups</h2>
        @module('sales_followups', 'create')
            <a href="{{ route('sales.followups.create', $createQuery) }}" class="btn btn-secondary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Schedule</a>
        @endmodule
    </div>
    <ul class="divide-y divide-slate-100">
        @forelse ($followUps as $f)
            <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-3 text-sm">
                <div class="min-w-0">
                    <div class="font-semibold text-slate-800">{{ $f->purposeLabel() }} <span class="font-normal text-slate-500">· {{ $f->typeLabel() }}</span></div>
                    <div class="text-xs text-slate-500">{{ $f->due_at->format('d M Y, h:i A') }}{{ $f->assignee ? ' · '.$f->assignee->name : '' }}{{ $f->product ? ' · '.$f->product->name : '' }}</div>
                    @if ($f->outcome)<div class="mt-1 text-slate-700">{{ $f->outcome }}</div>@elseif ($f->agenda)<div class="mt-1 text-slate-600">{{ $f->agenda }}</div>@endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="badge {{ $f->statusTone() }}">{{ $f->statusLabel() }}</span>
                    @module('sales_followups', 'view')
                        <a href="{{ route('sales.followups.show', $f) }}" class="btn btn-secondary btn-sm" title="Open"><x-icon name="eye" class="h-4 w-4" /></a>
                    @endmodule
                </div>
            </li>
        @empty
            <li class="px-5 py-4 text-sm text-slate-500">No follow-ups yet.</li>
        @endforelse
    </ul>
</div>
