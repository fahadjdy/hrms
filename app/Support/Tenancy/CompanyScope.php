<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class CompanyScope implements Scope
{
    /**
     * Restrict every query to the current company.
     *
     * Fails closed: without a tenant the query matches nothing, so a missing
     * context can never leak another company's rows.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantContext::class);

        if (! $tenant->check()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $tenant->id());
    }
}
