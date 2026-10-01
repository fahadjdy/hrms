<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollReportController extends Controller
{
    /**
     * Payroll reports: month-by-month totals and a department breakdown for one payroll.
     */
    public function index(Request $request): Response
    {
        $payrolls = Payroll::query()
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(24)
            ->get();

        $selectedId = $request->integer('payroll_id') ?: $payrolls->first()?->id;
        $selected = $payrolls->firstWhere('id', $selectedId);

        $departments = $selected === null ? collect() : PayrollItem::query()
            ->where('payroll_id', $selected->id)
            ->selectRaw("coalesce(department_name, 'No department') as department")
            ->selectRaw('count(*) as employees')
            ->selectRaw('sum(gross_salary) as gross')
            ->selectRaw('sum(overtime_amount) as overtime')
            ->selectRaw('sum(bonus_amount + other_earnings_amount) as bonus')
            ->selectRaw('sum(attendance_deduction + unpaid_leave_deduction) as attendance_deductions')
            ->selectRaw('sum(short_hours_deduction) as short_hours')
            ->selectRaw('sum(borrow_recovery) as borrow_recovery')
            ->selectRaw('sum(other_deductions) as other_deductions')
            ->selectRaw('sum(borrow_given) as borrow_given')
            ->selectRaw('sum(net_payable) as net_payable')
            ->groupBy('department')
            ->orderBy('department')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'department' => $row->department,
                'employees' => (int) $row->employees,
                'gross' => (float) $row->gross,
                'overtime' => (float) $row->overtime,
                'bonus' => (float) $row->bonus,
                'attendance_deductions' => (float) $row->attendance_deductions,
                'short_hours' => (float) $row->short_hours,
                'borrow_recovery' => (float) $row->borrow_recovery,
                'other_deductions' => (float) $row->other_deductions,
                'borrow_given' => (float) $row->borrow_given,
                'net_payable' => (float) $row->net_payable,
            ]);

        return Inertia::render('payroll/Reports', [
            'payrolls' => $payrolls->map(fn (Payroll $payroll): array => [
                'id' => $payroll->id,
                'label' => $payroll->label(),
                'status' => $payroll->status->value,
                'status_label' => $payroll->status->label(),
                'employee_count' => $payroll->employee_count,
                'total_gross' => $payroll->total_gross,
                'total_earnings' => $payroll->total_earnings,
                'total_deductions' => $payroll->total_deductions,
                'total_borrow_given' => $payroll->total_borrow_given,
                'total_borrow_recovery' => $payroll->total_borrow_recovery,
                'total_net_payable' => $payroll->total_net_payable,
            ]),
            'selectedPayrollId' => $selected?->id,
            'departments' => $departments,
        ]);
    }
}
