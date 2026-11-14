<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Masters\Concerns\HandlesMasterForms;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    use HandlesMasterForms;

    public function index(Request $request)
    {
        $employees = Employee::with('shift')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('phone', 'like', $term)
                    ->orWhere('designation', 'like', $term));
            })
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->department))
            ->when($request->filled('type'), fn ($q) => $q->where('employment_type', $request->type))
            ->when($request->input('status', 'active') !== 'all', fn ($q) => $q->where('status', $request->input('status', 'active')))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('hr.employees.index', ['employees' => $employees, 'departments' => $this->distinct('department')]);
    }

    public function show(Employee $employee)
    {
        $month = now()->startOfMonth();
        $attendance = $employee->attendances()->betweenDates($month, now())->get();

        return view('hr.employees.show', [
            'employee' => $employee->load('shift', 'creator:id,name'),
            'attendanceSummary' => $attendance->countBy('status'),
            'todayShift' => $employee->shiftOn(now()),
            'payslips' => $employee->payslips()->with('run')->latest('id')->take(6)->get(),
        ]);
    }

    public function create()
    {
        return view('hr.employees.form', $this->formData(new Employee([
            'employment_type' => 'permanent', 'status' => 'active', 'weekly_off' => 0, 'joining_date' => now(),
            'pf_applicable' => true, 'esi_applicable' => true, 'professional_tax' => config('payroll.default_professional_tax'),
        ]), Employee::nextCode()));
    }

    public function store(Request $request)
    {
        $employee = new Employee($this->validated($request));
        $employee->created_by = $request->user()->id;
        $this->syncImage($request, $employee, 'employees');
        $employee->save();

        return redirect()->route('hr.employees.show', $employee)->with('success', "Employee \"{$employee->code} – {$employee->name}\" added.");
    }

    public function edit(Employee $employee)
    {
        return view('hr.employees.form', $this->formData($employee, null));
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->fill($this->validated($request));
        $this->syncImage($request, $employee, 'employees');
        $employee->save();

        return redirect()->route('hr.employees.show', $employee)->with('success', "Employee \"{$employee->code}\" updated.");
    }

    public function destroy(Employee $employee)
    {
        if ($employee->payslips()->exists()) {
            return back()->with('error', "{$employee->name} has payslips, so the record can't be deleted. Set the status to Left instead.");
        }

        $employee->delete();

        return redirect()->route('hr.employees.index')->with('success', "Employee \"{$employee->code}\" deleted.");
    }

    protected function formData(Employee $employee, ?string $nextCode): array
    {
        return [
            'employee' => $employee,
            'nextCode' => $nextCode,
            'shifts' => Shift::where('is_active', true)->orWhere('id', $employee->shift_id)->orderBy('start_time')->get(),
            'departments' => $this->distinct('department'),
            'designations' => $this->distinct('designation'),
        ];
    }

    /** Values already used, offered as suggestions. */
    protected function distinct(string $column)
    {
        return Employee::whereNotNull($column)->distinct()->orderBy($column)->pluck($column);
    }

    protected function validated(Request $request): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:10000000'];

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(array_keys(Employee::GENDERS))],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'department' => ['nullable', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', Rule::in(array_keys(Employee::EMPLOYMENT_TYPES))],
            'joining_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(Employee::STATUSES))],
            'leaving_date' => ['nullable', 'required_if:status,left', 'date', 'after_or_equal:joining_date'],
            'weekly_off' => ['required', 'integer', Rule::in(array_keys(Employee::WEEKDAYS))],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            ...array_fill_keys(array_keys(Employee::SALARY_COMPONENTS), $money),
            'ot_rate_per_hour' => $money,
            'pf_applicable' => ['nullable', 'boolean'],
            'esi_applicable' => ['nullable', 'boolean'],
            'professional_tax' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'uan' => ['nullable', 'digits:12'],
            'pf_number' => ['nullable', 'string', 'max:30'],
            'esi_number' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'aadhaar' => ['nullable', 'digits:12'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z]+$/'],
            'ifsc' => ['nullable', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            ...$this->imageRules(),
        ], [
            'leaving_date.required_if' => 'Enter the last working day for an employee who has left.',
            'pan.regex' => 'PAN must look like ABCDE1234F.',
            'ifsc.regex' => 'IFSC must look like SBIN0001234.',
        ], [
            'date_of_birth' => 'date of birth',
            'joining_date' => 'joining date',
            'leaving_date' => 'leaving date',
            'shift_id' => 'default shift',
            'hra' => 'HRA',
            'ot_rate_per_hour' => 'overtime rate',
            'uan' => 'UAN',
            'pan' => 'PAN',
            'ifsc' => 'IFSC',
        ]);

        $data = $request->only([
            'name', 'father_name', 'gender', 'date_of_birth', 'phone', 'email', 'address', 'department', 'designation',
            'employment_type', 'joining_date', 'status', 'weekly_off', 'shift_id', 'ot_rate_per_hour',
            'uan', 'pf_number', 'esi_number', 'aadhaar', 'bank_name', 'bank_account', 'remarks',
        ]);

        foreach (array_keys(Employee::SALARY_COMPONENTS) as $component) {
            $data[$component] = $request->input($component) ?: 0;
        }

        return $data + [
            'leaving_date' => $request->input('status') === 'left' ? $request->input('leaving_date') : null,
            'pf_applicable' => $request->boolean('pf_applicable'),
            'esi_applicable' => $request->boolean('esi_applicable'),
            'professional_tax' => $request->input('professional_tax') ?: 0,
            'pan' => $request->filled('pan') ? strtoupper($request->pan) : null,
            'ifsc' => $request->filled('ifsc') ? strtoupper($request->ifsc) : null,
        ];
    }
}
