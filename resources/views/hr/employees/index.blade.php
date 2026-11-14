@extends('layouts.app')

@section('title', 'Employees')
@section('breadcrumb', 'HR & Payroll / Employees')

@use('App\Support\Money')

@section('content')
    <div class="card">
        <div class="card-header flex-wrap">
            <h2 class="card-title">Employees @module('hr_employees', 'view')<span class="ml-1 text-sm font-normal text-slate-500">({{ $employees->total() }})</span>@endmodule</h2>
            @module('hr_employees', 'create')
                <a href="{{ route('hr.employees.create') }}" class="btn btn-primary"><x-icon name="user-plus" class="h-4 w-4" /> Add Employee</a>
            @endmodule
        </div>

        @module('hr_employees', 'view')
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-3">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input name="search" value="{{ request('search') }}" class="form-input pl-9" placeholder="Search code, name, mobile or designation">
                    </div>
                </div>
                <select name="department" class="form-input w-auto">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)
                        <option @selected(request('department') === $d)>{{ $d }}</option>
                    @endforeach
                </select>
                <select name="type" class="form-input w-auto">
                    <option value="">All types</option>
                    @foreach (\App\Models\Employee::EMPLOYMENT_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-input w-auto">
                    @foreach (['active' => 'Active', 'left' => 'Left', 'all' => 'All'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status', 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary">Filter</button>
                @if (request()->hasAny(['search', 'department', 'type', 'status']))
                    <a href="{{ route('hr.employees.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="table-classic">
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Default shift</th>
                        <th>Joined</th>
                        <th class="text-right">Gross / month</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td class="font-mono text-xs font-semibold whitespace-nowrap text-navy-800">{{ $employee->code }}</td>
                            <td>
                                <div class="flex items-center gap-3">
                                    @if ($employee->imageUrl())
                                        <img src="{{ $employee->imageUrl() }}" alt="" class="h-9 w-9 rounded-full object-cover" loading="lazy">
                                    @else
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-800 text-xs font-bold text-white">{{ $employee->initials() }}</span>
                                    @endif
                                    <div>
                                        <a href="{{ route('hr.employees.show', $employee) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $employee->name }}</a>
                                        <div class="text-xs text-slate-500">{{ collect([$employee->designation, $employee->phone])->filter()->join(' · ') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $employee->department ?: '—' }}</td>
                            <td>
                                @if ($employee->shift)
                                    <span class="badge {{ $employee->shift->tone() }}">{{ $employee->shift->name }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">{{ $employee->joining_date->format('d M Y') }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">₹{{ Money::format($employee->fixedGross(), 0) }}</td>
                            <td><span class="badge {{ $employee->statusTone() }}">{{ $employee->statusLabel() }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-secondary btn-sm" title="View"><x-icon name="eye" class="h-4 w-4" /></a>
                                    @module('hr_employees', 'edit')
                                        <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-secondary btn-sm" title="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    @endmodule
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-sm text-slate-500">
                                No employees found.
                                @module('hr_employees', 'create')<a href="{{ route('hr.employees.create') }}" class="font-semibold text-brand-600 hover:underline">Add the first one</a>.@endmodule
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($employees->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $employees->links() }}</div>@endif
        @else
            <p class="px-5 py-10 text-center text-sm text-slate-500">You don't have permission to view these records.</p>
        @endmodule
    </div>
@endsection
