@extends('layouts.app')

@section('title', 'Adjust Payslip')
@section('breadcrumb', 'HR & Payroll / Payroll / '.$payslip->run->label().' / '.$payslip->snap('name'))

@use('App\Support\Money')

@section('content')
    <form method="POST" action="{{ route('hr.payslips.update', $payslip) }}" class="max-w-3xl space-y-6">
        @csrf @method('PUT')
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">{{ $payslip->snap('code') }} – {{ $payslip->snap('name') }}</h2>
                    <p class="text-xs text-slate-500">{{ $payslip->run->label() }} · paid {{ (float) $payslip->paid_days }} of {{ $payslip->days_in_month }} days · gross ₹{{ Money::format($payslip->gross_earnings) }} · net ₹{{ Money::format($payslip->net_pay) }}</p>
                </div>
            </div>
            <div class="grid gap-5 p-5 md:grid-cols-2">
                @foreach (['bonus' => ['Bonus / incentive (₹)', 'Added to earnings.'], 'tds' => ['TDS — income tax (₹)', 'Deducted.'], 'advance' => ['Salary advance / loan recovery (₹)', 'Deducted.'], 'other_deduction' => ['Other deduction (₹)', 'Deducted — e.g. canteen, uniform.']] as $field => [$label, $hint])
                    <div>
                        <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="number" step="0.01" min="0" value="{{ old($field, (float) $payslip->{$field} ?: null) }}" class="form-input" placeholder="0">
                        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
                        @error($field) <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                <div class="md:col-span-2">
                    <label for="remarks" class="form-label">Note on payslip</label>
                    <input id="remarks" name="remarks" value="{{ old('remarks', $payslip->remarks) }}" maxlength="255" class="form-input" placeholder="e.g. Diwali bonus">
                    @error('remarks') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
        <p class="text-xs text-slate-500">PF, ESI and Professional Tax are calculated automatically when you save. Salary components follow attendance — fix attendance and Recalculate to change paid days.</p>
        <div class="flex justify-end gap-2">
            <a href="{{ route('hr.payroll.show', $payslip->run) }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Save &amp; recalculate</button>
        </div>
    </form>
@endsection
