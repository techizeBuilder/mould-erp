<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    /** Earnings in slip order: the employee's salary components, then overtime and bonus. */
    public const EARNINGS = Employee::SALARY_COMPONENTS + [
        'overtime_pay' => 'Overtime',
        'bonus' => 'Bonus / Incentive',
    ];

    public const DEDUCTIONS = [
        'pf' => 'Provident Fund',
        'esi' => 'ESI',
        'professional_tax' => 'Professional Tax',
        'tds' => 'TDS (Income Tax)',
        'advance' => 'Salary Advance / Loan',
        'other_deduction' => 'Other Deduction',
    ];

    /** Amounts HR enters by hand on a draft payslip; everything else is calculated. */
    public const ADJUSTMENTS = ['bonus', 'tds', 'advance', 'other_deduction'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        $money = array_fill_keys([
            'paid_days', 'lop_days', 'overtime_hours', 'fixed_gross', ...array_keys(self::EARNINGS), 'gross_earnings',
            ...array_keys(self::DEDUCTIONS), 'total_deductions', 'net_pay', 'employer_pf', 'employer_esi',
        ], 'decimal:2');

        return ['employee_snapshot' => 'array', 'days_in_month' => 'integer', 'unmarked_days' => 'integer'] + $money;
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** A detail of the employee as it was when the payslip was generated. */
    public function snap(string $key): ?string
    {
        $value = $this->employee_snapshot[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /** Full-month rate of a salary component when the slip was generated. */
    public function rate(string $component): ?float
    {
        return $this->employee_snapshot['rates'][$component] ?? null;
    }
}
