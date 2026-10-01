<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsForCompany;
use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CalculatePayroll implements ShouldBeUnique, ShouldQueue
{
    use Queueable, RunsForCompany;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $companyId, public int $payrollId) {}

    public function uniqueId(): string
    {
        return "payroll-{$this->payrollId}";
    }

    public function handle(PayrollService $payrolls): void
    {
        $this->asCompany($this->companyId, function () use ($payrolls): void {
            $payrolls->calculate(Payroll::query()->findOrFail($this->payrollId));
        });
    }
}
