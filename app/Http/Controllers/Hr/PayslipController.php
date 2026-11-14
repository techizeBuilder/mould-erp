<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Support\Money;
use App\Support\PayrollCalculator;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    /** The printable salary slip. */
    public function show(Payslip $payslip)
    {
        return view('hr.payroll.slips', ['run' => $payslip->run, 'payslips' => collect([$payslip])]);
    }

    /** Bonus, TDS, advance and other deductions are entered by hand on a draft payslip. */
    public function edit(Payslip $payslip)
    {
        abort_if($payslip->run->isFinalized(), 403, 'This payroll is finalized.');

        return view('hr.payroll.payslip-edit', ['payslip' => $payslip]);
    }

    public function update(Request $request, Payslip $payslip)
    {
        abort_if($payslip->run->isFinalized(), 403, 'This payroll is finalized.');

        $data = $request->validate(
            array_fill_keys(Payslip::ADJUSTMENTS, ['nullable', 'numeric', 'min:0', 'max:10000000']) + ['remarks' => ['nullable', 'string', 'max:255']],
            [],
            ['tds' => 'TDS', 'other_deduction' => 'other deduction']
        );

        foreach (Payslip::ADJUSTMENTS as $field) {
            $payslip->{$field} = $data[$field] ?? 0;
        }
        $payslip->remarks = $data['remarks'] ?? null;
        // Full recalculation, since a bonus also changes ESI.
        PayrollCalculator::fill($payslip, $payslip->employee, $payslip->run->month)->save();

        return redirect()->route('hr.payroll.show', $payslip->run)->with('success', "Payslip of {$payslip->snap('name')} updated — net pay ₹".Money::format($payslip->net_pay, 0).'.');
    }
}
