@extends('layouts.app')

@section('title', $employee->name)
@section('breadcrumb', 'HR & Payroll / Employees / '.$employee->code)

@use('App\Support\Money')
@use('App\Models\Attendance')
@use('App\Models\Employee')

@section('content')
    <div class="max-w-5xl space-y-6">
        <div class="card">
            <div class="card-header flex-wrap">
                <div class="flex items-center gap-3">
                    @if ($employee->imageUrl())
                        <img src="{{ $employee->imageUrl() }}" alt="" class="h-12 w-12 rounded-full object-cover">
                    @else
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-navy-900 font-bold text-gold-500">{{ $employee->initials() }}</span>
                    @endif
                    <div>
                        <div class="font-mono text-xs font-semibold text-navy-700">{{ $employee->code }}</div>
                        <h2 class="card-title">{{ $employee->name }}</h2>
                        <div class="text-sm text-slate-500">{{ collect([$employee->designation, $employee->department])->filter()->join(' · ') }}</div>
                    </div>
                    <span class="badge {{ $employee->statusTone() }}">{{ $employee->statusLabel() }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    @module('hr_attendance', 'view')
                        <a href="{{ route('hr.attendance.register', ['month' => now()->format('Y-m')]) }}" class="btn btn-secondary"><x-icon name="calendar" class="h-4 w-4" /> Muster roll</a>
                    @endmodule
                    @module('hr_employees', 'edit')
                        <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                    @endmodule
                    @module('hr_employees', 'delete')
                        <form method="POST" action="{{ route('hr.employees.destroy', $employee) }}" data-confirm="Delete employee &quot;{{ $employee->code }} – {{ $employee->name }}&quot; and all their attendance? To keep the history, set the status to Left instead.">
                            @csrf @method('DELETE')
                            <button class="btn border border-red-200 bg-white text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                        </form>
                    @endmodule
                </div>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-detail label="Father's / spouse's name">{{ $employee->father_name }}</x-detail>
                <x-detail label="Gender">{{ $employee->genderLabel() }}</x-detail>
                <x-detail label="Date of birth">{{ $employee->date_of_birth?->format('d M Y') }}</x-detail>
                <x-detail label="Mobile">{{ $employee->phone }}</x-detail>
                <x-detail label="Email">{{ $employee->email }}</x-detail>
                <x-detail label="Aadhaar">{{ $employee->aadhaar ? 'XXXX XXXX '.substr($employee->aadhaar, -4) : '' }}</x-detail>
                <x-detail label="Address" class="sm:col-span-2"><span class="whitespace-pre-line">{{ $employee->address }}</span></x-detail>

                <x-detail label="Employment type">{{ $employee->employmentTypeLabel() }}</x-detail>
                <x-detail label="Date of joining">{{ $employee->joining_date->format('d M Y') }}</x-detail>
                <x-detail label="Last working day">{{ $employee->leaving_date?->format('d M Y') }}</x-detail>
                <x-detail label="Weekly off">{{ $employee->weeklyOffLabel() }}</x-detail>
                <x-detail label="Default shift">{{ $employee->shift ? $employee->shift->name.' · '.$employee->shift->timing() : '' }}</x-detail>
                <x-detail label="Shift today">
                    @if ($todayShift)<span class="badge {{ $todayShift->tone() }}">{{ $todayShift->name }}</span> <span class="text-xs text-slate-500">{{ $todayShift->timing() }}</span>@endif
                </x-detail>
                <x-detail label="Added by" class="sm:col-span-2">{{ $employee->creator?->name }}{{ $employee->creator ? ' · ' : '' }}{{ $employee->created_at?->format('d M Y') }}</x-detail>
                @if ($employee->remarks)
                    <x-detail label="Remarks" class="sm:col-span-2 lg:col-span-4"><span class="whitespace-pre-line">{{ $employee->remarks }}</span></x-detail>
                @endif
            </dl>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Salary structure</h2>
                    <span class="text-sm font-bold text-navy-900">₹{{ Money::format($employee->fixedGross()) }} / month</span>
                </div>
                <table class="table-classic">
                    <tbody>
                    @foreach (Employee::SALARY_COMPONENTS as $field => $label)
                        <tr><td>{{ $label }}</td><td class="text-right tabular-nums">₹{{ Money::format($employee->{$field}) }}</td></tr>
                    @endforeach
                    <tr><td>Overtime rate</td><td class="text-right tabular-nums">{{ $employee->ot_rate_per_hour !== null ? '₹'.Money::format($employee->ot_rate_per_hour).' / hour' : 'Auto ('.config('payroll.overtime_multiplier').'× hourly rate)' }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Statutory &amp; bank</h2></div>
                <dl class="grid gap-5 p-5 sm:grid-cols-2">
                    <x-detail label="PF">{{ $employee->pf_applicable ? 'Applicable' : 'Not applicable' }}</x-detail>
                    <x-detail label="ESI">{{ $employee->esi_applicable ? 'Applicable' : 'Not applicable' }}</x-detail>
                    <x-detail label="UAN">{{ $employee->uan }}</x-detail>
                    <x-detail label="PF account no.">{{ $employee->pf_number }}</x-detail>
                    <x-detail label="ESI IP no.">{{ $employee->esi_number }}</x-detail>
                    <x-detail label="Professional tax">{{ (float) $employee->professional_tax ? '₹'.Money::format($employee->professional_tax).' / month' : '' }}</x-detail>
                    <x-detail label="PAN">{{ $employee->pan }}</x-detail>
                    <x-detail label="Bank">{{ $employee->bank_name }}</x-detail>
                    <x-detail label="Account no.">{{ $employee->bank_account }}</x-detail>
                    <x-detail label="IFSC">{{ $employee->ifsc }}</x-detail>
                </dl>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Attendance this month <span class="text-sm font-normal text-slate-500">({{ now()->format('F Y') }}, to date)</span></h2></div>
                <div class="flex flex-wrap gap-2 p-5">
                    @forelse ($attendanceSummary as $status => $count)
                        <span class="badge {{ Attendance::tone($status) }}">{{ Attendance::STATUSES[$status]['label'] ?? $status }}: {{ $count }}</span>
                    @empty
                        <p class="text-sm text-slate-500">No attendance marked yet this month.</p>
                    @endforelse
                </div>
            </div>

            @module('hr_payroll', 'view')
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Recent salary slips</h2></div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($payslips as $slip)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                <span>{{ $slip->run->label() }} <span class="badge ml-1 {{ $slip->run->statusTone() }}">{{ $slip->run->statusLabel() }}</span></span>
                                <span class="flex items-center gap-3">
                                    <span class="font-semibold tabular-nums">₹{{ Money::format($slip->net_pay, 0) }}</span>
                                    <a href="{{ route('hr.payslips.show', $slip) }}" class="btn btn-secondary btn-sm" title="Salary slip"><x-icon name="document" class="h-4 w-4" /></a>
                                </span>
                            </li>
                        @empty
                            <li class="px-5 py-4 text-sm text-slate-500">No payroll generated yet.</li>
                        @endforelse
                    </ul>
                </div>
            @endmodule
        </div>

        <a href="{{ route('hr.employees.index') }}" class="inline-block text-sm font-semibold text-brand-600 hover:underline">← Back to employees</a>
    </div>
@endsection
