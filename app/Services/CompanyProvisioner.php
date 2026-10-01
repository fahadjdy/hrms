<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Models\WeeklyHoliday;
use App\Models\WorkShift;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a company (tenant) with everything it needs to be usable straight
 * away: settings, roles, a default shift, weekly off, leave types and its
 * first Company Admin.
 */
class CompanyProvisioner
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $companyData
     * @param  array{name: string, email: string, password: string}  $adminData
     */
    public function provision(array $companyData, array $adminData): Company
    {
        return DB::transaction(function () use ($companyData, $adminData): Company {
            $company = new Company($companyData);
            $company->slug = $this->uniqueSlug($companyData['name']);
            $company->is_active = true;
            $company->save();

            $this->tenant->run($company, function () use ($company, $adminData): void {
                $shift = WorkShift::query()->create([
                    'name' => 'General Shift',
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                    'required_minutes' => 480,
                    'break_minutes' => 60,
                ]);

                CompanySetting::query()->create(['default_work_shift_id' => $shift->id]);
                WeeklyHoliday::query()->create(['day_of_week' => 0]);

                foreach ([
                    ['Casual Leave', 'CL', true, 12],
                    ['Sick Leave', 'SL', true, 12],
                    ['Paid Leave', 'PL', true, 15],
                    ['Unpaid Leave', 'UL', false, 0],
                ] as [$name, $code, $paid, $allowance]) {
                    LeaveType::query()->create([
                        'name' => $name,
                        'code' => $code,
                        'is_paid' => $paid,
                        'annual_allowance' => $allowance,
                    ]);
                }

                $adminRole = $this->createRoles();
                $this->createAdmin($company, $adminRole, $adminData);

                $this->audit->log('company.created', $company, null, $company->only(['name', 'email', 'currency', 'timezone']), "Company {$company->name} created");
            });

            return $company;
        });
    }

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function createAdmin(Company $company, Role $role, array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->company_id = $company->id;
        $user->role_id = $role->id;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * Create the built-in roles for the current company and return the admin role.
     */
    private function createRoles(): Role
    {
        $roles = [
            [Role::ADMIN_SLUG, 'Company Admin', Permission::values()],
            ['hr-manager', 'HR Manager', array_values(array_diff(Permission::values(), [
                Permission::RolesManage->value,
                Permission::SettingsManage->value,
            ]))],
            ['viewer', 'Viewer', Permission::viewOnly()],
        ];

        $admin = null;

        foreach ($roles as [$slug, $name, $permissions]) {
            $role = new Role(['name' => $name, 'slug' => $slug, 'permissions' => $permissions]);
            $role->is_system = true;
            $role->save();

            $admin ??= $role;
        }

        return $admin;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $suffix = 2;

        while (Company::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".$suffix++;
        }

        return $slug;
    }
}
