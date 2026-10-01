<?php

namespace App\Http\Controllers;

use App\Enums\BorrowStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Services\AttendanceCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Attendance and borrow reports, one page of employees at a time.
     */
    public function index(Request $request, AttendanceCalculationService $attendance): Response
    {
        $validated = $request->validate([
            'report' => ['nullable', 'in:attendance,borrow'],
            'month' => ['nullable', 'date_format:Y-m'],
            'department_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $report = $validated['report'] ?? 'attendance';
        $month = $this->month($validated['month'] ?? null);

        $employees = $this->employees($report, $validated)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                ...$employee->toBrief(),
                ...($report === 'attendance'
                    ? ['summary' => $attendance->summarize($attendance->resolveDays($employee, $month, $month->endOfMonth()->startOfDay()))]
                    : $this->borrowTotals($employee)),
            ]);

        return Inertia::render('reports/Index', [
            'report' => $report,
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->format('F Y'),
            'filters' => [
                'department_id' => $validated['department_id'] ?? null,
                'search' => $validated['search'] ?? '',
            ],
            'employees' => $employees,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'payrolls' => Payroll::query()
                ->where('status', '!=', 'draft')
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->limit(24)
                ->get()
                ->map(fn (Payroll $payroll): array => ['id' => $payroll->id, 'label' => $payroll->label()]),
        ]);
    }

    /**
     * Export a report as CSV. Rows are streamed in chunks, so the size of
     * the company does not affect memory use.
     */
    public function export(Request $request, string $report, AttendanceCalculationService $attendance): StreamedResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'department_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'payroll_id' => [$report === 'payroll' ? 'required' : 'nullable', 'integer'],
        ]);

        $month = $this->month($validated['month'] ?? null);

        return match ($report) {
            'attendance' => $this->csv("attendance-{$month->format('Y-m')}.csv", [
                'Employee ID', 'Name', 'Department', 'Working Days', 'Present', 'Absent', 'Half Day', 'Paid Leave',
                'Unpaid Leave', 'Weekly Off', 'Holidays', 'WFH', 'Late', 'Unmarked', 'Required Hours', 'Worked Hours',
                'Short Hours', 'Overtime Hours', 'Attendance Rate %',
            ], function () use ($validated, $attendance, $month) {
                foreach ($this->employees('attendance', $validated)->lazy(200) as $employee) {
                    $summary = $attendance->summarize($attendance->resolveDays($employee, $month, $month->endOfMonth()->startOfDay()));

                    yield [
                        $employee->employee_code, $employee->full_name, $employee->department?->name,
                        $summary['working_days'], $summary['present'], $summary['absent'], $summary['half_day'],
                        $summary['paid_leave'], $summary['unpaid_leave'], $summary['weekly_off'], $summary['holidays'],
                        $summary['wfh'], $summary['late'], $summary['unmarked'],
                        round($summary['required_minutes'] / 60, 2), round($summary['worked_minutes'] / 60, 2),
                        round($summary['short_minutes'] / 60, 2), round($summary['overtime_minutes'] / 60, 2),
                        $summary['attendance_rate'],
                    ];
                }
            }),
            'borrow' => $this->csv('borrow-report.csv', [
                'Employee ID', 'Name', 'Department', 'Borrow Records', 'Total Borrowed', 'Total Recovered', 'Outstanding',
            ], function () use ($validated) {
                foreach ($this->employees('borrow', $validated)->lazy(200) as $employee) {
                    $totals = $this->borrowTotals($employee);

                    yield [
                        $employee->employee_code, $employee->full_name, $employee->department?->name,
                        $totals['borrow_count'], $totals['total_borrowed'], $totals['total_recovered'], $totals['total_outstanding'],
                    ];
                }
            }),
            default => $this->payrollCsv(Payroll::query()->findOrFail((int) $validated['payroll_id'])),
        };
    }

    private function payrollCsv(Payroll $payroll): StreamedResponse
    {
        return $this->csv("payroll-{$payroll->period_year}-".str_pad((string) $payroll->period_month, 2, '0', STR_PAD_LEFT).'.csv', [
            'Employee ID', 'Name', 'Department', 'Designation', 'Gross Salary', 'Overtime', 'Bonus', 'Other Earnings',
            'Attendance Deduction', 'Unpaid Leave', 'Short Hours Deduction', 'Borrow Recovery', 'Other Deductions',
            'Net Salary', 'New Borrow / Advance', 'Net Payable',
        ], function () use ($payroll) {
            $items = PayrollItem::query()->where('payroll_id', $payroll->id)->orderBy('employee_name')->orderBy('id')->lazy(500);

            foreach ($items as $item) {
                yield [
                    $item->employee_code, $item->employee_name, $item->department_name, $item->designation_name,
                    $item->gross_salary, $item->overtime_amount, $item->bonus_amount, $item->other_earnings_amount,
                    $item->attendance_deduction, $item->unpaid_leave_deduction, $item->short_hours_deduction,
                    $item->borrow_recovery, $item->other_deductions, $item->net_salary, $item->borrow_given, $item->net_payable,
                ];
            }
        });
    }

    /**
     * @param  list<string>  $header
     * @param  callable(): iterable<list<mixed>>  $rows
     */
    private function csv(string $filename, array $header, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, $header, escape: '\\');

            foreach ($rows() as $row) {
                // Neutralize values a spreadsheet would run as a formula.
                fputcsv($output, array_map(
                    fn (mixed $value): mixed => is_string($value) && preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value,
                    $row,
                ), escape: '\\');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Employee>
     */
    private function employees(string $report, array $filters): Builder
    {
        return Employee::query()
            ->search($filters['search'] ?? null)
            ->when($filters['department_id'] ?? null, fn (Builder $query, $id) => $query->where('department_id', $id))
            ->when($report === 'attendance', fn (Builder $query) => $query->current()->with('shiftAssignments'))
            ->when($report === 'borrow', fn (Builder $query) => $query->whereHas('borrows')->with('borrows'))
            ->with(['department:id,name', 'designation:id,name'])
            ->orderBy('first_name')
            ->orderBy('id');
    }

    /**
     * @return array{borrow_count: int, total_borrowed: float, total_recovered: float, total_outstanding: float}
     */
    private function borrowTotals(Employee $employee): array
    {
        $issued = $employee->borrows->filter(fn ($borrow): bool => in_array($borrow->status, [BorrowStatus::Active, BorrowStatus::Recovered], true));

        return [
            'borrow_count' => $issued->count(),
            'total_borrowed' => round((float) $issued->sum('opening_balance'), 2),
            'total_recovered' => round((float) $issued->sum('recovered_amount'), 2),
            'total_outstanding' => round((float) $issued->where('status', BorrowStatus::Active)->sum('outstanding_amount'), 2),
        ];
    }

    private function month(?string $month): CarbonImmutable
    {
        return $month !== null
            ? CarbonImmutable::createFromFormat('!Y-m', $month)
            : $this->tenant()->today()->startOfMonth();
    }
}
