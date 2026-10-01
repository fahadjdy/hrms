<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AttendanceCalculationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hrms:generate-attendance {--days=2 : How many days back to cover, including today}')]
#[Description('Store weekly offs, holidays and automatic-mode attendance for every active company')]
class GenerateDailyAttendance extends Command
{
    public function handle(TenantContext $tenant, AttendanceCalculationService $attendance): int
    {
        $days = max(1, (int) $this->option('days'));

        Company::query()->where('is_active', true)->orderBy('id')->each(function (Company $company) use ($tenant, $attendance, $days): void {
            $created = $tenant->run($company, function () use ($tenant, $attendance, $days): int {
                $today = $tenant->today();

                return $attendance->generateForRange($today->subDays($days - 1), $today);
            });

            $this->components->info("{$company->name}: {$created} record(s) generated.");
        });

        return self::SUCCESS;
    }
}
