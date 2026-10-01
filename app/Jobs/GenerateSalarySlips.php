<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsForCompany;
use App\Models\SalarySlip;
use App\Services\SalarySlipService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pre-generates the salary slip PDFs of a finalized payroll. A slip that has
 * not been generated yet is still produced on demand when it is downloaded.
 */
class GenerateSalarySlips implements ShouldQueue
{
    use Queueable, RunsForCompany;

    public int $timeout = 1800;

    public function __construct(public int $companyId, public int $payrollId) {}

    public function handle(SalarySlipService $slips): void
    {
        $this->asCompany($this->companyId, function () use ($slips): void {
            SalarySlip::query()
                ->where('payroll_id', $this->payrollId)
                ->whereNull('file_path')
                ->with('payrollItem.payroll', 'payrollItem.employee')
                ->chunkById(50, function (Collection $chunk) use ($slips): void {
                    foreach ($chunk as $slip) {
                        $slips->generate($slip);
                    }
                });
        });
    }
}
