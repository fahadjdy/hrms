<?php

namespace App\Services;

use App\Enums\SalaryCalculationMethod;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Support\Money;
use App\Support\PayrollPeriod;
use App\Support\Tenancy\TenantContext;

/**
 * Works out the monthly salary that applies to a payroll period, taking salary
 * revisions that start mid-period into account, plus the per-day and hourly
 * rates used for attendance, short-hours and overtime amounts.
 */
class SalaryCalculationService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly SalaryRevisionService $revisions,
    ) {}

    /**
     * The salary for a period.
     *
     * When more than one revision applies, each component is weighted by the
     * number of days its revision covers. Days before the first revision are
     * not part of the weighting; payroll treats them as days without salary.
     *
     * @return array{
     *     method: string,
     *     method_label: string,
     *     divisor: int,
     *     gross: float,
     *     fixed_deductions: float,
     *     per_day_rate: float,
     *     hourly_rate: float,
     *     required_minutes_per_day: int,
     *     salary_starts_on: string|null,
     *     components: list<array{code: string, name: string, type: string, amount: float}>,
     *     segments: list<array{revision_id: int, effective_date: string, from: string, to: string, days: int, gross: float, reason: string|null}>
     * }
     */
    public function forPeriod(Employee $employee, PayrollPeriod $period, int $workingDaysInPeriod, int $requiredMinutesPerDay): array
    {
        $segments = $this->revisions->segments($employee, $period);
        $coveredDays = array_sum(array_column($segments, 'days'));
        $components = [];

        foreach ($segments as $segment) {
            $weight = $segment['days'] / $coveredDays;

            foreach ($segment['revision']->components as $component) {
                $key = $component->type.':'.$component->code;

                $components[$key] ??= [
                    'code' => $component->code,
                    'name' => $component->name,
                    'type' => $component->type,
                    'amount' => 0.0,
                ];
                $components[$key]['amount'] += $component->amount * $weight;
            }
        }

        $components = array_values(array_map(
            fn (array $component): array => [...$component, 'amount' => Money::round($component['amount'])],
            $components,
        ));

        $gross = Money::sum(array_column(
            array_filter($components, fn (array $c): bool => $c['type'] === EmployeeSalaryComponent::TYPE_EARNING),
            'amount',
        ));
        $fixedDeductions = Money::sum(array_column(
            array_filter($components, fn (array $c): bool => $c['type'] === EmployeeSalaryComponent::TYPE_DEDUCTION),
            'amount',
        ));

        $method = $this->tenant->settings()->salary_calculation_method;
        $divisor = max(1, match ($method) {
            SalaryCalculationMethod::CalendarDays => $period->days(),
            SalaryCalculationMethod::WorkingDays => $workingDaysInPeriod,
            SalaryCalculationMethod::Fixed30 => 30,
        });

        $perDay = $gross / $divisor;
        $requiredMinutesPerDay = max(1, $requiredMinutesPerDay);

        return [
            'method' => $method->value,
            'method_label' => $method->label(),
            'divisor' => $divisor,
            'gross' => $gross,
            'fixed_deductions' => $fixedDeductions,
            'per_day_rate' => Money::round($perDay),
            'hourly_rate' => Money::round($perDay / ($requiredMinutesPerDay / 60)),
            'required_minutes_per_day' => $requiredMinutesPerDay,
            'salary_starts_on' => $segments !== [] ? $segments[0]['from']->toDateString() : null,
            'components' => $components,
            'segments' => array_map(fn (array $segment): array => [
                'revision_id' => $segment['revision']->id,
                'effective_date' => $segment['revision']->effective_date->toDateString(),
                'from' => $segment['from']->toDateString(),
                'to' => $segment['to']->toDateString(),
                'days' => $segment['days'],
                'gross' => $segment['revision']->new_gross,
                'reason' => $segment['revision']->reason,
            ], $segments),
        ];
    }
}
