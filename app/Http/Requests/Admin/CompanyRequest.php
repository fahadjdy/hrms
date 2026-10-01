<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CompanyRequest extends FormRequest
{
    public const array DATE_FORMATS = ['d M Y', 'd/m/Y', 'm/d/Y', 'Y-m-d', 'd-m-Y'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = self::companyRules();

        // A new company is created together with its first Company Admin.
        if (! $this->route('company') instanceof Company) {
            $rules += [
                'admin_name' => ['required', 'string', 'max:255'],
                'admin_email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
                'admin_password' => ['required', 'string', Password::defaults()],
            ];
        }

        return $rules;
    }

    /**
     * Rules for the company profile, shared with the company's own settings page.
     *
     * @return array<string, array<mixed>>
     */
    public static function companyRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'currency' => ['required', 'string', 'size:3', 'alpha', 'uppercase'],
            'timezone' => ['required', 'timezone:all'],
            'date_format' => ['required', Rule::in(self::DATE_FORMATS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tax_id' => 'GST / tax number',
            'admin_name' => 'admin name',
            'admin_email' => 'admin email',
            'admin_password' => 'admin password',
        ];
    }
}
