<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SalaryCalculationMethod;
use App\Enums\ShortHoursMode;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayrollSettingController extends Controller
{
    private const array FIELDS = [
        'salary_calculation_method', 'payroll_cycle', 'payroll_period_start_day', 'salary_payment_day',
        'overtime_rate_type', 'overtime_multiplier', 'overtime_fixed_rate', 'overtime_from_attendance',
        'short_hours_mode', 'short_hours_rate_type', 'short_hours_fixed_rate', 'borrow_auto_deduct',
        'borrow_max_deduction_percent',
    ];

    public function edit(): Response
    {
        $settings = $this->tenant()->settings();

        return Inertia::render('company-settings/Payroll', [
            'settings' => [
                ...$settings->only(self::FIELDS),
                'salary_calculation_method' => $settings->salary_calculation_method->value,
                'short_hours_mode' => $settings->short_hours_mode->value,
            ],
            'calculationMethods' => SalaryCalculationMethod::options(),
            'shortHoursModes' => ShortHoursMode::options(),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'salary_calculation_method' => ['required', Rule::enum(SalaryCalculationMethod::class)],
            'payroll_period_start_day' => ['required', 'integer', 'between:1,28'],
            'salary_payment_day' => ['required', 'integer', 'between:1,28'],
            'overtime_rate_type' => ['required', Rule::in([CompanySetting::OVERTIME_RATE_MULTIPLIER, CompanySetting::OVERTIME_RATE_FIXED])],
            'overtime_multiplier' => ['required', 'numeric', 'between:0,10'],
            'overtime_fixed_rate' => ['nullable', 'numeric', 'min:0', 'max:999999', 'required_if:overtime_rate_type,'.CompanySetting::OVERTIME_RATE_FIXED],
            'overtime_from_attendance' => ['required', 'boolean'],
            'short_hours_mode' => ['required', Rule::enum(ShortHoursMode::class)],
            'short_hours_rate_type' => ['required', Rule::in([CompanySetting::SHORT_HOURS_RATE_SALARY, CompanySetting::SHORT_HOURS_RATE_FIXED])],
            'short_hours_fixed_rate' => ['nullable', 'numeric', 'min:0', 'max:999999', 'required_if:short_hours_rate_type,'.CompanySetting::SHORT_HOURS_RATE_FIXED],
            'borrow_auto_deduct' => ['required', 'boolean'],
            'borrow_max_deduction_percent' => ['nullable', 'numeric', 'between:1,100'],
        ], [
            'overtime_fixed_rate.required_if' => 'Enter the fixed overtime rate per hour.',
            'short_hours_fixed_rate.required_if' => 'Enter the fixed short-hours rate per hour.',
        ]);

        $settings = $this->tenant()->settings();
        $original = $settings->getAttributes();
        $settings->update($validated);
        $this->tenant()->flushSettings();

        [$old, $new] = $audit->diff($settings, $original);

        if ($new !== []) {
            $audit->log('settings.payroll_updated', $settings, $old, $new, 'Payroll settings updated');
        }

        $this->toast('Payroll settings saved.');

        return back();
    }
}
