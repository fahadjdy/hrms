<?php

namespace App\Services;

use App\Enums\PayrollBucket;
use App\Models\PayrollItem;
use App\Models\SalarySlip;
use App\Support\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Salary slip PDFs. Slips exist only for finalized payrolls and are available
 * to authorized admin users only; employees have no login.
 */
class SalarySlipService
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Render the slip for a payroll item as PDF bytes.
     */
    public function render(PayrollItem $item): string
    {
        return Pdf::loadView('pdf.salary-slip', $this->viewData($item))
            ->setPaper('a4')
            // Embed only the glyphs the slip uses instead of the whole font.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /**
     * Render and store the slip, returning the stored record.
     */
    public function generate(SalarySlip $slip): SalarySlip
    {
        $slip->loadMissing('payrollItem.payroll');

        $path = "salary-slips/{$slip->company_id}/{$slip->payroll_id}/{$slip->slip_number}.pdf";
        Storage::disk('local')->put($path, $this->render($slip->payrollItem));

        $slip->file_path = $path;
        $slip->generated_at = now();
        $slip->save();

        return $slip;
    }

    /**
     * The PDF bytes of a slip, generating the file on first use.
     */
    public function contents(SalarySlip $slip): string
    {
        if ($slip->file_path === null || ! Storage::disk('local')->exists($slip->file_path)) {
            $this->generate($slip);
        }

        return (string) Storage::disk('local')->get((string) $slip->file_path);
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(PayrollItem $item): array
    {
        $item->loadMissing(['payroll', 'employee', 'salarySlip']);
        $company = $this->tenant->require();
        $breakdown = $item->breakdown ?? [];
        $lines = collect(PayrollCalculationService::linesOf($breakdown));

        $group = fn (array $buckets) => $lines
            ->filter(fn (array $line): bool => in_array($line['bucket'], array_map(fn (PayrollBucket $b): string => $b->value, $buckets), true) && $line['amount'] != 0)
            ->values();

        $adjustments = collect(PayrollCalculationService::bucketsOf($breakdown))
            ->filter(fn (array $bucket): bool => $bucket['adjustment'] != 0)
            ->map(fn (array $bucket, string $key): array => [
                'label' => $bucket['label'].' (adjustment)',
                'amount' => $bucket['adjustment'],
                'bucket' => $key,
            ]);

        $logo = null;

        if ($company->logo_path !== null && Storage::disk('public')->exists($company->logo_path)) {
            $logo = 'data:'.Storage::disk('public')->mimeType($company->logo_path).';base64,'
                .base64_encode((string) Storage::disk('public')->get($company->logo_path));
        }

        return [
            'company' => $company,
            'logo' => $logo,
            'item' => $item,
            'payroll' => $item->payroll,
            'employee' => $item->employee,
            'slip' => $item->salarySlip,
            'summary' => $item->attendance_summary ?? [],
            'earnings' => $group([PayrollBucket::Salary, PayrollBucket::Overtime, PayrollBucket::Bonus, PayrollBucket::OtherEarnings]),
            'deductions' => $group([
                PayrollBucket::AttendanceDeduction, PayrollBucket::UnpaidLeave, PayrollBucket::ShortHours, PayrollBucket::OtherDeductions,
            ]),
            'recoveries' => $group([PayrollBucket::BorrowRecovery]),
            'given' => $group([PayrollBucket::BorrowGiven]),
            'earningAdjustments' => $adjustments->filter(fn (array $a): bool => PayrollBucket::from($a['bucket'])->isEarning())->values(),
            'deductionAdjustments' => $adjustments->filter(fn (array $a): bool => PayrollBucket::from($a['bucket'])->isDeduction())->values(),
            'totals' => $breakdown['totals'] ?? [],
            'currency' => $company->currency,
        ];
    }
}
