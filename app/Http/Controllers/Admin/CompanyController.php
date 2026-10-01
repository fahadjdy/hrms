<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\CompanyProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-level company management. This is the only area that looks across
 * tenants, and it only ever reads counts and totals from tenant-owned tables.
 */
class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $companies = Company::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->withCount('users')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $employeeCounts = Employee::withoutTenancy()
            ->whereIn('company_id', $companies->pluck('id'))
            ->selectRaw('company_id, count(*) as total')
            ->selectRaw('sum(case when status <> ? then 1 else 0 end) as current', [EmployeeStatus::Past->value])
            ->groupBy('company_id')
            ->toBase()
            ->get()
            ->keyBy('company_id');

        return Inertia::render('admin/companies/Index', [
            'companies' => $companies->through(fn (Company $company): array => [
                ...$this->present($company),
                'users_count' => $company->users_count,
                'employees_count' => (int) ($employeeCounts[$company->id]->total ?? 0),
                'active_employees_count' => (int) ($employeeCounts[$company->id]->current ?? 0),
            ]),
            'filters' => ['search' => $search, 'status' => $status],
            'stats' => [
                'companies' => Company::query()->count(),
                'active_companies' => Company::query()->where('is_active', true)->count(),
                'employees' => Employee::withoutTenancy()->whereIn('status', EmployeeStatus::currentValues())->count(),
                'users' => User::query()->whereNotNull('company_id')->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        $platform = PlatformSetting::allValues();

        return Inertia::render('admin/companies/Create', [
            ...$this->formOptions(),
            // A new company starts from the platform's regional defaults.
            'defaults' => [
                'currency' => $platform['default_currency'],
                'timezone' => $platform['default_timezone'],
                'date_format' => $platform['default_date_format'],
            ],
        ]);
    }

    public function store(CompanyRequest $request, CompanyProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validated();

        $company = $provisioner->provision(
            Arr::except($data, ['logo', 'admin_name', 'admin_email', 'admin_password']),
            ['name' => $data['admin_name'], 'email' => $data['admin_email'], 'password' => $data['admin_password']],
        );

        $this->storeLogo($request, $company);

        $this->toast("Company {$company->name} created.");

        return to_route('admin.companies.show', $company);
    }

    public function show(Company $company): Response
    {
        $employees = Employee::withoutTenancy()->where('company_id', $company->id);
        $lastPayroll = Payroll::withoutTenancy()
            ->where('company_id', $company->id)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        return Inertia::render('admin/companies/Show', [
            // Not `company`: that key is the shared prop for the viewer's own tenant.
            'managedCompany' => $this->present($company),
            'stats' => [
                'employees' => (clone $employees)->count(),
                'active_employees' => (clone $employees)->whereIn('status', EmployeeStatus::currentValues())->count(),
                'past_employees' => (clone $employees)->where('status', EmployeeStatus::Past->value)->count(),
                'users' => $company->users()->count(),
                'last_payroll' => $lastPayroll === null ? null : [
                    'label' => $lastPayroll->label(),
                    'status' => $lastPayroll->status->label(),
                    'net_payable' => $lastPayroll->total_net_payable,
                ],
            ],
            'users' => $company->users()
                ->with('role')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->name,
                    'is_active' => $user->is_active,
                ]),
        ]);
    }

    public function edit(Company $company): Response
    {
        return Inertia::render('admin/companies/Edit', [
            // Not `company`: that key is the shared prop for the viewer's own tenant.
            'managedCompany' => $this->present($company),
            ...$this->formOptions(),
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update(Arr::except($request->validated(), ['logo']));
        $this->storeLogo($request, $company);

        $this->toast('Company updated.');

        return to_route('admin.companies.show', $company);
    }

    private function storeLogo(Request $request, Company $company): void
    {
        if (! $request->hasFile('logo')) {
            return;
        }

        if ($company->logo_path !== null) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $company->logo_path = $request->file('logo')->store("companies/{$company->id}", 'public') ?: null;
        $company->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Company $company): array
    {
        return [
            ...$company->only([
                'id', 'name', 'legal_name', 'slug', 'email', 'phone', 'address', 'city', 'state', 'country',
                'postal_code', 'tax_id', 'currency', 'timezone', 'date_format', 'is_active',
            ]),
            'logo_url' => $company->logoUrl(),
            'created_at' => $company->created_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'timezones' => timezone_identifiers_list(),
            'dateFormats' => CompanyRequest::DATE_FORMATS,
        ];
    }
}
