<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR & Payroll: shifts, employees (records only — no login), shift assignments,
 * daily attendance, monthly payroll runs and their payslips.
 *
 * The old single "hr" permission module is split into hr_employees, hr_shifts,
 * hr_attendance and hr_payroll; existing rights are copied to all four.
 */
return new class extends Migration
{
    protected const HR_MODULES = ['hr_employees', 'hr_shifts', 'hr_attendance', 'hr_payroll'];

    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->string('colour', 20)->default('sky');
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('department', 100)->nullable();
            $table->string('designation', 100)->nullable();
            $table->string('employment_type', 20)->default('permanent');
            $table->date('joining_date');
            $table->date('leaving_date')->nullable();
            $table->unsignedTinyInteger('weekly_off')->default(0); // 0 = Sunday … 6 = Saturday
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete(); // default shift
            // Monthly salary structure.
            $table->decimal('basic', 10, 2)->default(0);
            $table->decimal('hra', 10, 2)->default(0);
            $table->decimal('conveyance', 10, 2)->default(0);
            $table->decimal('medical', 10, 2)->default(0);
            $table->decimal('special_allowance', 10, 2)->default(0);
            $table->decimal('other_allowance', 10, 2)->default(0);
            $table->decimal('ot_rate_per_hour', 8, 2)->nullable();
            // Statutory.
            $table->boolean('pf_applicable')->default(true);
            $table->boolean('esi_applicable')->default(true);
            $table->decimal('professional_tax', 8, 2)->default(0);
            $table->string('uan', 20)->nullable();
            $table->string('pf_number', 30)->nullable();
            $table->string('esi_number', 20)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('aadhaar', 12)->nullable();
            // Bank.
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 30)->nullable();
            $table->string('ifsc', 11)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('image_path')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'start_date', 'end_date']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20);
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->time('in_time')->nullable();
            $table->time('out_time')->nullable();
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
            $table->index('date');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique(); // first day of the month
            $table->string('status', 20)->default('draft');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // Employee details as on the slip, so finalized slips don't change if the employee record does.
            $table->json('employee_snapshot');
            // Attendance.
            $table->unsignedTinyInteger('days_in_month');
            $table->decimal('paid_days', 5, 2);
            $table->decimal('lop_days', 5, 2);
            $table->unsignedTinyInteger('unmarked_days')->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);
            // Earnings.
            $table->decimal('fixed_gross', 12, 2);
            $table->decimal('basic', 12, 2)->default(0);
            $table->decimal('hra', 12, 2)->default(0);
            $table->decimal('conveyance', 12, 2)->default(0);
            $table->decimal('medical', 12, 2)->default(0);
            $table->decimal('special_allowance', 12, 2)->default(0);
            $table->decimal('other_allowance', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('gross_earnings', 12, 2)->default(0);
            // Deductions.
            $table->decimal('pf', 12, 2)->default(0);
            $table->decimal('esi', 12, 2)->default(0);
            $table->decimal('professional_tax', 12, 2)->default(0);
            $table->decimal('tds', 12, 2)->default(0);
            $table->decimal('advance', 12, 2)->default(0);
            $table->decimal('other_deduction', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            // Employer contributions (not deducted; for the register and challans).
            $table->decimal('employer_pf', 12, 2)->default(0);
            $table->decimal('employer_esi', 12, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        // Split the old "hr" right into the four HR modules.
        foreach (DB::table('user_permissions')->where('module', 'hr')->get() as $row) {
            foreach (self::HR_MODULES as $module) {
                DB::table('user_permissions')->updateOrInsert(
                    ['user_id' => $row->user_id, 'module' => $module],
                    collect((array) $row)->only(['can_show', 'can_view', 'can_create', 'can_edit', 'can_delete', 'created_at', 'updated_at'])->all()
                );
            }
        }
        DB::table('user_permissions')->where('module', 'hr')->delete();

        DB::table('designations')->insertOrIgnore([
            'name' => 'HR Admin',
            'description' => 'Manages employees, shifts, attendance and payroll.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        foreach (DB::table('user_permissions')->where('module', 'hr_employees')->get() as $row) {
            DB::table('user_permissions')->insert(['module' => 'hr'] + collect((array) $row)->except(['id', 'module'])->all());
        }
        DB::table('user_permissions')->whereIn('module', self::HR_MODULES)->delete();

        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('shifts');
    }
};
