<?php

use App\Http\Controllers\AttendanceBulkController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceGenerationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\BorrowRecoveryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeductionController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationChangeController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\EmployeeAttendanceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeeExitController;
use App\Http\Controllers\EmployeeShiftController;
use App\Http\Controllers\FinalSettlementController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveDecisionController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\PastEmployeeController;
use App\Http\Controllers\PayrollAdjustmentController;
use App\Http\Controllers\PayrollCalculationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollFinalizationController;
use App\Http\Controllers\PayrollItemController;
use App\Http\Controllers\PayrollReportController;
use App\Http\Controllers\PayrollReviewController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SalaryRevisionController;
use App\Http\Controllers\SalarySlipController;
use App\Http\Controllers\Settings\AttendanceSettingController;
use App\Http\Controllers\Settings\CompanyProfileController;
use App\Http\Controllers\Settings\CompanyUserController;
use App\Http\Controllers\Settings\PayrollSettingController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\ShortHoursController;
use App\Http\Controllers\WeeklyHolidayController;
use App\Http\Controllers\WorkShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Company (tenant) routes
|--------------------------------------------------------------------------
|
| Loaded behind the `auth`, `verified` and `tenant` middleware. Every model
| bound from a URL is resolved through the company scope, so an id that
| belongs to another company is a 404.
|
*/

Route::get('dashboard', DashboardController::class)->name('dashboard');

/* Employees */
Route::middleware('can:employees.manage')->group(function () {
    Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::post('employees/{employee}/exit', [EmployeeExitController::class, 'store'])->name('employees.exit.store');
    Route::delete('employees/{employee}/exit', [EmployeeExitController::class, 'destroy'])->name('employees.exit.destroy');
    Route::put('employees/{employee}/shift', [EmployeeShiftController::class, 'update'])->name('employees.shift.update');
    Route::post('employees/{employee}/designation-changes', [DesignationChangeController::class, 'store'])->name('employees.designation-changes.store');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
    Route::delete('documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('documents.destroy');

    Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    Route::post('designations', [DesignationController::class, 'store'])->name('designations.store');
    Route::put('designations/{designation}', [DesignationController::class, 'update'])->name('designations.update');
    Route::delete('designations/{designation}', [DesignationController::class, 'destroy'])->name('designations.destroy');
});

Route::middleware('can:employees.view')->group(function () {
    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/past', [PastEmployeeController::class, 'index'])->name('employees.past');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::get('designations', [DesignationController::class, 'index'])->name('designations.index');
    Route::get('designation-changes', [DesignationChangeController::class, 'index'])->name('designation-changes.index');
    Route::get('documents', [EmployeeDocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/{document}', [EmployeeDocumentController::class, 'show'])->name('documents.show');
});

/* Attendance */
Route::middleware('can:attendance.view')->group(function () {
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('attendance/calendar', [AttendanceController::class, 'calendar'])->name('attendance.calendar');
    Route::get('employees/{employee}/attendance', [EmployeeAttendanceController::class, 'show'])->name('employees.attendance.show');
    Route::get('work-shifts', [WorkShiftController::class, 'index'])->name('work-shifts.index');
    Route::get('weekly-holidays', [WeeklyHolidayController::class, 'edit'])->name('weekly-holidays.edit');
    Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
});

Route::middleware('can:attendance.manage')->group(function () {
    Route::put('employees/{employee}/attendance/{date}', [EmployeeAttendanceController::class, 'update'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('employees.attendance.update');
    Route::delete('employees/{employee}/attendance/{date}', [EmployeeAttendanceController::class, 'destroy'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('employees.attendance.destroy');
    Route::post('attendance/bulk', [AttendanceBulkController::class, 'store'])->name('attendance.bulk.store');
    Route::post('attendance/generate', [AttendanceGenerationController::class, 'store'])->name('attendance.generate.store');

    Route::post('work-shifts', [WorkShiftController::class, 'store'])->name('work-shifts.store');
    Route::put('work-shifts/{workShift}', [WorkShiftController::class, 'update'])->name('work-shifts.update');
    Route::delete('work-shifts/{workShift}', [WorkShiftController::class, 'destroy'])->name('work-shifts.destroy');
    Route::put('weekly-holidays', [WeeklyHolidayController::class, 'update'])->name('weekly-holidays.update');
    Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
});

/* Leave */
Route::middleware('can:leave.view')->group(function () {
    Route::get('leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::get('leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
    Route::get('leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
});

Route::middleware('can:leave.manage')->group(function () {
    Route::post('leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::put('leaves/{leave}', [LeaveController::class, 'update'])->name('leaves.update');
    Route::put('leaves/{leave}/decision', [LeaveDecisionController::class, 'update'])->name('leaves.decision.update');
    Route::post('leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
    Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('leave-types.update');
    Route::delete('leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');
    Route::put('leave-balances', [LeaveBalanceController::class, 'update'])->name('leave-balances.update');
});

/* Payroll and salary */
Route::middleware('can:payroll.view')->group(function () {
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('payroll/reports', [PayrollReportController::class, 'index'])->name('payroll.reports');
    Route::get('payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::get('payroll/{payroll}/items/{item}', [PayrollItemController::class, 'show'])
        ->scopeBindings()
        ->name('payroll.items.show');
    Route::get('salary-structure', [SalaryController::class, 'index'])->name('salary.index');
    Route::get('employees/{employee}/salary', [SalaryController::class, 'show'])->name('employees.salary.show');
    Route::get('salary-revisions', [SalaryRevisionController::class, 'index'])->name('salary-revisions.index');
    Route::get('salary-slips', [SalarySlipController::class, 'index'])->name('salary-slips.index');
    Route::get('salary-slips/{slip}', [SalarySlipController::class, 'show'])->name('salary-slips.show');
});

Route::middleware('can:payroll.manage')->group(function () {
    Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
    Route::delete('payroll/{payroll}', [PayrollController::class, 'destroy'])->name('payroll.destroy');
    Route::post('payroll/{payroll}/calculate', [PayrollCalculationController::class, 'store'])->name('payroll.calculate');
    Route::post('payroll/{payroll}/review', [PayrollReviewController::class, 'store'])->name('payroll.review');
    Route::post('payroll/{payroll}/items/{item}/adjustments', [PayrollAdjustmentController::class, 'store'])
        ->scopeBindings()
        ->name('payroll.items.adjustments.store');
    Route::delete('payroll/{payroll}/items/{item}/adjustments/{adjustment}', [PayrollAdjustmentController::class, 'destroy'])
        ->scopeBindings()
        ->name('payroll.items.adjustments.destroy');
    Route::post('employees/{employee}/salary', [SalaryController::class, 'store'])->name('employees.salary.store');
});

Route::middleware('can:payroll.finalize')->group(function () {
    Route::post('payroll/{payroll}/finalize', [PayrollFinalizationController::class, 'store'])->name('payroll.finalize');
    Route::post('payroll/{payroll}/reopen', [PayrollFinalizationController::class, 'destroy'])->name('payroll.reopen');
});

/* Employee finance */
Route::middleware('can:finance.manage')->group(function () {
    Route::get('borrows/create', [BorrowController::class, 'create'])->name('borrows.create');
    Route::post('borrows', [BorrowController::class, 'store'])->name('borrows.store');
    Route::delete('borrows/{borrow}', [BorrowController::class, 'destroy'])->name('borrows.destroy');
    Route::post('borrows/{borrow}/recoveries', [BorrowRecoveryController::class, 'store'])->name('borrows.recoveries.store');

    Route::post('overtime', [OvertimeController::class, 'store'])->name('overtime.store');
    Route::put('overtime/{overtime}', [OvertimeController::class, 'update'])->name('overtime.update');
    Route::delete('overtime/{overtime}', [OvertimeController::class, 'destroy'])->name('overtime.destroy');

    Route::put('short-hours/{employee}', [ShortHoursController::class, 'update'])->name('short-hours.update');
    Route::delete('short-hours/{employee}', [ShortHoursController::class, 'destroy'])->name('short-hours.destroy');

    Route::post('deductions', [DeductionController::class, 'store'])->name('deductions.store');
    Route::put('deductions/{deduction}', [DeductionController::class, 'update'])->name('deductions.update');
    Route::delete('deductions/{deduction}', [DeductionController::class, 'destroy'])->name('deductions.destroy');

    Route::post('bonuses', [BonusController::class, 'store'])->name('bonuses.store');
    Route::put('bonuses/{bonus}', [BonusController::class, 'update'])->name('bonuses.update');
    Route::delete('bonuses/{bonus}', [BonusController::class, 'destroy'])->name('bonuses.destroy');
});

Route::middleware('can:finance.view')->group(function () {
    Route::get('borrows', [BorrowController::class, 'index'])->name('borrows.index');
    Route::get('borrows/{borrow}', [BorrowController::class, 'show'])->name('borrows.show');
    Route::get('borrow-recoveries', [BorrowRecoveryController::class, 'index'])->name('borrow-recoveries.index');
    Route::get('overtime', [OvertimeController::class, 'index'])->name('overtime.index');
    Route::get('short-hours', [ShortHoursController::class, 'index'])->name('short-hours.index');
    Route::get('deductions', [DeductionController::class, 'index'])->name('deductions.index');
    Route::get('bonuses', [BonusController::class, 'index'])->name('bonuses.index');
});

/* Final settlement */
Route::middleware('can:settlements.manage')->group(function () {
    Route::get('final-settlements', [FinalSettlementController::class, 'index'])->name('final-settlements.index');
    Route::get('final-settlements/{employee}', [FinalSettlementController::class, 'show'])->name('final-settlements.show');
    Route::post('final-settlements/{employee}', [FinalSettlementController::class, 'store'])->name('final-settlements.store');
    Route::put('final-settlements/{employee}', [FinalSettlementController::class, 'update'])->name('final-settlements.update');
    Route::post('final-settlements/{employee}/finalize', [FinalSettlementController::class, 'finalize'])->name('final-settlements.finalize');
    Route::post('final-settlements/{employee}/paid', [FinalSettlementController::class, 'paid'])->name('final-settlements.paid');
});

/* Reports */
Route::middleware('can:reports.view')->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export/{report}', [ReportController::class, 'export'])
        ->whereIn('report', ['attendance', 'payroll', 'borrow'])
        ->name('reports.export');
});

/* Company settings */
Route::middleware('can:settings.manage')->prefix('settings')->name('settings.')->group(function () {
    Route::get('company', [CompanyProfileController::class, 'edit'])->name('company.edit');
    Route::put('company', [CompanyProfileController::class, 'update'])->name('company.update');
    Route::get('attendance', [AttendanceSettingController::class, 'edit'])->name('attendance.edit');
    Route::put('attendance', [AttendanceSettingController::class, 'update'])->name('attendance.update');
    Route::get('payroll', [PayrollSettingController::class, 'edit'])->name('payroll.edit');
    Route::put('payroll', [PayrollSettingController::class, 'update'])->name('payroll.update');
});

Route::middleware('can:roles.manage')->prefix('settings')->name('settings.')->group(function () {
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::post('users', [CompanyUserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [CompanyUserController::class, 'update'])->name('users.update');
});

/* Audit */
Route::get('audit-logs', [AuditLogController::class, 'index'])
    ->middleware('can:audit.view')
    ->name('audit-logs.index');
