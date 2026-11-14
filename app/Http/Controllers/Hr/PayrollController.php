<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Support\PayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function index()
    {
        $runs = PayrollRun::withCount('payslips')
            ->withSum('payslips', 'gross_earnings')
            ->withSum('payslips', 'total_deductions')
            ->withSum('payslips', 'net_pay')
            ->orderByDesc('month')
            ->paginate(12);

        return view('hr.payroll.index', [
            'runs' => $runs,
            'suggestedMonth' => now()->subMonthNoOverflow()->format('Y-m'),
        ]);
    }

    /** Generate payslips for every employee on the rolls during the month. */
    public function store(Request $request)
    {
        $request->validate([
            'month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
        ], ['month.before_or_equal' => 'Payroll can only be generated for the current or a past month.']);

        $month = Carbon::parse($request->month.'-01')->startOfMonth();
        if ($existing = PayrollRun::whereDate('month', $month)->first()) {
            return redirect()->route('hr.payroll.show', $existing)->with('error', "Payroll for {$existing->label()} already exists — recalculate it instead.");
        }

        $employees = $this->employeesFor($month);
        if ($employees->isEmpty()) {
            return back()->withInput()->with('error', "No employees were on the rolls in {$month->format('F Y')}.");
        }

        $run = DB::transaction(function () use ($month, $employees, $request) {
            $run = PayrollRun::create(['month' => $month->toDateString(), 'status' => 'draft', 'created_by' => $request->user()->id]);
            foreach ($employees as $employee) {
                PayrollCalculator::fill(new Payslip(['payroll_run_id' => $run->id]), $employee, $month)->save();
            }

            return $run;
        });

        return redirect()->route('hr.payroll.show', $run)->with('success', "Payroll for {$run->label()} generated for {$employees->count()} employee(s). Review it, then finalize.");
    }

    public function show(PayrollRun $run)
    {
        $run->load(['payslips' => fn ($q) => $q->with('employee:id,code,name,department')->orderBy('employee_id'), 'creator:id,name', 'finalizer:id,name']);

        $totals = collect([...array_keys(Payslip::EARNINGS), 'gross_earnings', ...array_keys(Payslip::DEDUCTIONS), 'total_deductions', 'net_pay', 'employer_pf', 'employer_esi'])
            ->mapWithKeys(fn ($f) => [$f => $run->payslips->sum(fn ($s) => (float) $s->{$f})]);

        // Employees who joined after the payroll was generated.
        $missing = $run->isFinalized() ? collect() : $this->employeesFor($run->month)->whereNotIn('id', $run->payslips->pluck('employee_id'));

        return view('hr.payroll.show', ['run' => $run, 'totals' => $totals, 'missing' => $missing]);
    }

    /** Re-reads attendance and salary structure; HR's manual adjustments are kept. */
    public function recalculate(PayrollRun $run)
    {
        abort_if($run->isFinalized(), 403, 'This payroll is finalized.');

        DB::transaction(function () use ($run) {
            $slips = $run->payslips()->get()->keyBy('employee_id');
            $employees = $this->employeesFor($run->month);

            foreach ($employees as $employee) {
                PayrollCalculator::fill($slips->get($employee->id) ?? new Payslip(['payroll_run_id' => $run->id]), $employee, $run->month)->save();
            }
            // Employees no longer on the rolls that month (e.g. joining date corrected).
            $run->payslips()->whereNotIn('employee_id', $employees->modelKeys())->delete();
            $run->touch();
        });

        return back()->with('success', "Payroll for {$run->label()} recalculated from the latest attendance and salary details.");
    }

    public function finalize(PayrollRun $run, Request $request)
    {
        abort_if($run->isFinalized(), 403, 'This payroll is already finalized.');

        $run->forceFill(['status' => 'finalized', 'finalized_at' => now(), 'finalized_by' => $request->user()->id])->save();

        return back()->with('success', "Payroll for {$run->label()} finalized. Salary slips are now locked.");
    }

    /** Only the super admin can unlock a finalized payroll. */
    public function reopen(PayrollRun $run, Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only the super admin can reopen a finalized payroll.');

        $run->forceFill(['status' => 'draft', 'finalized_at' => null, 'finalized_by' => null])->save();

        return back()->with('success', "Payroll for {$run->label()} reopened for changes.");
    }

    public function destroy(PayrollRun $run)
    {
        abort_if($run->isFinalized(), 403, 'A finalized payroll cannot be deleted.');

        $run->delete();

        return redirect()->route('hr.payroll.index')->with('success', "Draft payroll for {$run->label()} deleted.");
    }

    /** Salary register as CSV (opens in Excel). */
    public function export(PayrollRun $run): StreamedResponse
    {
        $run->load(['payslips' => fn ($q) => $q->orderBy('employee_id')]);
        $columns = ['days_in_month' => 'Days', 'paid_days' => 'Paid Days', 'lop_days' => 'LOP Days', 'overtime_hours' => 'OT Hours',
            ...Payslip::EARNINGS, 'gross_earnings' => 'Gross Earnings', ...Payslip::DEDUCTIONS, 'total_deductions' => 'Total Deductions',
            'net_pay' => 'Net Pay', 'employer_pf' => 'Employer PF', 'employer_esi' => 'Employer ESI'];

        return response()->streamDownload(function () use ($run, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Name', 'Designation', 'Department', 'UAN', 'ESI No.', 'Bank A/c', 'IFSC', ...array_values($columns)]);
            foreach ($run->payslips as $s) {
                fputcsv($out, [$s->snap('code'), $s->snap('name'), $s->snap('designation'), $s->snap('department'), $s->snap('uan'),
                    $s->snap('esi_number'), $s->snap('bank_account'), $s->snap('ifsc'), ...array_map(fn ($f) => $s->{$f}, array_keys($columns))]);
            }
            fclose($out);
        }, 'salary-register-'.$run->month->format('Y-m').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Every salary slip of the month on one page, one slip per printed page. */
    public function slips(PayrollRun $run)
    {
        return view('hr.payroll.slips', [
            'run' => $run,
            'payslips' => $run->payslips()->orderBy('employee_id')->get(),
        ]);
    }

    protected function employeesFor(Carbon $month)
    {
        return Employee::employedBetween($month->copy()->startOfMonth(), $month->copy()->endOfMonth())->orderBy('code')->get();
    }
}
