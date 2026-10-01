<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanyProfileController extends Controller
{
    public function edit(): Response
    {
        $company = $this->company();

        return Inertia::render('company-settings/Company', [
            'companyProfile' => [
                ...$company->only([
                    'name', 'legal_name', 'email', 'phone', 'address', 'city', 'state', 'country',
                    'postal_code', 'tax_id', 'currency', 'timezone', 'date_format',
                ]),
                'logo_url' => $company->logoUrl(),
            ],
            'timezones' => timezone_identifiers_list(),
            'dateFormats' => CompanyRequest::DATE_FORMATS,
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate(CompanyRequest::companyRules());
        $original = $company->getAttributes();

        $company->fill(Arr::except($validated, ['logo']));

        if ($request->hasFile('logo')) {
            if ($company->logo_path !== null) {
                Storage::disk('public')->delete($company->logo_path);
            }

            $company->logo_path = $request->file('logo')->store("companies/{$company->id}", 'public') ?: null;
        }

        $company->save();
        [$old, $new] = $audit->diff($company, $original);

        if ($new !== []) {
            $audit->log('company.updated', $company, $old, $new, 'Company profile updated');
        }

        $this->toast('Company details saved.');

        return back();
    }
}
