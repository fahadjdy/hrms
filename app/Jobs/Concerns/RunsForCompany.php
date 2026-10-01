<?php

namespace App\Jobs\Concerns;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Closure;

/**
 * Queued jobs run outside a request, so they carry the company id and set the
 * tenant context themselves before touching tenant-owned data.
 */
trait RunsForCompany
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function asCompany(int $companyId, Closure $callback): mixed
    {
        return app(TenantContext::class)->run(Company::query()->findOrFail($companyId), $callback);
    }
}
