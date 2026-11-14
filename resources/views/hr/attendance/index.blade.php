@extends('layouts.app')

@section('title', 'Attendance')
@section('breadcrumb', 'HR & Payroll / Attendance / '.$date->format('d M Y'))

@use('App\Models\Attendance')
@php
    $user = auth()->user();
    $canMark = $user->canModule('hr_attendance', 'create') || $user->canModule('hr_attendance', 'edit');
    $nav = fn ($d) => route('hr.attendance.index', ['date' => $d->toDateString()] + request()->only('department'));
@endphp

@section('content')
    <form method="POST" action="{{ route('hr.attendance.store') }}" class="card" data-sheet>
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <input type="hidden" name="department" value="{{ request('department') }}">

        <div class="card-header flex-wrap">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $nav($date->copy()->subDay()) }}" class="btn btn-secondary btn-sm" title="Previous day"><x-icon name="chevron-left" class="h-4 w-4" /></a>
                <input type="date" value="{{ $date->toDateString() }}" class="form-input w-auto py-1.5"
                       onchange="location = @js(route('hr.attendance.index')) + '?date=' + this.value + @js(request('department') ? '&department='.urlencode(request('department')) : '')">
                <a href="{{ $nav($date->copy()->addDay()) }}" class="btn btn-secondary btn-sm" title="Next day"><x-icon name="chevron-right" class="h-4 w-4" /></a>
                <span class="ml-1 font-semibold text-slate-700">{{ $date->format('l') }}</span>
                @unless ($date->isToday())<a href="{{ route('hr.attendance.index', request()->only('department')) }}" class="text-sm font-semibold text-brand-600 hover:underline">Today</a>@endunless
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($departments->isNotEmpty())
                    <select class="form-input w-auto py-1.5" onchange="location = @js(route('hr.attendance.index', ['date' => $date->toDateString()])) + (this.value ? '&department=' + encodeURIComponent(this.value) : '')">
                        <option value="">All departments</option>
                        @foreach ($departments as $d)<option @selected(request('department') === $d)>{{ $d }}</option>@endforeach
                    </select>
                @endif
                @module('hr_attendance', 'view')
                    <a href="{{ route('hr.attendance.register', ['month' => $date->format('Y-m')]) }}" class="btn btn-secondary btn-sm"><x-icon name="calendar" class="h-4 w-4" /> Muster roll</a>
                @endmodule
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50/60 px-5 py-2.5 text-sm">
            @foreach (Attendance::STATUSES as $status => $meta)
                @if ($summary[$status] ?? 0)<span class="badge {{ $meta['tone'] }}">{{ $meta['label'] }}: {{ $summary[$status] }}</span>@endif
            @endforeach
            @if ($unmarked)<span class="badge bg-amber-50 text-amber-700">Not marked: {{ $unmarked }}</span>@endif
            @if ($canMark && $rows->isNotEmpty())
                <span class="ml-auto flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-fill="suggested" title="Unmarked rows: Present, or Weekly Off on their off day">Fill unmarked</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-fill="present">All present</button>
                </span>
            @endif
        </div>

        @error('rows') <div class="border-b border-red-200 bg-red-50 px-5 py-2.5 text-sm font-medium text-red-700">{{ $message }}</div> @enderror

        <div class="overflow-x-auto">
            <table class="table-classic">
                <thead>
                <tr>
                    <th class="min-w-[200px]">Employee</th>
                    <th>Shift</th>
                    <th class="min-w-[150px]">Status</th>
                    <th>In</th>
                    <th>Out</th>
                    <th class="text-right">OT (h)</th>
                    <th class="min-w-[160px]">Remarks</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    @php
                        $e = $row['employee'];
                        $r = $row['record'];
                        $old = fn ($f, $default = null) => old("rows.{$e->id}.{$f}", $default);
                        $time = fn ($t) => $t ? substr($t, 0, 5) : null;
                    @endphp
                    <tr data-row data-suggested="{{ $row['suggested'] }}" data-start="{{ $time($row['shift']?->start_time) }}" data-end="{{ $time($row['shift']?->end_time) }}" class="{{ $r ? '' : 'bg-amber-50/30' }}">
                        <td>
                            <span class="font-mono text-xs font-semibold text-navy-800">{{ $e->code }}</span>
                            <span class="ml-1 font-semibold text-slate-800">{{ $e->name }}</span>
                            <div class="text-xs text-slate-500">{{ collect([$e->designation, $e->isWeeklyOff($date) ? 'weekly off day' : null])->filter()->join(' · ') }}</div>
                        </td>
                        <td class="whitespace-nowrap">
                            @if ($row['shift'])<span class="badge {{ $row['shift']->tone() }}" title="{{ $row['shift']->timing() }}">{{ $row['shift']->name }}</span>@else<span class="text-xs text-slate-400">—</span>@endif
                        </td>
                        <td>
                            <select name="rows[{{ $e->id }}][status]" class="form-input py-1.5" data-status @disabled(! $canMark)>
                                <option value="">— Not marked —</option>
                                @foreach (Attendance::STATUSES as $status => $meta)
                                    <option value="{{ $status }}" @selected($old('status', $r?->status) === $status)>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="time" name="rows[{{ $e->id }}][in_time]" value="{{ $old('in_time', $time($r?->in_time)) }}" class="form-input w-28 py-1.5" data-in @disabled(! $canMark)></td>
                        <td><input type="time" name="rows[{{ $e->id }}][out_time]" value="{{ $old('out_time', $time($r?->out_time)) }}" class="form-input w-28 py-1.5" data-out @disabled(! $canMark)></td>
                        <td><input type="number" step="0.5" min="0" max="24" name="rows[{{ $e->id }}][overtime_hours]" value="{{ $old('overtime_hours', $r && (float) $r->overtime_hours ? (float) $r->overtime_hours : null) }}" class="form-input w-20 py-1.5 text-right" placeholder="auto" @disabled(! $canMark)></td>
                        <td><input name="rows[{{ $e->id }}][remarks]" value="{{ $old('remarks', $r?->remarks) }}" maxlength="255" class="form-input py-1.5" @disabled(! $canMark)></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-12 text-center text-sm text-slate-500">No employees on the rolls on this date.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($canMark && $rows->isNotEmpty())
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
                <p class="text-xs text-slate-500">OT left blank is worked out from In / Out against the shift hours. Set a row back to "Not marked" to clear it.</p>
                <button class="btn btn-primary"><x-icon name="check" class="h-4 w-4" /> Save attendance</button>
            </div>
        @endif
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        const rows = [...document.querySelectorAll('[data-row]')];
        const fillTimes = (row) => {
            const status = row.querySelector('[data-status]').value;
            if (!['present', 'half_day'].includes(status)) return;
            const inp = row.querySelector('[data-in]'), out = row.querySelector('[data-out]');
            if (!inp.value && row.dataset.start) inp.value = row.dataset.start;
            if (!out.value && row.dataset.end && status === 'present') out.value = row.dataset.end;
        };
        document.querySelectorAll('[data-fill]').forEach((btn) => btn.addEventListener('click', () => {
            rows.forEach((row) => {
                const select = row.querySelector('[data-status]');
                if (btn.dataset.fill === 'present') select.value = row.dataset.suggested === 'weekly_off' && !select.value ? 'weekly_off' : 'present';
                else if (!select.value) select.value = row.dataset.suggested;
                fillTimes(row);
            });
        }));
        rows.forEach((row) => row.querySelector('[data-status]').addEventListener('change', () => fillTimes(row)));
    })();
</script>
@endpush
