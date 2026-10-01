<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;

abstract class Controller
{
    /**
     * Flash a toast notification for the next page.
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }

    protected function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    protected function company(): Company
    {
        return $this->tenant()->require();
    }
}
