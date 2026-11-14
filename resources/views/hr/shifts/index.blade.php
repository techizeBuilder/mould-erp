@extends('layouts.app')

@section('title', 'Shifts & Rotation')
@section('breadcrumb', 'HR & Payroll / Shifts & Rotation')

@section('content')
    <div class="space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div>
                    <h2 class="card-title">Weekly roster</h2>
                    <p class="text-xs text-slate-500">{{ $days->first()->format('d M') }} – {{ $days->last()->format('d M Y') }} · faded = default shift, bold = assigned / rotation</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('hr.shifts.index', ['week' => $weekStart->copy()->subWeek()->toDateString()] + request()->only('department')) }}" class="btn btn-secondary btn-sm" title="Previous week"><x-icon name="chevron-left" class="h-4 w-4" /></a>
                    <a href="{{ route('hr.shifts.index', request()->only('department')) }}" class="btn btn-secondary btn-sm">This week</a>
                    <a href="{{ route('hr.shifts.index', ['week' => $weekStart->copy()->addWeek()->toDateString()] + request()->only('department')) }}" class="btn btn-secondary btn-sm" title="Next week"><x-icon name="chevron-right" class="h-4 w-4" /></a>
                    @module('hr_shifts', 'create')
                        <a href="{{ route('hr.shifts.assign') }}" class="btn btn-secondary btn-sm"><x-icon name="clock" class="h-4 w-4" /> Assign shift</a>
                        <a href="{{ route('hr.shifts.rotate') }}" class="btn btn-primary btn-sm"><x-icon name="refresh" class="h-4 w-4" /> Rotate shifts</a>
                    @endmodule
                </div>
            </div>

            @if ($departments->isNotEmpty())
                <form method="GET" class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-2.5">
                    <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                    <select name="department" class="form-input w-auto" onchange="this.form.submit()">
                        <option value="">All departments</option>
                        @foreach ($departments as $d)<option @selected(request('department') === $d)>{{ $d }}</option>@endforeach
                    </select>
                </form>
            @endif

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th class="min-w-[200px]">Employee</th>
                        @foreach ($days as $day)
                            <th class="text-center {{ $day->isToday() ? '!bg-brand-50 !text-brand-700' : '' }}">{{ $day->format('D') }}<div class="font-normal normal-case">{{ $day->format('d M') }}</div></th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>
                                <span class="font-mono text-xs font-semibold text-navy-800">{{ $employee->code }}</span>
                                <span class="ml-1 font-semibold text-slate-800">{{ $employee->name }}</span>
                                <div class="text-xs text-slate-500">{{ $employee->department }}</div>
                            </td>
                            @foreach ($days as $day)
                                @php
                                    $employed = $employee->isEmployedOn($day);
                                    $assigned = $employee->shiftAssignments->first(fn ($a) => $a->start_date->lte($day) && $a->end_date->gte($day));
                                    $shift = $assigned?->shift ?? $employee->shift;
                                @endphp
                                <td class="text-center whitespace-nowrap {{ $day->isToday() ? 'bg-brand-50/40' : '' }}">
                                    @if (! $employed)
                                        <span class="text-xs text-slate-300">—</span>
                                    @elseif ($employee->isWeeklyOff($day))
                                        <span class="badge bg-slate-100 text-slate-500">Off</span>
                                    @elseif ($shift)
                                        <span class="badge {{ $shift->tone() }} {{ $assigned ? '' : 'opacity-50' }}" title="{{ $shift->timing() }}{{ $assigned ? ' · assigned' : ' · default shift' }}">{{ $shift->name }}</span>
                                    @else
                                        <span class="text-xs text-amber-600">No shift</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-10 text-center text-sm text-slate-500">No employees on the rolls this week.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Shifts <span class="ml-1 text-sm font-normal text-slate-500">({{ $shifts->count() }})</span></h2>
                @module('hr_shifts', 'create')
                    <a href="{{ route('hr.shifts.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Add Shift</a>
                @endmodule
            </div>
            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Shift</th>
                        <th>Timing</th>
                        <th class="text-right">Break</th>
                        <th class="text-right">Working hours</th>
                        <th class="text-right">Grace</th>
                        <th class="text-center">Default for</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($shifts as $shift)
                        <tr>
                            <td><span class="badge {{ $shift->tone() }}">{{ $shift->name }}</span></td>
                            <td class="whitespace-nowrap">{{ $shift->timing() }}</td>
                            <td class="text-right tabular-nums">{{ $shift->break_minutes }} min</td>
                            <td class="text-right tabular-nums">{{ $shift->hoursLabel() }}</td>
                            <td class="text-right tabular-nums">{{ $shift->grace_minutes }} min</td>
                            <td class="text-center tabular-nums">{{ $shift->employees_count }}</td>
                            <td><span class="badge {{ $shift->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $shift->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    @module('hr_shifts', 'edit')
                                        <a href="{{ route('hr.shifts.edit', $shift) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    @endmodule
                                    @module('hr_shifts', 'delete')
                                        <form method="POST" action="{{ route('hr.shifts.destroy', $shift) }}" data-confirm="Delete shift &quot;{{ $shift->name }}&quot;?">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm border border-red-200 bg-white text-red-600 hover:bg-red-50" title="Delete"><x-icon name="trash" class="h-4 w-4" /></button>
                                        </form>
                                    @endmodule
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-10 text-center text-sm text-slate-500">No shifts yet — add e.g. Day (08:00–20:00) and Night (20:00–08:00).</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
