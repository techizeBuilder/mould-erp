@extends('layouts.app')

@section('title', 'Muster Roll')
@section('breadcrumb', 'HR & Payroll / Attendance / Muster roll / '.$month->format('F Y'))

@use('App\Models\Attendance')

@section('content')
    <div class="card">
        <div class="card-header flex-wrap print:hidden">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <a href="{{ route('hr.attendance.register', ['month' => $month->copy()->subMonth()->format('Y-m')] + request()->only('department')) }}" class="btn btn-secondary btn-sm"><x-icon name="chevron-left" class="h-4 w-4" /></a>
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-input w-auto py-1.5" onchange="this.form.submit()">
                <a href="{{ route('hr.attendance.register', ['month' => $month->copy()->addMonth()->format('Y-m')] + request()->only('department')) }}" class="btn btn-secondary btn-sm"><x-icon name="chevron-right" class="h-4 w-4" /></a>
                @if ($departments->isNotEmpty())
                    <select name="department" class="form-input w-auto py-1.5" onchange="this.form.submit()">
                        <option value="">All departments</option>
                        @foreach ($departments as $d)<option @selected(request('department') === $d)>{{ $d }}</option>@endforeach
                    </select>
                @endif
            </form>
            <div class="flex gap-2">
                <a href="{{ route('hr.attendance.index') }}" class="btn btn-secondary btn-sm"><x-icon name="calendar" class="h-4 w-4" /> Daily sheet</a>
                <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm"><x-icon name="printer" class="h-4 w-4" /> Print</button>
            </div>
        </div>

        <div class="px-5 pt-4">
            <h2 class="text-base font-bold text-navy-900">{{ config('company.name') }} — Muster roll, {{ $month->format('F Y') }}</h2>
            <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                @foreach (Attendance::STATUSES as $meta)<span class="badge {{ $meta['tone'] }}">{{ $meta['short'] }} = {{ $meta['label'] }}</span>@endforeach
                <span class="badge bg-white text-slate-400 ring-1 ring-slate-200">wo = weekly off, not marked</span>
                <span class="badge bg-amber-50 text-amber-700">· = not marked</span>
            </div>
        </div>

        <div class="overflow-x-auto p-5">
            <table class="w-full border-collapse text-xs">
                <thead>
                <tr class="bg-slate-50 text-slate-600">
                    <th class="sticky left-0 z-10 border border-slate-200 bg-slate-50 px-2 py-1.5 text-left">Employee</th>
                    @foreach ($days as $day)
                        <th class="border border-slate-200 px-1 py-1.5 text-center font-semibold {{ $day->isSunday() ? 'bg-slate-100' : '' }}">{{ $day->day }}<div class="font-normal text-slate-400">{{ substr($day->format('D'), 0, 2) }}</div></th>
                    @endforeach
                    <th class="border border-slate-200 px-1.5 py-1.5 text-center">P</th>
                    <th class="border border-slate-200 px-1.5 py-1.5 text-center">HD</th>
                    <th class="border border-slate-200 px-1.5 py-1.5 text-center">A</th>
                    <th class="border border-slate-200 px-1.5 py-1.5 text-center">Leave</th>
                    <th class="border border-slate-200 px-1.5 py-1.5 text-center">WO / H</th>
                    <th class="border border-slate-200 bg-brand-50 px-1.5 py-1.5 text-center text-brand-700">Paid days</th>
                    <th class="border border-slate-200 px-1.5 py-1.5 text-center">OT h</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    @php $e = $row['employee']; $c = $row['counts']; @endphp
                    <tr>
                        <td class="sticky left-0 z-10 border border-slate-200 bg-white px-2 py-1 whitespace-nowrap">
                            <span class="font-mono font-semibold text-navy-800">{{ $e->code }}</span> {{ $e->name }}
                        </td>
                        @foreach ($days as $day)
                            @php $rec = $row['byDate']->get($day->toDateString()); @endphp
                            <td class="border border-slate-200 p-0.5 text-center">
                                @if (! $e->isEmployedOn($day))
                                    <span class="text-slate-300">–</span>
                                @elseif ($rec)
                                    <span class="block rounded px-0.5 font-semibold {{ Attendance::tone($rec->status) }}" title="{{ Attendance::STATUSES[$rec->status]['label'] }}{{ (float) $rec->overtime_hours ? ' · OT '.(float) $rec->overtime_hours.' h' : '' }}">{{ Attendance::short($rec->status) }}</span>
                                @elseif ($e->isWeeklyOff($day))
                                    <span class="text-slate-400">wo</span>
                                @else
                                    <span class="font-bold text-amber-500">·</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="border border-slate-200 px-1.5 text-center tabular-nums">{{ $c['present'] ?? 0 }}</td>
                        <td class="border border-slate-200 px-1.5 text-center tabular-nums">{{ $c['half_day'] ?? 0 }}</td>
                        <td class="border border-slate-200 px-1.5 text-center tabular-nums">{{ ($c['absent'] ?? 0) + ($c['unpaid_leave'] ?? 0) }}</td>
                        <td class="border border-slate-200 px-1.5 text-center tabular-nums">{{ $c['paid_leave'] ?? 0 }}</td>
                        <td class="border border-slate-200 px-1.5 text-center tabular-nums">{{ ($c['weekly_off'] ?? 0) + ($c['holiday'] ?? 0) + $row['implied_off'] }}</td>
                        <td class="border border-slate-200 bg-brand-50/50 px-1.5 text-center font-bold tabular-nums">{{ rtrim(rtrim(number_format($row['paid'], 1), '0'), '.') }}</td>
                        <td class="border border-slate-200 px-1.5 text-center tabular-nums">{{ $row['overtime'] ? rtrim(rtrim(number_format($row['overtime'], 1), '0'), '.') : '' }}</td>
                    </tr>
                    @if ($row['unmarked'])
                        <tr class="print:hidden"><td colspan="{{ $days->count() + 8 }}" class="border border-slate-200 bg-amber-50 px-2 py-0.5 text-amber-700">{{ $row['unmarked'] }} day(s) not marked for {{ $e->name }} — they count as unpaid in payroll until marked.</td></tr>
                    @endif
                @empty
                    <tr><td colspan="{{ $days->count() + 8 }}" class="py-10 text-center text-sm text-slate-500">No employees on the rolls this month.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
