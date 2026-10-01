<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyRequest;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/Settings', [
            'settings' => PlatformSetting::allValues(),
            'timezones' => timezone_identifiers_list(),
            'dateFormats' => CompanyRequest::DATE_FORMATS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform_name' => ['required', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'default_currency' => ['required', 'string', 'size:3', 'alpha', 'uppercase'],
            'default_timezone' => ['required', 'timezone:all'],
            'default_date_format' => ['required', Rule::in(CompanyRequest::DATE_FORMATS)],
        ]);

        foreach ($validated as $key => $value) {
            PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        $this->toast('Platform settings saved.');

        return back();
    }
}
