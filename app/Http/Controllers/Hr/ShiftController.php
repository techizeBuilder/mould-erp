<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    /** Weekly roster (who works which shift each day) plus the list of shifts. */
    public function index(Request $request)
    {
        $weekStart = Carbon::parse($request->input('week', now()->toDateString()))->startOfWeek();
        $days = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));
        $weekEnd = $days->last();

        $employees = Employee::with(['shift', 'shiftAssignments' => fn ($q) => $q->with('shift')
            ->whereDate('start_date', '<=', $weekEnd)->whereDate('end_date', '>=', $weekStart)])
            ->employedBetween($weekStart, $weekEnd)
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->department))
            ->orderBy('code')
            ->get();

        return view('hr.shifts.index', [
            'weekStart' => $weekStart,
            'days' => $days,
            'employees' => $employees,
            'shifts' => Shift::withCount('employees')->orderBy('start_time')->get(),
            'departments' => Employee::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
        ]);
    }

    public function create()
    {
        return view('hr.shifts.form', ['shift' => new Shift(['break_minutes' => 30, 'grace_minutes' => 10, 'colour' => 'sky', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $shift = Shift::create($this->validated($request));

        return redirect()->route('hr.shifts.index')->with('success', "Shift \"{$shift->name}\" added.");
    }

    public function edit(Shift $shift)
    {
        return view('hr.shifts.form', ['shift' => $shift]);
    }

    public function update(Request $request, Shift $shift)
    {
        $shift->update($this->validated($request, $shift));

        return redirect()->route('hr.shifts.index')->with('success', "Shift \"{$shift->name}\" updated.");
    }

    public function destroy(Shift $shift)
    {
        if ($shift->employees()->exists() || $shift->assignments()->exists()) {
            return back()->with('error', "Shift \"{$shift->name}\" is still assigned to employees. Mark it inactive instead, or move them to another shift first.");
        }

        $shift->delete();

        return redirect()->route('hr.shifts.index')->with('success', "Shift \"{$shift->name}\" deleted.");
    }

    /** Put employees on one shift for a date range (or back on their default shift). */
    public function assignForm(Request $request)
    {
        return view('hr.shifts.assign', [
            'employees' => $this->activeEmployees(),
            'shifts' => Shift::where('is_active', true)->orderBy('start_time')->get(),
            'selected' => array_map('intval', (array) $request->input('employee_ids', [])),
        ]);
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:'.Carbon::parse($request->input('start_date', 'today'))->addYear()->toDateString()],
        ], [
            'employee_ids.required' => 'Select at least one employee.',
            'end_date.before_or_equal' => 'Assign at most one year at a time.',
        ], ['shift_id' => 'shift', 'start_date' => 'from date', 'end_date' => 'to date']);

        [$start, $end] = [Carbon::parse($data['start_date']), Carbon::parse($data['end_date'])];

        DB::transaction(function () use ($data, $start, $end, $request) {
            foreach (array_unique($data['employee_ids']) as $id) {
                $data['shift_id']
                    ? ShiftAssignment::assign($id, $data['shift_id'], $start, $end, $request->user()->id)
                    : ShiftAssignment::clear($id, $start, $end);
            }
        });

        $count = count(array_unique($data['employee_ids']));
        $what = $data['shift_id'] ? 'assigned to '.Shift::find($data['shift_id'])->name : 'moved back to their default shift';

        return redirect()->route('hr.shifts.index', ['week' => $start->toDateString()])
            ->with('success', "{$count} employee(s) {$what} from {$start->format('d M Y')} to {$end->format('d M Y')}.");
    }

    /** Rotate employees through a sequence of shifts, changing every N days. */
    public function rotateForm()
    {
        return view('hr.shifts.rotate', [
            'employees' => $this->activeEmployees(),
            'shifts' => Shift::where('is_active', true)->orderBy('start_time')->get(),
        ]);
    }

    public function rotate(Request $request)
    {
        $request->merge(['sequence' => array_values(array_filter((array) $request->input('sequence', [])))]);

        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'sequence' => ['required', 'array', 'min:2'],
            'sequence.*' => ['integer', Rule::exists('shifts', 'id')->where('is_active', true)],
            'every_days' => ['required', 'integer', 'min:1', 'max:90'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:'.Carbon::parse($request->input('start_date', 'today'))->addYear()->toDateString()],
            'stagger' => ['nullable', 'boolean'],
        ], [
            'employee_ids.required' => 'Select at least one employee.',
            'sequence.min' => 'Choose at least two shifts to rotate between.',
            'end_date.before_or_equal' => 'Plan at most one year at a time.',
        ], ['every_days' => 'rotation period', 'start_date' => 'from date', 'end_date' => 'to date']);

        [$start, $end] = [Carbon::parse($data['start_date']), Carbon::parse($data['end_date'])];
        $sequence = $data['sequence'];
        $employeeIds = array_values(array_unique($data['employee_ids']));

        DB::transaction(function () use ($employeeIds, $sequence, $data, $start, $end, $request) {
            foreach ($employeeIds as $position => $id) {
                // Staggered: the 1st employee starts on the 1st shift, the 2nd on the 2nd shift, and so on.
                $turn = $request->boolean('stagger') ? $position : 0;
                for ($from = $start->copy(); $from->lte($end); $turn++) {
                    $to = $from->copy()->addDays($data['every_days'] - 1)->min($end);
                    ShiftAssignment::assign($id, $sequence[$turn % count($sequence)], $from, $to, $request->user()->id);
                    $from = $to->copy()->addDay();
                }
            }
        });

        $names = Shift::whereIn('id', $sequence)->pluck('name', 'id');

        return redirect()->route('hr.shifts.index', ['week' => $start->toDateString()])->with('success',
            count($employeeIds).' employee(s) rotated '.collect($sequence)->map(fn ($id) => $names[$id])->join(' → ')
            ." every {$data['every_days']} day(s), {$start->format('d M Y')} to {$end->format('d M Y')}.");
    }

    protected function activeEmployees()
    {
        return Employee::with('shift')->where('status', 'active')->orderBy('code')->get();
    }

    protected function validated(Request $request, ?Shift $shift = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('shifts')->ignore($shift)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'colour' => ['required', Rule::in(array_keys(Shift::COLOURS))],
            'is_active' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], ['end_time.different' => 'The shift must end at a different time from when it starts.'], [
            'start_time' => 'start time', 'end_time' => 'end time', 'break_minutes' => 'break', 'grace_minutes' => 'grace time',
        ]);

        if (Shift::minutesBetween($data['start_time'], $data['end_time']) <= $data['break_minutes']) {
            throw ValidationException::withMessages(['break_minutes' => 'The break is longer than the shift.']);
        }

        return ['is_active' => $request->boolean('is_active')] + $data;
    }
}
