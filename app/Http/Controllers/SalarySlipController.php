<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\SalarySlip;
use App\Services\SalarySlipService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Salary slips are available to authorized admin users only; there is no
 * employee login, so nothing here is reachable by an employee.
 */
class SalarySlipController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'payroll_id']);

        $slips = SalarySlip::query()
            ->when($filters['payroll_id'] ?? null, fn ($query, $id) => $query->where('payroll_id', $id))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('payrollItem', fn ($item) => $item
                ->where('employee_name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")))
            ->with(['payroll:id,period_year,period_month,period_start,period_end', 'payrollItem:id,employee_id,employee_name,employee_code,department_name,net_payable'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SalarySlip $slip): array => [
                'id' => $slip->id,
                'slip_number' => $slip->slip_number,
                'month' => $slip->payroll->label(),
                'employee_id' => $slip->payrollItem->employee_id,
                'employee_name' => $slip->payrollItem->employee_name,
                'employee_code' => $slip->payrollItem->employee_code,
                'department' => $slip->payrollItem->department_name,
                'net_payable' => $slip->payrollItem->net_payable,
                'generated_at' => $slip->generated_at?->toIso8601String(),
            ]);

        return Inertia::render('payroll/SalarySlips', [
            'slips' => $slips,
            'filters' => $filters,
            'payrolls' => Payroll::query()
                ->where('status', 'finalized')
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->get()
                ->map(fn (Payroll $payroll): array => ['id' => $payroll->id, 'label' => $payroll->label()]),
        ]);
    }

    /**
     * Download (or view) the salary slip PDF.
     */
    public function show(Request $request, SalarySlip $slip, SalarySlipService $slips): HttpResponse
    {
        $slip->load(['payrollItem.payroll', 'payrollItem.employee']);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($slips->contents($slip), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$slip->slip_number}.pdf\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
