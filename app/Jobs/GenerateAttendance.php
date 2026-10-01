<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsForCompany;
use App\Services\AttendanceCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Bulk attendance generation for a date range.
 */
class GenerateAttendance implements ShouldQueue
{
    use Queueable, RunsForCompany;

    public int $timeout = 1800;

    public function __construct(public int $companyId, public string $startDate, public string $endDate) {}

    public function handle(AttendanceCalculationService $attendance): void
    {
        $this->asCompany($this->companyId, function () use ($attendance): void {
            $attendance->generateForRange(
                CarbonImmutable::parse($this->startDate),
                CarbonImmutable::parse($this->endDate),
            );
        });
    }
}
