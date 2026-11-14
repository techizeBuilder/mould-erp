<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payslip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out one employee's payslip for a month from their salary structure and attendance.
 *
 * - Paid days = days with a paid attendance status (present, half day = ½, paid leave, weekly off, holiday).
 *   An unmarked day on the employee's weekly off counts as paid; any other unmarked day is unpaid and
 *   reported as "unmarked" so HR can fix attendance before finalizing. Days before joining / after
 *   leaving are unpaid.
 * - Each salary component is paid pro rata: amount × paid days ÷ days in month.
 * - PF on earned Basic up to the wage ceiling; ESI on gross when the monthly salary is within the limit;
 *   Professional Tax as set on the employee. Rates come from config/payroll.php.
 * - Bonus, TDS, advance and other deductions are entered by HR and kept when recalculating.
 */
class PayrollCalculator
{
    public static function fill(Payslip $slip, Employee $employee, Carbon $month): Payslip
    {
        $month = $month->copy()->startOfMonth();
        $days = $month->daysInMonth;

        $attendance = $employee->attendances()
            ->betweenDates($month, $month->copy()->endOfMonth())
            ->get();
        ['paid' => $paid, 'unmarked' => $unmarked] = self::countDays($employee, $month, $attendance);

        $ratio = $paid / $days;
        $fixedGross = $employee->fixedGross();
        $otHours = (float) $attendance->sum('overtime_hours');
        $otRate = $employee->ot_rate_per_hour !== null
            ? (float) $employee->ot_rate_per_hour
            : $fixedGross / $days / config('payroll.standard_hours_per_day') * config('payroll.overtime_multiplier');

        $slip->fill([
            'employee_id' => $employee->id,
            'employee_snapshot' => self::snapshot($employee),
            'days_in_month' => $days,
            'paid_days' => $paid,
            'lop_days' => $days - $paid,
            'unmarked_days' => $unmarked,
            'overtime_hours' => $otHours,
            'fixed_gross' => $fixedGross,
            'overtime_pay' => round($otHours * $otRate, 2),
        ]);
        foreach (array_keys(Employee::SALARY_COMPONENTS) as $component) {
            $slip->{$component} = round((float) $employee->{$component} * $ratio, 2);
        }
        foreach (Payslip::ADJUSTMENTS as $field) {
            $slip->{$field} ??= 0;
        }

        $gross = round(collect(array_keys(Payslip::EARNINGS))->sum(fn ($f) => (float) $slip->{$f}), 2);

        $pfWage = min((float) $slip->basic, config('payroll.pf.wage_ceiling'));
        $pf = $employee->pf_applicable ? round($pfWage * config('payroll.pf.employee_rate') / 100) : 0;
        $employerPf = $employee->pf_applicable ? round($pfWage * config('payroll.pf.employer_rate') / 100) : 0;

        // ESI is rounded up to the next rupee.
        $esiCovered = $employee->esi_applicable && $fixedGross <= config('payroll.esi.wage_limit') && $gross > 0;
        $esi = $esiCovered ? ceil($gross * config('payroll.esi.employee_rate') / 100) : 0;
        $employerEsi = $esiCovered ? ceil($gross * config('payroll.esi.employer_rate') / 100) : 0;

        $slip->fill([
            'gross_earnings' => $gross,
            'pf' => $pf,
            'esi' => $esi,
            'professional_tax' => $paid > 0 ? (float) $employee->professional_tax : 0,
            'employer_pf' => $employerPf,
            'employer_esi' => $employerEsi,
        ]);

        return self::totals($slip);
    }

    /**
     * Paid and unmarked days in the month from the employee's attendance records
     * (shared with the muster roll so both always agree).
     *
     * @param  Collection<int, Attendance>  $attendance  the employee's records for the month
     * @return array{paid: float, unmarked: int, implied_off: int}
     */
    public static function countDays(Employee $employee, Carbon $month, Collection $attendance): array
    {
        $byDate = $attendance->keyBy(fn (Attendance $a) => $a->date->toDateString());
        $paid = 0.0;
        $unmarked = $impliedOff = 0;

        for ($day = $month->copy()->startOfMonth(); $day->month === $month->month; $day->addDay()) {
            if (! $employee->isEmployedOn($day)) {
                continue;
            }
            if ($record = $byDate->get($day->toDateString())) {
                $paid += $record->paidWeight();
            } elseif ($employee->isWeeklyOff($day)) {
                $paid += 1;
                $impliedOff++;
            } else {
                $unmarked++;
            }
        }

        return ['paid' => $paid, 'unmarked' => $unmarked, 'implied_off' => $impliedOff];
    }

    /** Re-adds deductions and net pay (after HR edits an adjustment). */
    public static function totals(Payslip $slip): Payslip
    {
        $gross = round(collect(array_keys(Payslip::EARNINGS))->sum(fn ($f) => (float) $slip->{$f}), 2);
        $deductions = round(collect(array_keys(Payslip::DEDUCTIONS))->sum(fn ($f) => (float) $slip->{$f}), 2);

        $slip->gross_earnings = $gross;
        $slip->total_deductions = $deductions;
        $slip->net_pay = round($gross - $deductions);

        return $slip;
    }

    protected static function snapshot(Employee $e): array
    {
        return [
            'code' => $e->code,
            'name' => $e->name,
            'designation' => $e->designation,
            'department' => $e->department,
            'joining_date' => $e->joining_date?->toDateString(),
            'gender' => $e->genderLabel(),
            'uan' => $e->uan,
            'pf_number' => $e->pf_number,
            'esi_number' => $e->esi_number,
            'pan' => $e->pan,
            'bank_name' => $e->bank_name,
            'bank_account' => $e->bank_account,
            'ifsc' => $e->ifsc,
            // Full-month rates, shown beside the earned amounts on the slip.
            'rates' => collect(array_keys(Employee::SALARY_COMPONENTS))->mapWithKeys(fn ($c) => [$c => (float) $e->{$c}])->all(),
        ];
    }
}
