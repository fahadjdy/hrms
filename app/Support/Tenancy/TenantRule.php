<?php

namespace App\Support\Tenancy;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules that only ever look at the current company's rows.
 *
 * An id from the request is never trusted on its own: `exists` must match a
 * row of this company, so an id that belongs to another company fails
 * validation exactly like an id that does not exist.
 */
class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('company_id', app(TenantContext::class)->require()->id);
    }

    public static function unique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('company_id', app(TenantContext::class)->require()->id);
    }
}
