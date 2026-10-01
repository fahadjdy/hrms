<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Permission: string
{
    use HasOptions;

    case EmployeesView = 'employees.view';
    case EmployeesManage = 'employees.manage';
    case AttendanceView = 'attendance.view';
    case AttendanceManage = 'attendance.manage';
    case LeaveView = 'leave.view';
    case LeaveManage = 'leave.manage';
    case PayrollView = 'payroll.view';
    case PayrollManage = 'payroll.manage';
    case PayrollFinalize = 'payroll.finalize';
    case FinanceView = 'finance.view';
    case FinanceManage = 'finance.manage';
    case SettlementsManage = 'settlements.manage';
    case ReportsView = 'reports.view';
    case SettingsManage = 'settings.manage';
    case RolesManage = 'roles.manage';
    case AuditView = 'audit.view';

    public function label(): string
    {
        return match ($this) {
            self::EmployeesView => 'View employees',
            self::EmployeesManage => 'Add, edit and exit employees',
            self::AttendanceView => 'View attendance',
            self::AttendanceManage => 'Mark and edit attendance',
            self::LeaveView => 'View leave',
            self::LeaveManage => 'Manage leave',
            self::PayrollView => 'View payroll and salary',
            self::PayrollManage => 'Run payroll and revise salary',
            self::PayrollFinalize => 'Finalize and reopen payroll',
            self::FinanceView => 'View borrow, overtime, bonuses and deductions',
            self::FinanceManage => 'Manage borrow, overtime, bonuses and deductions',
            self::SettlementsManage => 'Manage final settlements',
            self::ReportsView => 'View reports',
            self::SettingsManage => 'Manage company settings',
            self::RolesManage => 'Manage users, roles and permissions',
            self::AuditView => 'View audit logs',
        };
    }

    /**
     * Permissions granted to the read-only role.
     *
     * @return list<string>
     */
    public static function viewOnly(): array
    {
        return [
            self::EmployeesView->value,
            self::AttendanceView->value,
            self::LeaveView->value,
            self::PayrollView->value,
            self::FinanceView->value,
            self::ReportsView->value,
        ];
    }
}
