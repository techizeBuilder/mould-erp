{{-- An order's approval trail. Expects $order. --}}
@use('App\Models\SalesOrder')
<div class="card">
    <div class="card-header"><h2 class="card-title">Approval trail</h2></div>
    <ol class="divide-y divide-slate-100">
        @foreach ($order->logs->sortByDesc('id') as $log)
            <li class="px-5 py-2.5 text-sm">
                <span class="font-semibold text-slate-800">{{ SalesOrder::STATUSES[$log->to_status] ?? $log->to_status }}</span>
                @if ($log->note)<span class="text-slate-600">— {{ $log->note }}</span>@endif
                <div class="text-xs text-slate-500">{{ $log->created_at->format('d M Y, h:i A') }}{{ $log->user ? ' · '.$log->user->name : '' }}</div>
            </li>
        @endforeach
    </ol>
</div>
