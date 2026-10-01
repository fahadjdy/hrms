<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use App\Models\CompanySetting;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Context;

/**
 * Holds the company the current request, job or command is working for.
 *
 * The tenant is always resolved on the server (from the authenticated user or
 * from a job payload) and never from request input. Tenant-owned models read
 * this context through CompanyScope, so every query is isolated by default.
 */
class TenantContext
{
    private ?Company $company = null;

    private ?CompanySetting $settings = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
        $this->settings = null;

        if ($company === null) {
            Context::forget('company_id');

            return;
        }

        Context::add('company_id', $company->id);
    }

    public function forget(): void
    {
        $this->set(null);
    }

    public function check(): bool
    {
        return $this->company !== null;
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function require(): Company
    {
        if ($this->company === null) {
            throw new MissingTenantException('No company is set for the current operation.');
        }

        return $this->company;
    }

    /**
     * Run a callback as the given company, restoring the previous tenant afterwards.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Company $company, Closure $callback): mixed
    {
        $previous = $this->company;
        $this->set($company);

        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }

    public function settings(): CompanySetting
    {
        $this->require();

        // refresh() loads the column defaults that the database filled in.
        return $this->settings ??= CompanySetting::query()->first()
            ?? CompanySetting::query()->create()->refresh();
    }

    public function flushSettings(): void
    {
        $this->settings = null;
    }

    public function timezone(): string
    {
        return $this->company->timezone ?? config('app.timezone');
    }

    /**
     * "Now" in the company's timezone.
     */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /**
     * Today's calendar date for the company, as a timezone-free date.
     */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->now()->toDateString());
    }
}
