<?php

use App\Http\Controllers\Accounts\LedgerController;
use App\Http\Controllers\Accounts\TrialBalanceController;
use App\Http\Controllers\Accounts\VoucherController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\SubAdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Hr\PayslipController;
use App\Http\Controllers\Hr\ShiftController;
use App\Http\Controllers\Masters\MachineController;
use App\Http\Controllers\Masters\MouldController;
use App\Http\Controllers\Masters\NeckController;
use App\Http\Controllers\Masters\ProductController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\Orders\ApprovalController;
use App\Http\Controllers\Orders\CustomerController;
use App\Http\Controllers\Orders\SalesOrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Sales\FollowUpController;
use App\Http\Controllers\Sales\LeadController;
use App\Http\Controllers\Sales\QualificationController;
use App\Http\Controllers\Sales\QuotationController;
use App\Http\Controllers\Sales\RetentionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    // ERP modules — each one checks the user's "show" right.
    Route::get('/modules/{module}', [ModuleController::class, 'show'])->name('modules.show');

    // Masters — every action is gated by the matching right on its module.
    Route::prefix('masters')->name('masters.')->group(function () {
        foreach ([
            'necks' => [NeckController::class, 'neck_master', 'neck'],
            'moulds' => [MouldController::class, 'mould_master', 'mould'],
            'products' => [ProductController::class, 'product_master', 'product'],
            'machines' => [MachineController::class, 'machine_master', 'machine'],
        ] as $uri => [$controller, $module, $param]) {
            Route::get($uri, [$controller, 'index'])->middleware("module:$module,show")->name("$uri.index");
            Route::get("$uri/create", [$controller, 'create'])->middleware("module:$module,create")->name("$uri.create");
            Route::post($uri, [$controller, 'store'])->middleware("module:$module,create")->name("$uri.store");
            Route::get("$uri/{{$param}}", [$controller, 'show'])->middleware("module:$module,view")->name("$uri.show");
            Route::get("$uri/{{$param}}/edit", [$controller, 'edit'])->middleware("module:$module,edit")->name("$uri.edit");
            Route::put("$uri/{{$param}}", [$controller, 'update'])->middleware("module:$module,edit")->name("$uri.update");
            Route::delete("$uri/{{$param}}", [$controller, 'destroy'])->middleware("module:$module,delete")->name("$uri.destroy");
        }
    });

    // Sales & CRM — each screen is gated by the matching right on its module.
    Route::prefix('sales')->name('sales.')->group(function () {
        foreach ([
            'leads' => [LeadController::class, 'sales_leads', 'lead'],
            'quotations' => [QuotationController::class, 'sales_quotations', 'quotation'],
            'followups' => [FollowUpController::class, 'sales_followups', 'followUp'],
            'retention' => [RetentionController::class, 'sales_retention', 'customer'],
        ] as $uri => [$controller, $module, $param]) {
            Route::get($uri, [$controller, 'index'])->middleware("module:$module,show")->name("$uri.index");
            Route::get("$uri/create", [$controller, 'create'])->middleware("module:$module,create")->name("$uri.create");
            Route::post($uri, [$controller, 'store'])->middleware("module:$module,create")->name("$uri.store");
            Route::get("$uri/{{$param}}", [$controller, 'show'])->middleware("module:$module,view")->name("$uri.show");
            Route::get("$uri/{{$param}}/edit", [$controller, 'edit'])->middleware("module:$module,edit")->name("$uri.edit");
            Route::put("$uri/{{$param}}", [$controller, 'update'])->middleware("module:$module,edit")->name("$uri.update");
            Route::delete("$uri/{{$param}}", [$controller, 'destroy'])->middleware("module:$module,delete")->name("$uri.destroy");
        }

        // Saving checks create or edit depending on whether the lead was already assessed.
        Route::get('qualification', [QualificationController::class, 'index'])->middleware('module:sales_qualification,show')->name('qualification.index');
        Route::get('qualification/{lead}', [QualificationController::class, 'edit'])->middleware('module:sales_qualification,view')->name('qualification.edit');
        Route::put('qualification/{lead}', [QualificationController::class, 'update'])->middleware('module:sales_qualification,view')->name('qualification.update');
        Route::delete('qualification/{lead}', [QualificationController::class, 'destroy'])->middleware('module:sales_qualification,delete')->name('qualification.destroy');

        Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])->middleware('module:sales_quotations,view')->name('quotations.print');
        Route::post('quotations/{quotation}/status', [QuotationController::class, 'status'])->middleware('module:sales_quotations,edit')->name('quotations.status');
        Route::post('quotations/{quotation}/sample', [QuotationController::class, 'sample'])->middleware('module:sales_quotations,edit')->name('quotations.sample');
        Route::post('quotations/{quotation}/revise', [QuotationController::class, 'revise'])->middleware('module:sales_quotations,create')->name('quotations.revise');

        Route::post('followups/{followUp}/complete', [FollowUpController::class, 'complete'])->middleware('module:sales_followups,edit')->name('followups.complete');
        Route::post('followups/{followUp}/cancel', [FollowUpController::class, 'cancel'])->middleware('module:sales_followups,edit')->name('followups.cancel');

        Route::post('retention-reminders', [RetentionController::class, 'reminders'])->middleware('module:sales_followups,create')->name('retention.reminders');
        Route::post('retention/{customer}/orders', [RetentionController::class, 'storeOrder'])->middleware('module:sales_retention,create')->name('retention.orders.store');
        Route::delete('retention/{customer}/orders/{order}', [RetentionController::class, 'destroyOrder'])->middleware('module:sales_retention,delete')->name('retention.orders.destroy');
    });

    // Order Management — each screen is gated by the matching right on its module.
    Route::prefix('orders')->name('orders.')->group(function () {
        foreach ([
            'customers' => [CustomerController::class, 'orders_customers', 'customer'],
            'sales' => [SalesOrderController::class, 'orders_sales', 'salesOrder'],
        ] as $uri => [$controller, $module, $param]) {
            Route::get($uri, [$controller, 'index'])->middleware("module:$module,show")->name("$uri.index");
            Route::get("$uri/create", [$controller, 'create'])->middleware("module:$module,create")->name("$uri.create");
            Route::post($uri, [$controller, 'store'])->middleware("module:$module,create")->name("$uri.store");
            Route::get("$uri/{{$param}}", [$controller, 'show'])->middleware("module:$module,view")->name("$uri.show");
            Route::get("$uri/{{$param}}/edit", [$controller, 'edit'])->middleware("module:$module,edit")->name("$uri.edit");
            Route::put("$uri/{{$param}}", [$controller, 'update'])->middleware("module:$module,edit")->name("$uri.update");
            Route::delete("$uri/{{$param}}", [$controller, 'destroy'])->middleware("module:$module,delete")->name("$uri.destroy");
        }

        Route::post('sales/{salesOrder}/submit', [SalesOrderController::class, 'submit'])->middleware('module:orders_sales,edit')->name('sales.submit');
        Route::post('sales/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->middleware('module:orders_sales,edit')->name('sales.cancel');

        Route::get('approval', [ApprovalController::class, 'index'])->middleware('module:orders_approval,show')->name('approval.index');
        Route::get('approval/{salesOrder}', [ApprovalController::class, 'show'])->middleware('module:orders_approval,view')->name('approval.show');
        Route::post('approval/{salesOrder}/approve', [ApprovalController::class, 'approve'])->middleware('module:orders_approval,edit')->name('approval.approve');
        Route::post('approval/{salesOrder}/reject', [ApprovalController::class, 'reject'])->middleware('module:orders_approval,edit')->name('approval.reject');
    });

    // Accounts — each screen is gated by the matching right on its module.
    Route::prefix('accounts')->name('accounts.')->group(function () {
        foreach ([
            'ledgers' => [LedgerController::class, 'accounts_ledgers', 'ledger'],
            'vouchers' => [VoucherController::class, 'accounts_vouchers', 'voucher'],
        ] as $uri => [$controller, $module, $param]) {
            Route::get($uri, [$controller, 'index'])->middleware("module:$module,show")->name("$uri.index");
            Route::get("$uri/create", [$controller, 'create'])->middleware("module:$module,create")->name("$uri.create");
            Route::post($uri, [$controller, 'store'])->middleware("module:$module,create")->name("$uri.store");
            Route::get("$uri/{{$param}}", [$controller, 'show'])->middleware("module:$module,view")->name("$uri.show");
            Route::get("$uri/{{$param}}/edit", [$controller, 'edit'])->middleware("module:$module,edit")->name("$uri.edit");
            Route::put("$uri/{{$param}}", [$controller, 'update'])->middleware("module:$module,edit")->name("$uri.update");
            Route::delete("$uri/{{$param}}", [$controller, 'destroy'])->middleware("module:$module,delete")->name("$uri.destroy");
        }

        Route::get('trial-balance', TrialBalanceController::class)->middleware('module:accounts_trial_balance,view')->name('trial-balance.index');
    });

    // HR & Payroll — each screen is gated by the matching right on its HR module.
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->middleware('module:hr_employees,show')->name('employees.index');
        Route::get('employees/create', [EmployeeController::class, 'create'])->middleware('module:hr_employees,create')->name('employees.create');
        Route::post('employees', [EmployeeController::class, 'store'])->middleware('module:hr_employees,create')->name('employees.store');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->middleware('module:hr_employees,view')->name('employees.show');
        Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->middleware('module:hr_employees,edit')->name('employees.edit');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->middleware('module:hr_employees,edit')->name('employees.update');
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('module:hr_employees,delete')->name('employees.destroy');

        Route::get('shifts', [ShiftController::class, 'index'])->middleware('module:hr_shifts,show')->name('shifts.index');
        Route::get('shifts/create', [ShiftController::class, 'create'])->middleware('module:hr_shifts,create')->name('shifts.create');
        Route::post('shifts', [ShiftController::class, 'store'])->middleware('module:hr_shifts,create')->name('shifts.store');
        Route::get('shifts/assign', [ShiftController::class, 'assignForm'])->middleware('module:hr_shifts,create')->name('shifts.assign');
        Route::post('shifts/assign', [ShiftController::class, 'assign'])->middleware('module:hr_shifts,create')->name('shifts.assign.store');
        Route::get('shifts/rotate', [ShiftController::class, 'rotateForm'])->middleware('module:hr_shifts,create')->name('shifts.rotate');
        Route::post('shifts/rotate', [ShiftController::class, 'rotate'])->middleware('module:hr_shifts,create')->name('shifts.rotate.store');
        Route::get('shifts/{shift}/edit', [ShiftController::class, 'edit'])->middleware('module:hr_shifts,edit')->name('shifts.edit');
        Route::put('shifts/{shift}', [ShiftController::class, 'update'])->middleware('module:hr_shifts,edit')->name('shifts.update');
        Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->middleware('module:hr_shifts,delete')->name('shifts.destroy');

        // Saving checks create / edit / delete per row (see AttendanceController::store).
        Route::get('attendance', [AttendanceController::class, 'index'])->middleware('module:hr_attendance,show')->name('attendance.index');
        Route::post('attendance', [AttendanceController::class, 'store'])->middleware('module:hr_attendance,show')->name('attendance.store');
        Route::get('attendance/register', [AttendanceController::class, 'register'])->middleware('module:hr_attendance,view')->name('attendance.register');

        Route::get('payroll', [PayrollController::class, 'index'])->middleware('module:hr_payroll,show')->name('payroll.index');
        Route::post('payroll', [PayrollController::class, 'store'])->middleware('module:hr_payroll,create')->name('payroll.store');
        Route::get('payroll/{run}', [PayrollController::class, 'show'])->middleware('module:hr_payroll,view')->name('payroll.show');
        Route::get('payroll/{run}/export', [PayrollController::class, 'export'])->middleware('module:hr_payroll,view')->name('payroll.export');
        Route::get('payroll/{run}/slips', [PayrollController::class, 'slips'])->middleware('module:hr_payroll,view')->name('payroll.slips');
        Route::post('payroll/{run}/recalculate', [PayrollController::class, 'recalculate'])->middleware('module:hr_payroll,edit')->name('payroll.recalculate');
        Route::post('payroll/{run}/finalize', [PayrollController::class, 'finalize'])->middleware('module:hr_payroll,edit')->name('payroll.finalize');
        Route::post('payroll/{run}/reopen', [PayrollController::class, 'reopen'])->middleware('module:hr_payroll,edit')->name('payroll.reopen');
        Route::delete('payroll/{run}', [PayrollController::class, 'destroy'])->middleware('module:hr_payroll,delete')->name('payroll.destroy');
        Route::get('payslips/{payslip}', [PayslipController::class, 'show'])->middleware('module:hr_payroll,view')->name('payslips.show');
        Route::get('payslips/{payslip}/edit', [PayslipController::class, 'edit'])->middleware('module:hr_payroll,edit')->name('payslips.edit');
        Route::put('payslips/{payslip}', [PayslipController::class, 'update'])->middleware('module:hr_payroll,edit')->name('payslips.update');
    });

    // User Management — Super Admin only.
    Route::middleware('super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', SubAdminController::class)->except('show');
        Route::patch('users/{user}/toggle-status', [SubAdminController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::get('users/{user}/permissions', [PermissionController::class, 'edit'])->name('users.permissions.edit');
        Route::put('users/{user}/permissions', [PermissionController::class, 'update'])->name('users.permissions.update');

        Route::resource('designations', DesignationController::class)->except(['show', 'create']);
    });
});
