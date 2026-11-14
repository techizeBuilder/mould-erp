<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Support\PayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    /** Daily sheet: every employee on the rolls that day, with their shift and attendance. */
    public function index(Request $request)
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()))->startOfDay();

        $employees = $this->employeesOn($date, $request->input('department'));
        $records = Attendance::whereDate('date', $date)->whereIn('employee_id', $employees->modelKeys())->get()->keyBy('employee_id');

        $rows = $employees->map(fn (Employee $e) => [
            'employee' => $e,
            'shift' => $e->shiftOn($date, $e->shiftAssignments),
            'record' => $records->get($e->id),
            // Suggested status for a day not marked yet.
            'suggested' => $e->isWeeklyOff($date) ? 'weekly_off' : 'present',
        ]);

        return view('hr.attendance.index', [
            'date' => $date,
            'rows' => $rows,
            'summary' => $records->countBy('status'),
            'unmarked' => $employees->count() - $records->count(),
            'departments' => Employee::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date', 'before_or_equal:'.now()->addDays(31)->toDateString()],
            'rows' => ['required', 'array'],
            'rows.*.status' => ['nullable', Rule::in(array_keys(Attendance::STATUSES))],
            'rows.*.in_time' => ['nullable', 'date_format:H:i'],
            'rows.*.out_time' => ['nullable', 'date_format:H:i'],
            'rows.*.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'rows.*.remarks' => ['nullable', 'string', 'max:255'],
        ], ['date.before_or_equal' => 'Attendance can be marked at most a month ahead (e.g. planned leave).'], [
            'rows.*.status' => 'status', 'rows.*.in_time' => 'in time', 'rows.*.out_time' => 'out time', 'rows.*.overtime_hours' => 'overtime',
        ]);

        $date = Carbon::parse($request->date)->startOfDay();
        $user = $request->user();
        $employees = $this->employeesOn($date)->keyBy('id');
        $existing = Attendance::whereDate('date', $date)->whereIn('employee_id', $employees->keys())->get()->keyBy('employee_id');
        $saved = $removed = 0;

        DB::transaction(function () use ($request, $date, $user, $employees, $existing, &$saved, &$removed) {
            foreach ($request->input('rows') as $employeeId => $row) {
                $employee = $employees->get((int) $employeeId);
                if (! $employee) {
                    continue; // not on the rolls that day
                }
                $record = $existing->get($employee->id);

                if (empty($row['status'])) {
                    if ($record) {
                        $this->requireRight($user, 'delete', 'clear attendance that was already marked');
                        $record->delete();
                        $removed++;
                    }

                    continue;
                }

                $shift = $employee->shiftOn($date, $employee->shiftAssignments);
                $worked = in_array($row['status'], ['present', 'half_day'], true);
                $in = $worked ? ($row['in_time'] ?? null) : null;
                $out = $worked ? ($row['out_time'] ?? null) : null;
                $overtime = $worked
                    ? (($row['overtime_hours'] ?? '') !== '' ? (float) $row['overtime_hours'] : (Attendance::overtimeFrom($in, $out, $shift) ?? 0))
                    : 0;

                $values = [
                    'status' => $row['status'],
                    'shift_id' => $shift?->id,
                    'in_time' => $in,
                    'out_time' => $out,
                    'overtime_hours' => $overtime,
                    'remarks' => $row['remarks'] ?? null,
                ];

                $record ??= new Attendance(['employee_id' => $employee->id, 'date' => $date->toDateString()]);
                $record->fill($values);
                if (! $record->exists || $record->isDirty()) {
                    $this->requireRight($user, $record->exists ? 'edit' : 'create', $record->exists ? 'change attendance that was already marked' : 'mark attendance');
                    $record->marked_by = $user->id;
                    $record->save();
                    $saved++;
                }
            }
        });

        return redirect()->route('hr.attendance.index', array_filter(['date' => $date->toDateString(), 'department' => $request->input('department')]))
            ->with('success', "Attendance for {$date->format('d M Y')} saved: {$saved} updated".($removed ? ", {$removed} cleared" : '').'.');
    }

    /** Monthly muster roll: employees × days with status codes and totals. */
    public function register(Request $request)
    {
        $month = Carbon::parse(($request->input('month') ?: now()->format('Y-m')).'-01')->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $days = collect(range(1, $month->daysInMonth))->map(fn ($d) => $month->copy()->day($d));

        $employees = Employee::with(['attendances' => fn ($q) => $q->betweenDates($month, $end)])
            ->employedBetween($month, $end)
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->department))
            ->orderBy('code')
            ->get();

        $rows = $employees->map(fn (Employee $e) => [
            'employee' => $e,
            'byDate' => $e->attendances->keyBy(fn ($a) => $a->date->toDateString()),
            'counts' => $e->attendances->countBy('status'),
            'overtime' => (float) $e->attendances->sum('overtime_hours'),
        ] + PayrollCalculator::countDays($e, $month, $e->attendances));

        return view('hr.attendance.register', [
            'month' => $month,
            'days' => $days,
            'rows' => $rows,
            'departments' => Employee::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
        ]);
    }

    protected function employeesOn(Carbon $date, ?string $department = null)
    {
        return Employee::with(['shift', 'shiftAssignments' => fn ($q) => $q->with('shift')->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)])
            ->employedBetween($date, $date)
            ->when($department, fn ($q) => $q->where('department', $department))
            ->orderBy('code')
            ->get();
    }

    protected function requireRight($user, string $action, string $what): void
    {
        if (! $user->canModule('hr_attendance', $action)) {
            throw ValidationException::withMessages(['rows' => "You don't have the right to {$what}. Nothing was saved."]);
        }
    }
}
