<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeSalaryRevision;
use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceCalculationService;
use App\Services\CompanyProvisioner;
use App\Services\SalaryRevisionService;
use App\Services\WorkingCalendarService;
use App\Services\WorkingHoursCalculationService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Helpers for tests that work inside a company (tenant).
 *
 * A provisioned company has: a "General Shift" (09:00-18:00, 8 required
 * hours, 1 hour break) as the default shift, Sunday as the weekly off, the
 * built-in roles and leave types, and one Company Admin.
 */
trait InteractsWithCompanies
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createCompany(array $attributes = []): Company
    {
        return app(CompanyProvisioner::class)->provision(
            [
                'name' => fake()->unique()->company(),
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'date_format' => 'd M Y',
                ...$attributes,
            ],
            [
                'name' => 'Company Admin',
                'email' => fake()->unique()->safeEmail(),
                'password' => 'password',
            ],
        );
    }

    protected function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    /**
     * Make the company the current tenant for code run directly by the test.
     */
    protected function useCompany(Company $company): Company
    {
        $this->tenant()->set($company);
        app(WorkingCalendarService::class)->flush();
        app(WorkingHoursCalculationService::class)->flush();

        return $company;
    }

    protected function adminOf(Company $company): User
    {
        return User::query()
            ->where('company_id', $company->id)
            ->oldest('id')
            ->firstOrFail();
    }

    /**
     * A user of the company with one of its built-in roles: 'hr-manager' or 'viewer'.
     */
    protected function userWithRole(Company $company, string $roleSlug): User
    {
        $role = Role::withoutTenancy()
            ->where('company_id', $company->id)
            ->where('slug', $roleSlug)
            ->firstOrFail();

        return User::factory()->forCompany($company, $role)->create();
    }

    /**
     * Change settings of the current company.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function configure(array $settings): void
    {
        $this->tenant()->settings()->update($settings);
        $this->tenant()->flushSettings();
    }

    /**
     * Create an employee in the current company, optionally with a monthly basic salary.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createEmployee(array $attributes = [], ?float $monthlySalary = null): Employee
    {
        $employee = Employee::factory()->create($attributes);

        if ($monthlySalary !== null) {
            $this->setSalary($employee, $monthlySalary, $employee->joining_date->toDateString());
        }

        return $employee;
    }

    /**
     * @param  list<array{name: string, type: string, amount: float|int}>  $extraComponents
     */
    protected function setSalary(Employee $employee, float $basic, string $effectiveDate, array $extraComponents = [], ?string $reason = null): EmployeeSalaryRevision
    {
        return app(SalaryRevisionService::class)->revise($employee, [
            'effective_date' => $effectiveDate,
            'reason' => $reason,
            'components' => [
                ['name' => 'Basic', 'type' => 'earning', 'amount' => $basic],
                ...$extraComponents,
            ],
        ]);
    }

    /**
     * Mark one day of attendance for an employee.
     */
    protected function markAttendance(Employee $employee, string $date, string $status, ?string $checkIn = null, ?string $checkOut = null): void
    {
        app(AttendanceCalculationService::class)->record($employee, CarbonImmutable::parse($date), [
            'status' => $status,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);
    }
}
