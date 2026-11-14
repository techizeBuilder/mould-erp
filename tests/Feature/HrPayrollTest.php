<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected const HR_MODULES = ['hr_employees', 'hr_shifts', 'hr_attendance', 'hr_payroll'];

    protected function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

    /** A sub-admin with the given actions on each module (show is implied). */
    protected function subAdminWith(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $module => $actions) {
            $row = ['can_show' => true];
            foreach ($actions as $action) {
                $row["can_$action"] = true;
            }
            $user->permissions()->create(['module' => $module] + $row);
        }

        return $user;
    }

    protected function hrAdmin(): User
    {
        return $this->subAdminWith(array_fill_keys(self::HR_MODULES, ['view', 'create', 'edit', 'delete']));
    }

    protected function shift(array $extra = []): Shift
    {
        return Shift::create($extra + ['name' => 'General', 'start_time' => '08:00', 'end_time' => '16:30', 'break_minutes' => 30, 'grace_minutes' => 10, 'colour' => 'sky', 'is_active' => true]);
    }

    protected function employee(array $extra = []): Employee
    {
        return Employee::create($extra + [
            'name' => 'Ravi Kumar', 'employment_type' => 'permanent', 'joining_date' => '2026-01-01', 'weekly_off' => 0, 'status' => 'active',
            'basic' => 12000, 'hra' => 4000, 'special_allowance' => 2000, 'pf_applicable' => true, 'esi_applicable' => true, 'professional_tax' => 200,
        ]);
    }

    public function test_hr_admin_manages_employees_who_cannot_log_in(): void
    {
        $this->actingAs($hr = $this->hrAdmin());
        $users = User::count();

        $this->get('/dashboard')->assertOk()->assertSee('HR &amp; Payroll', false)->assertSee('Employees')->assertSee('Payroll &amp; Salary Slips', false);
        foreach (['/hr/employees', '/hr/employees/create', '/hr/shifts', '/hr/shifts/create', '/hr/shifts/assign', '/hr/shifts/rotate',
            '/hr/attendance', '/hr/attendance/register', '/hr/payroll'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->post('/hr/employees', [
            'name' => 'Sunita Devi', 'gender' => 'female', 'employment_type' => 'permanent', 'joining_date' => '2026-04-01', 'status' => 'active',
            'weekly_off' => 0, 'basic' => 15000, 'hra' => 6000, 'pf_applicable' => 1, 'esi_applicable' => 0, 'professional_tax' => 200,
            'pan' => 'abcde1234f', 'ifsc' => 'sbin0001234', 'uan' => '100200300400',
        ])->assertSessionHasNoErrors();

        $employee = Employee::first();
        $this->assertSame(['EMP-0001', 'ABCDE1234F', 'SBIN0001234'], [$employee->code, $employee->pan, $employee->ifsc]);
        $this->assertSame(21000.0, $employee->fixedGross());
        $this->assertFalse($employee->esi_applicable);
        $this->assertSame($users, User::count(), 'Employees are records, not login accounts.');
        $this->get("/hr/employees/{$employee->id}")->assertOk()->assertSee('Sunita Devi')->assertSee('21,000.00');
        $this->get("/hr/employees/{$employee->id}/edit")->assertOk();

        // HR rights don't reach other modules or user management.
        $this->get('/masters/moulds')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_employee_validation(): void
    {
        $this->actingAs($this->hrAdmin());
        $base = ['name' => 'X', 'employment_type' => 'permanent', 'joining_date' => '2026-04-01', 'weekly_off' => 0, 'basic' => 10000];

        $this->post('/hr/employees', $base + ['status' => 'left'])->assertSessionHasErrors('leaving_date');
        $this->post('/hr/employees', $base + ['status' => 'active', 'pan' => '12345'])->assertSessionHasErrors('pan');
        $this->post('/hr/employees', $base + ['status' => 'active', 'ifsc' => 'BAD'])->assertSessionHasErrors('ifsc');
        $this->post('/hr/employees', $base + ['status' => 'left', 'leaving_date' => '2026-03-01'])->assertSessionHasErrors('leaving_date');
        $this->assertSame(0, Employee::count());
    }

    public function test_rights_are_enforced_per_hr_module(): void
    {
        $employee = $this->employee();
        $this->actingAs($this->subAdminWith(['hr_employees' => ['view'], 'hr_attendance' => ['view']]));

        $this->get('/hr/employees')->assertOk()->assertDontSee('Add Employee');
        $this->get("/hr/employees/{$employee->id}/edit")->assertForbidden();
        $this->post('/hr/employees', ['name' => 'Y'])->assertForbidden();
        $this->get('/hr/payroll')->assertForbidden();
        $this->get('/hr/shifts')->assertForbidden();

        // View-only attendance: sheet is read-only and saving is refused.
        $this->get('/hr/attendance?date=2026-09-01')->assertOk()->assertDontSee('Save attendance');
        $this->post('/hr/attendance', ['date' => '2026-09-01', 'rows' => [$employee->id => ['status' => 'present']]])->assertSessionHasErrors('rows');
        $this->assertSame(0, Attendance::count());
    }

    public function test_shift_assignment_and_rotation(): void
    {
        $this->actingAs($this->hrAdmin());
        $day = $this->shift(['name' => 'Day', 'start_time' => '08:00', 'end_time' => '20:00', 'break_minutes' => 60]);
        $night = $this->shift(['name' => 'Night', 'start_time' => '20:00', 'end_time' => '08:00', 'break_minutes' => 60, 'colour' => 'violet']);
        $this->assertTrue($night->isOvernight());
        $this->assertSame(660, $night->workMinutes());

        [$a, $b] = [$this->employee(['name' => 'A', 'shift_id' => $day->id]), $this->employee(['name' => 'B', 'shift_id' => $day->id])];

        // Two-week rotation, staggered: A starts on Day, B on Night, switching weekly.
        $this->post('/hr/shifts/rotate', [
            'employee_ids' => [$a->id, $b->id], 'sequence' => [$day->id, $night->id, ''], 'every_days' => 7,
            'start_date' => '2026-11-02', 'end_date' => '2026-11-15', 'stagger' => 1,
        ])->assertSessionHasNoErrors();

        $on = fn (Employee $e, string $date) => $e->fresh()->shiftOn(Carbon::parse($date))?->name;
        $this->assertSame(['Day', 'Night', 'Night', 'Day'], [$on($a, '2026-11-03'), $on($b, '2026-11-03'), $on($a, '2026-11-10'), $on($b, '2026-11-10')]);
        $this->assertSame(4, ShiftAssignment::count());

        // A single assignment in the middle of a rotation block splits it.
        $this->post('/hr/shifts/assign', ['employee_ids' => [$a->id], 'shift_id' => $night->id, 'start_date' => '2026-11-04', 'end_date' => '2026-11-05'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Day', 'Night', 'Day'], [$on($a, '2026-11-03'), $on($a, '2026-11-04'), $on($a, '2026-11-06')]);

        // "Default shift" removes assignments; outside any assignment the default shift applies.
        $this->post('/hr/shifts/assign', ['employee_ids' => [$a->id], 'shift_id' => '', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Day', $on($a, '2026-11-10'));
        $this->assertSame(0, ShiftAssignment::where('employee_id', $a->id)->count());

        $this->get('/hr/shifts?week=2026-11-09')->assertOk()->assertSee('Night');
        $this->post('/hr/shifts/rotate', ['employee_ids' => [$a->id], 'sequence' => [$day->id], 'every_days' => 7, 'start_date' => '2026-11-02', 'end_date' => '2026-11-15'])
            ->assertSessionHasErrors('sequence');

        // A shift in use can't be deleted.
        $this->delete("/hr/shifts/{$day->id}")->assertRedirect();
        $this->assertNotNull($day->fresh());
    }

    public function test_daily_attendance_with_overtime_and_edit_rights(): void
    {
        $shift = $this->shift(); // 08:00–16:30, 30 min break = 8 h
        $employee = $this->employee(['shift_id' => $shift->id]);

        $this->actingAs($this->subAdminWith(['hr_attendance' => ['view', 'create']]));
        $this->post('/hr/attendance', ['date' => '2026-09-01', 'rows' => [$employee->id => ['status' => 'present', 'in_time' => '08:00', 'out_time' => '18:30']]])
            ->assertSessionHasNoErrors();

        $record = Attendance::first();
        $this->assertSame('present', $record->status);
        $this->assertEquals(2, $record->overtime_hours, 'OT worked out from in/out against the shift.');
        $this->assertSame($shift->id, $record->shift_id);

        // Re-saving the same values is fine; changing them needs the edit right.
        $this->post('/hr/attendance', ['date' => '2026-09-01', 'rows' => [$employee->id => ['status' => 'present', 'in_time' => '08:00', 'out_time' => '18:30']]])
            ->assertSessionHasNoErrors();
        $this->post('/hr/attendance', ['date' => '2026-09-01', 'rows' => [$employee->id => ['status' => 'absent']]])->assertSessionHasErrors('rows');
        $this->assertSame('present', $record->fresh()->status);

        $this->actingAs($this->hrAdmin());
        $this->post('/hr/attendance', ['date' => '2026-09-01', 'rows' => [$employee->id => ['status' => 'absent', 'in_time' => '08:00']]])->assertSessionHasNoErrors();
        $this->assertSame(['absent', null], [$record->fresh()->status, $record->fresh()->in_time]);
        $this->post('/hr/attendance', ['date' => '2026-09-01', 'rows' => [$employee->id => ['status' => '']]])->assertSessionHasNoErrors();
        $this->assertSame(0, Attendance::count());

        // Not yet joined → not on the sheet, and ignored if posted.
        $late = $this->employee(['name' => 'Late Joiner', 'joining_date' => '2026-10-01']);
        $this->get('/hr/attendance?date=2026-09-01')->assertOk()->assertSee('Ravi Kumar')->assertDontSee('Late Joiner');
        $this->post('/hr/attendance', ['date' => '2026-09-01', 'rows' => [$late->id => ['status' => 'present']]]);
        $this->assertSame(0, Attendance::count());
    }

    public function test_payroll_from_attendance_to_finalized_salary_slip(): void
    {
        // September 2026: 30 days, Sundays 6/13/20/27 are weekly offs.
        $shift = $this->shift();
        $employee = $this->employee(['shift_id' => $shift->id, 'ot_rate_per_hour' => 100, 'uan' => '100200300400']);
        $this->employee(['name' => 'Joins In October', 'joining_date' => '2026-10-05']);

        $this->actingAs($hr = $this->hrAdmin());
        $absent = ['2026-09-08', '2026-09-09'];
        $rows = [];
        for ($d = Carbon::parse('2026-09-01'); $d->month === 9; $d->addDay()) {
            if ($d->isSunday()) {
                continue; // left unmarked: counted as weekly off
            }
            $status = in_array($d->toDateString(), $absent, true) ? 'absent' : ($d->toDateString() === '2026-09-10' ? 'half_day' : 'present');
            $row = ['status' => $status] + ($d->toDateString() === '2026-09-01' ? ['in_time' => '08:00', 'out_time' => '18:30'] : []);
            $this->post('/hr/attendance', ['date' => $d->toDateString(), 'rows' => [$employee->id => $row]])->assertSessionHasNoErrors();
        }
        $this->get('/hr/attendance/register?month=2026-09')->assertOk()->assertSee('27.5');

        $this->post('/hr/payroll', ['month' => '2026-09'])->assertSessionHasNoErrors();
        $run = PayrollRun::first();
        $this->assertCount(1, $run->payslips, 'Only employees on the rolls that month.');
        $slip = $run->payslips->first();

        // 23 present + ½ + 4 weekly offs = 27.5 of 30 paid days.
        $this->assertEquals([27.5, 2.5, 0, 2], [$slip->paid_days, $slip->lop_days, $slip->unmarked_days, $slip->overtime_hours]);
        $this->assertEquals([11000, 3666.67, 1833.33, 200], [$slip->basic, $slip->hra, $slip->special_allowance, $slip->overtime_pay]);
        $this->assertEquals(16700, $slip->gross_earnings);
        $this->assertEquals([1320, 126, 200], [$slip->pf, $slip->esi, $slip->professional_tax]);   // PF 12% of 11,000; ESI ⌈0.75% of 16,700⌉
        $this->assertEquals([1320, 543], [$slip->employer_pf, $slip->employer_esi]);
        $this->assertEquals(15054, $slip->net_pay);

        // Bonus and TDS: ESI follows the higher gross.
        $this->put("/hr/payslips/{$slip->id}", ['bonus' => 1000, 'tds' => 500, 'remarks' => 'Festival bonus'])->assertSessionHasNoErrors();
        $slip->refresh();
        $this->assertEquals([17700, 133, 2153, 15547], [$slip->gross_earnings, $slip->esi, $slip->total_deductions, $slip->net_pay]);

        // Recalculate keeps the adjustments.
        $this->post("/hr/payroll/{$run->id}/recalculate")->assertRedirect();
        $this->assertEquals([1000, 500, 15547], [$slip->fresh()->bonus, $slip->fresh()->tds, $slip->fresh()->net_pay]);

        $this->get("/hr/payroll/{$run->id}")->assertOk()->assertSee('15,547');
        $this->get("/hr/payslips/{$slip->id}")->assertOk()->assertSee('Salary Slip')->assertSee('September 2026')->assertSee('100200300400')
            ->assertSee('Rupees Fifteen Thousand Five Hundred Forty-Seven Only')->assertSee('DRAFT');
        $this->get("/hr/payroll/{$run->id}/slips")->assertOk()->assertSee('Ravi Kumar');
        $csv = $this->get("/hr/payroll/{$run->id}/export")->assertOk()->streamedContent();
        $this->assertStringContainsString('Net Pay', $csv);
        $this->assertStringContainsString('15547', $csv);

        // Duplicate month, future month.
        $this->post('/hr/payroll', ['month' => '2026-09'])->assertRedirect("/hr/payroll/{$run->id}");
        $this->post('/hr/payroll', ['month' => now()->addMonths(2)->format('Y-m')])->assertSessionHasErrors('month');

        // Finalize locks it; only the super admin can reopen.
        $this->post("/hr/payroll/{$run->id}/finalize")->assertRedirect();
        $this->assertTrue($run->fresh()->isFinalized());
        $this->get("/hr/payslips/{$slip->id}")->assertDontSee('DRAFT');
        $this->get("/hr/payslips/{$slip->id}/edit")->assertForbidden();
        $this->post("/hr/payroll/{$run->id}/recalculate")->assertForbidden();
        $this->delete("/hr/payroll/{$run->id}")->assertForbidden();
        $this->post("/hr/payroll/{$run->id}/reopen")->assertForbidden();
        $this->delete("/hr/employees/{$employee->id}")->assertRedirect();
        $this->assertNotNull($employee->fresh(), 'Employees with payslips are kept.');

        $this->actingAs($this->superAdmin())->post("/hr/payroll/{$run->id}/reopen")->assertRedirect();
        $this->assertFalse($run->fresh()->isFinalized());
    }

    public function test_unmarked_days_are_unpaid_and_flagged(): void
    {
        $employee = $this->employee(['pf_applicable' => false, 'esi_applicable' => false, 'professional_tax' => 0]);
        $this->actingAs($this->hrAdmin())->post('/hr/payroll', ['month' => '2026-09'])->assertSessionHasNoErrors();

        $slip = PayrollRun::first()->payslips->first();
        $this->assertEquals([4, 26, 2400, 2400], [$slip->paid_days, $slip->unmarked_days, $slip->gross_earnings, $slip->net_pay]);
        $this->get('/hr/payroll/'.$slip->payroll_run_id)->assertSee('have days with no attendance marked', false);
    }

    public function test_money_helpers(): void
    {
        $this->assertSame('12,34,567.50', Money::format(1234567.5));
        $this->assertSame('999.00', Money::format(999));
        $this->assertSame('1,00,000', Money::format(100000, 0));
        $this->assertSame('Rupees One Crore Fifty Thousand and Twenty-Five Paise Only', Money::inWords(10050000.25));
        $this->assertSame('Rupees Twelve Lakh Thirty-Four Thousand Five Hundred Sixty-Seven Only', Money::inWords(1234567));
        $this->assertSame('Rupees Zero Only', Money::inWords(0));
    }
}
