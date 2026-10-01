<?php

namespace App\Http\Controllers;

use App\Enums\PayrollBucket;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use Inertia\Inertia;
use Inertia\Response;

class PayrollItemController extends Controller
{
    /**
     * The full breakdown of one employee's pay: every line, how it was
     * derived, and every manual adjustment with who made it and why.
     */
    public function show(Payroll $payroll, PayrollItem $item): Response
    {
        $item->load(['adjustments.user:id,name', 'salarySlip:id,payroll_item_id']);
        $breakdown = $item->breakdown ?? [];

        return Inertia::render('payroll/Item', [
            'payroll' => [
                'id' => $payroll->id,
                'label' => $payroll->label(),
                'status' => $payroll->status->value,
                'status_label' => $payroll->status->label(),
                'is_locked' => $payroll->isLocked(),
                'period_start' => $payroll->period_start->toDateString(),
                'period_end' => $payroll->period_end->toDateString(),
            ],
            'item' => [
                ...$item->only([
                    'id', 'employee_id', 'employee_name', 'employee_code', 'department_name', 'designation_name',
                    'net_salary', 'net_payable', 'is_adjusted',
                ]),
                'slip_id' => $item->salarySlip?->id,
            ],
            'salary' => $breakdown['salary'] ?? null,
            'attendance' => $item->attendance_summary ?? [],
            'shortHours' => $breakdown['short_hours'] ?? null,
            'lines' => $breakdown['lines'] ?? [],
            'buckets' => $breakdown['buckets'] ?? [],
            'totals' => $breakdown['totals'] ?? [],
            'warnings' => $breakdown['warnings'] ?? [],
            'adjustments' => $item->adjustments->sortByDesc('id')->values()->map(fn (PayrollAdjustment $adjustment): array => [
                'id' => $adjustment->id,
                'bucket' => $adjustment->bucket->value,
                'bucket_label' => $adjustment->bucket->label(),
                'amount' => $adjustment->amount,
                'reason' => $adjustment->reason,
                'user' => $adjustment->user?->name,
                'created_at' => $adjustment->created_at?->toIso8601String(),
            ]),
            'adjustableBuckets' => array_map(
                fn (PayrollBucket $bucket): array => [
                    'value' => $bucket->value,
                    'label' => $bucket->label(),
                    'is_deduction' => $bucket->isDeduction(),
                ],
                PayrollBucket::adjustable(),
            ),
        ]);
    }
}
