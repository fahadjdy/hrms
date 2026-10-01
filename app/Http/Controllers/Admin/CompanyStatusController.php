<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyStatusController extends Controller
{
    /**
     * Activate or deactivate a company. Users of a deactivated company cannot sign in.
     */
    public function update(Request $request, Company $company, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        $company->is_active = $validated['is_active'];
        $company->save();

        $this->tenant()->run($company, fn () => $audit->log(
            $company->is_active ? 'company.activated' : 'company.deactivated',
            $company,
            ['is_active' => ! $company->is_active],
            ['is_active' => $company->is_active],
            "Company {$company->name} ".($company->is_active ? 'activated' : 'deactivated'),
        ));

        $this->toast($company->is_active ? 'Company activated.' : 'Company deactivated.');

        return back();
    }
}
