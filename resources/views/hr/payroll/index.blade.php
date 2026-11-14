@extends('layouts.app')

@section('title', 'Payroll')
@section('breadcrumb', 'HR & Payroll / Payroll & Salary Slips')

@use('App\Support\Money')

@section('content')
    <div class="space-y-6">
        @module('hr_payroll', 'create')
            <form method="POST" action="{{ route('hr.payroll.store') }}" class="card">
                @csrf
                <div class="card-header"><h2 class="card-title">Generate payroll</h2></div>
                <div class="flex flex-wrap items-end gap-4 p-5">
                    <div>
                        <label for="month" class="form-label">Month</label>
                        <input id="month" name="month" type="month" value="{{ old('month', $suggestedMonth) }}" max="{{ now()->format('Y-m') }}" required class="form-input w-auto">
                        @error('month') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <button class="btn btn-primary"><x-icon name="banknotes" class="h-4 w-4" /> Generate</button>
                    <p class="max-w-xl text-xs text-slate-500">Creates a draft payslip for every employee on the rolls that month, from their salary structure and attendance. You can adjust bonus, TDS and advances, recalculate after fixing attendance, then finalize.</p>
                </div>
            </form>
        @endmodule

        <div class="card">
            <div class="card-header"><h2 class="card-title">Payroll runs</h2></div>
            @module('hr_payroll', 'view')
                <div class="overflow-x-auto">
                    <table class="table-classic">
                        <thead>
                        <tr>
                            <th>Month</th>
                            <th class="text-center">Employees</th>
                            <th class="text-right">Gross</th>
                            <th class="text-right">Deductions</th>
                            <th class="text-right">Net pay</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($runs as $run)
                            <tr>
                                <td><a href="{{ route('hr.payroll.show', $run) }}" class="font-semibold text-slate-800 hover:text-brand-600 hover:underline">{{ $run->label() }}</a></td>
                                <td class="text-center tabular-nums">{{ $run->payslips_count }}</td>
                                <td class="text-right tabular-nums">₹{{ Money::format($run->payslips_sum_gross_earnings, 0) }}</td>
                                <td class="text-right tabular-nums">₹{{ Money::format($run->payslips_sum_total_deductions, 0) }}</td>
                                <td class="text-right font-semibold tabular-nums">₹{{ Money::format($run->payslips_sum_net_pay, 0) }}</td>
                                <td><span class="badge {{ $run->statusTone() }}">{{ $run->statusLabel() }}</span></td>
                                <td>
                                    <div class="flex justify-end gap-1.5">
                                        <a href="{{ route('hr.payroll.show', $run) }}" class="btn btn-secondary btn-sm" title="Open"><x-icon name="eye" class="h-4 w-4" /></a>
                                        <a href="{{ route('hr.payroll.slips', $run) }}" class="btn btn-secondary btn-sm" title="Print all salary slips" target="_blank"><x-icon name="printer" class="h-4 w-4" /></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-10 text-center text-sm text-slate-500">No payroll generated yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($runs->hasPages())<div class="border-t border-slate-200 px-5 py-3">{{ $runs->links() }}</div>@endif
            @else
                <p class="px-5 py-10 text-center text-sm text-slate-500">You don't have permission to view these records.</p>
            @endmodule
        </div>
    </div>
@endsection
