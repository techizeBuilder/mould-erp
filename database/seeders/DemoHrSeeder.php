<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for HR & Payroll: shifts, employees, a Day/Night rotation and attendance
 * from 1 September 2026 to yesterday, ready for generating payroll.
 *
 * Re-runnable: removes only previous demo employees (remarks start with "[Demo]") and
 * their attendance / payslips; real employees are left alone. Not called by DatabaseSeeder:
 *     php artisan db:seed --class=DemoHrSeeder
 */
class DemoHrSeeder extends Seeder
{
    protected const TAG = '[Demo]';

    protected const FROM = '2026-09-01';

    /** National / festival holidays in the period. */
    protected const HOLIDAYS = ['2026-10-02' => 'Gandhi Jayanti', '2026-10-20' => 'Dussehra'];

    public function run(): void
    {
        mt_srand(2026); // same "random" attendance on every run

        DB::transaction(function () {
            Employee::where('remarks', 'like', self::TAG.'%')->get()->each->delete();
            PayrollRun::doesntHave('payslips')->delete();

            $by = User::where('is_super_admin', true)->value('id');
            $shift = fn (array $a) => Shift::updateOrCreate(['name' => $a['name']], $a + ['grace_minutes' => 10, 'is_active' => true]);
            $general = $shift(['name' => 'General', 'start_time' => '09:00', 'end_time' => '17:30', 'break_minutes' => 30, 'colour' => 'emerald']);
            $day = $shift(['name' => 'Day', 'start_time' => '08:00', 'end_time' => '20:00', 'break_minutes' => 60, 'colour' => 'sky']);
            $night = $shift(['name' => 'Night', 'start_time' => '20:00', 'end_time' => '08:00', 'break_minutes' => 60, 'colour' => 'violet']);

            $e = fn (array $a) => tap(new Employee($a + [
                'employment_type' => 'permanent', 'status' => 'active', 'joining_date' => '2024-04-01', 'weekly_off' => 0,
                'pf_applicable' => true, 'esi_applicable' => true, 'professional_tax' => 200, 'bank_name' => 'State Bank of India',
            ]), function (Employee $x) use ($by) {
                $x->remarks = trim(self::TAG.' '.$x->remarks);
                $x->created_by = $by;
                $x->save();
            });

            // ---- Production: two crews rotating Day / Night, factory runs 7 days ----
            $crewA = [
                $e(['name' => 'Ramesh Yadav', 'father_name' => 'Shyam Lal Yadav', 'gender' => 'male', 'department' => 'Production', 'designation' => 'Machine Operator',
                    'phone' => '9876501001', 'weekly_off' => 1, 'shift_id' => $day->id, 'basic' => 9500, 'hra' => 2500, 'conveyance' => 800, 'special_allowance' => 1200,
                    'uan' => '101100000001', 'esi_number' => '3100000001', 'bank_account' => '30110000001', 'ifsc' => 'SBIN0001234', 'pan' => 'ABCPY1001A']),
                $e(['name' => 'Sunil Kumar', 'father_name' => 'Raj Kumar', 'gender' => 'male', 'department' => 'Production', 'designation' => 'Machine Operator',
                    'phone' => '9876501002', 'weekly_off' => 2, 'shift_id' => $day->id, 'basic' => 9500, 'hra' => 2500, 'conveyance' => 800, 'special_allowance' => 1000,
                    'uan' => '101100000002', 'esi_number' => '3100000002', 'bank_account' => '30110000002', 'ifsc' => 'SBIN0001234']),
                $e(['name' => 'Pooja Sharma', 'father_name' => 'Mahesh Sharma', 'gender' => 'female', 'department' => 'Production', 'designation' => 'Packing Operator',
                    'phone' => '9876501003', 'weekly_off' => 3, 'shift_id' => $day->id, 'basic' => 8500, 'hra' => 2000, 'conveyance' => 800, 'special_allowance' => 700,
                    'uan' => '101100000003', 'esi_number' => '3100000003', 'bank_account' => '30110000003', 'ifsc' => 'SBIN0001234']),
                $e(['name' => 'Manoj Tiwari', 'father_name' => 'Ram Prasad Tiwari', 'gender' => 'male', 'department' => 'Production', 'designation' => 'Helper',
                    'phone' => '9876501004', 'weekly_off' => 4, 'shift_id' => $day->id, 'employment_type' => 'contract', 'pf_applicable' => false,
                    'basic' => 8000, 'hra' => 1500, 'special_allowance' => 500, 'professional_tax' => 0, 'esi_number' => '3100000004',
                    'bank_name' => 'Punjab National Bank', 'bank_account' => '40220000004', 'ifsc' => 'PUNB0123400', 'remarks' => 'Contract labour — no PF.']),
            ];
            $crewB = [
                $e(['name' => 'Vikram Singh', 'father_name' => 'Balwant Singh', 'gender' => 'male', 'department' => 'Production', 'designation' => 'Senior Operator',
                    'phone' => '9876501005', 'weekly_off' => 1, 'shift_id' => $night->id, 'basic' => 11000, 'hra' => 3000, 'conveyance' => 800, 'special_allowance' => 1700,
                    'uan' => '101100000005', 'esi_number' => '3100000005', 'bank_account' => '30110000005', 'ifsc' => 'SBIN0001234', 'pan' => 'BXVPS1005K']),
                $e(['name' => 'Arjun Patel', 'father_name' => 'Dinesh Patel', 'gender' => 'male', 'department' => 'Production', 'designation' => 'Machine Operator',
                    'phone' => '9876501006', 'weekly_off' => 2, 'shift_id' => $night->id, 'basic' => 9500, 'hra' => 2500, 'conveyance' => 800, 'special_allowance' => 1200,
                    'uan' => '101100000006', 'esi_number' => '3100000006', 'bank_account' => '30110000006', 'ifsc' => 'SBIN0001234']),
                $e(['name' => 'Deepak Verma', 'father_name' => 'Suresh Verma', 'gender' => 'male', 'department' => 'Production', 'designation' => 'Machine Operator',
                    'phone' => '9876501007', 'weekly_off' => 3, 'shift_id' => $night->id, 'joining_date' => '2026-09-15', 'basic' => 9000, 'hra' => 2200, 'conveyance' => 800,
                    'special_allowance' => 1000, 'uan' => '101100000007', 'esi_number' => '3100000007', 'bank_account' => '30110000007', 'ifsc' => 'SBIN0001234',
                    'remarks' => 'Joined mid-September — pro-rata salary.']),
                $e(['name' => 'Rahul Gupta', 'father_name' => 'Anil Gupta', 'gender' => 'male', 'department' => 'Production', 'designation' => 'Helper',
                    'phone' => '9876501008', 'weekly_off' => 4, 'shift_id' => $night->id, 'basic' => 8000, 'hra' => 1500, 'special_allowance' => 800,
                    'status' => 'left', 'leaving_date' => '2026-10-10', 'esi_number' => '3100000008', 'bank_account' => '30110000008', 'ifsc' => 'SBIN0001234',
                    'remarks' => 'Resigned — last working day 10 Oct.']),
            ];

            // ---- Day staff on the General shift, Sunday off ----
            $staff = [
                $e(['name' => 'Anita Desai', 'father_name' => 'Prakash Desai', 'gender' => 'female', 'department' => 'Quality', 'designation' => 'QC Inspector',
                    'phone' => '9876501009', 'shift_id' => $general->id, 'basic' => 12000, 'hra' => 4000, 'conveyance' => 1200, 'medical' => 800, 'special_allowance' => 2000,
                    'uan' => '101100000009', 'esi_number' => '3100000009', 'bank_name' => 'HDFC Bank', 'bank_account' => '50100000009', 'ifsc' => 'HDFC0001234', 'pan' => 'CDAPD1009M']),
                $e(['name' => 'Suresh Nair', 'father_name' => 'Gopal Nair', 'gender' => 'male', 'department' => 'Stores', 'designation' => 'Store Keeper',
                    'phone' => '9876501010', 'shift_id' => $general->id, 'basic' => 11000, 'hra' => 3500, 'conveyance' => 1000, 'medical' => 500, 'special_allowance' => 1500,
                    'uan' => '101100000010', 'esi_number' => '3100000010', 'bank_account' => '30110000010', 'ifsc' => 'SBIN0001234']),
                $e(['name' => 'Imran Khan', 'father_name' => 'Salim Khan', 'gender' => 'male', 'department' => 'Maintenance', 'designation' => 'Maintenance Fitter',
                    'phone' => '9876501011', 'shift_id' => $general->id, 'basic' => 13000, 'hra' => 4500, 'conveyance' => 1200, 'special_allowance' => 2000,
                    'ot_rate_per_hour' => 150, 'uan' => '101100000011', 'esi_number' => '3100000011', 'bank_account' => '30110000011', 'ifsc' => 'SBIN0001234',
                    'remarks' => 'Fixed OT rate ₹150/h.']),
                $e(['name' => 'Kavita Joshi', 'father_name' => 'Hemant Joshi', 'gender' => 'female', 'department' => 'Accounts', 'designation' => 'Accountant',
                    'phone' => '9876501012', 'shift_id' => $general->id, 'basic' => 22000, 'hra' => 8800, 'conveyance' => 1600, 'medical' => 1250, 'special_allowance' => 6350,
                    'uan' => '101100000012', 'bank_name' => 'ICICI Bank', 'bank_account' => '00120000012', 'ifsc' => 'ICIC0000123', 'pan' => 'DKJPJ1012Q',
                    'remarks' => 'Salary above ESI limit — PF capped at ₹15,000 basic.']),
                $e(['name' => 'Rajesh Mehta', 'father_name' => 'Kishore Mehta', 'gender' => 'male', 'department' => 'Production', 'designation' => 'Production Supervisor',
                    'phone' => '9876501013', 'shift_id' => $general->id, 'joining_date' => '2022-06-01', 'basic' => 30000, 'hra' => 12000, 'conveyance' => 1600,
                    'medical' => 1250, 'special_allowance' => 10150, 'professional_tax' => 200, 'uan' => '101100000013', 'bank_name' => 'HDFC Bank',
                    'bank_account' => '50100000013', 'ifsc' => 'HDFC0001234', 'pan' => 'EFRPM1013L']),
            ];

            // ---- Rotation: crew A Day → Night, crew B Night → Day, switching every Monday ----
            $rotateFrom = Carbon::parse('2026-08-31'); // a Monday
            $rotateTo = Carbon::parse('2026-11-29');
            foreach ([[$crewA, [$day, $night]], [$crewB, [$night, $day]]] as [$crew, $sequence]) {
                foreach ($crew as $member) {
                    for ($from = $rotateFrom->copy(), $turn = 0; $from->lte($rotateTo); $turn++) {
                        $to = $from->copy()->addDays(6);
                        ShiftAssignment::assign($member->id, $sequence[$turn % 2]->id, $from, $to, $by);
                        $from = $to->addDay();
                    }
                }
            }

            // ---- Attendance: 1 Sep to yesterday ----
            $end = now()->subDay()->startOfDay();
            foreach ([...$crewA, ...$crewB, ...$staff] as $employee) {
                $assignments = $employee->shiftAssignments()->with('shift')->get();
                $employee->load('shift');
                $rows = [];

                for ($date = Carbon::parse(self::FROM); $date->lte($end); $date->addDay()) {
                    if (! $employee->isEmployedOn($date)) {
                        continue;
                    }
                    $shift = $employee->shiftOn($date, $assignments);
                    $row = ['employee_id' => $employee->id, 'date' => $date->toDateString(), 'shift_id' => $shift?->id,
                        'in_time' => null, 'out_time' => null, 'overtime_hours' => 0, 'remarks' => null, 'marked_by' => $by,
                        'created_at' => now(), 'updated_at' => now()];

                    if (isset(self::HOLIDAYS[$date->toDateString()])) {
                        $rows[] = ['status' => 'holiday', 'remarks' => self::HOLIDAYS[$date->toDateString()]] + $row;

                        continue;
                    }
                    if ($employee->isWeeklyOff($date)) {
                        // Most weekly offs are marked; a few are left blank (still paid as weekly off).
                        if (mt_rand(1, 100) <= 80) {
                            $rows[] = ['status' => 'weekly_off'] + $row;
                        }

                        continue;
                    }

                    $roll = mt_rand(1, 100);
                    $status = match (true) {
                        $roll <= 3 => 'absent',
                        $roll <= 5 => 'unpaid_leave',
                        $roll <= 8 => 'half_day',
                        $roll <= 11 => 'paid_leave',
                        default => 'present',
                    };
                    $row['remarks'] = match ($status) {
                        'paid_leave' => 'Casual leave',
                        'unpaid_leave' => 'Leave without pay',
                        'half_day' => 'Left early',
                        default => null,
                    };

                    if ($shift && in_array($status, ['present', 'half_day'], true)) {
                        $in = Carbon::parse($shift->start_time)->addMinutes(mt_rand(-10, 15));
                        $out = $status === 'half_day'
                            ? Carbon::parse($shift->start_time)->addMinutes(intdiv($shift->workMinutes(), 2) + mt_rand(0, 20))
                            : Carbon::parse($shift->end_time)->addMinutes(mt_rand(0, 20));
                        // Production and maintenance sometimes stay back for overtime.
                        if ($status === 'present' && in_array($employee->department, ['Production', 'Maintenance'], true) && mt_rand(1, 100) <= 18) {
                            $out->addHours(mt_rand(1, 3));
                        }
                        $row['in_time'] = $in->format('H:i');
                        $row['out_time'] = $out->format('H:i');
                        $row['overtime_hours'] = $status === 'present' ? (Attendance::overtimeFrom($row['in_time'], $row['out_time'], $shift) ?? 0) : 0;
                    }

                    $rows[] = ['status' => $status] + $row;
                }

                foreach (array_chunk($rows, 200) as $chunk) {
                    Attendance::insert($chunk);
                }
            }
        });
    }
}
