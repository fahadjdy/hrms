<?php

namespace Tests\Feature\Http;

use App\Enums\AttendanceMode;
use App\Enums\SalaryCalculationMethod;
use App\Enums\ShortHoursMode;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\WeeklyHoliday;
use App\Models\WorkShift;
use App\Services\WorkingCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class SettingsEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_attendance_settings_are_saved_and_audited(): void
    {
        $company = $this->useCompany($this->createCompany());
        $default = WorkShift::query()->sole();
        $female = WorkShift::factory()->timing('09:30', '17:30', 420)->create(['name' => 'Female Shift']);

        $response = $this->actingAs($this->adminOf($company))->put('/settings/attendance', [
            'attendance_mode' => 'automatic',
            'default_work_shift_id' => $default->id,
            'male_work_shift_id' => null,
            'female_work_shift_id' => $female->id,
            'other_work_shift_id' => null,
            'grace_minutes' => 15,
            'lates_per_half_day' => 3,
            'short_hours_tolerance_minutes' => 5,
        ]);

        $response->assertSessionHasNoErrors();
        $settings = $this->tenant()->settings();
        $this->assertSame(AttendanceMode::Automatic, $settings->attendance_mode);
        $this->assertSame($female->id, $settings->female_work_shift_id);
        $this->assertSame(15, $settings->grace_minutes);
        $this->assertSame(3, $settings->lates_per_half_day);
        $this->assertSame(5, $settings->short_hours_tolerance_minutes);

        $audit = AuditLog::query()->where('action', 'settings.attendance_updated')->sole();
        $this->assertSame('manual', $audit->old_values['attendance_mode']);
        $this->assertSame('automatic', $audit->new_values['attendance_mode']);
    }

    public function test_attendance_settings_require_a_default_shift_of_this_company(): void
    {
        $company = $this->createCompany();
        $otherShift = $this->shiftOfAnotherCompany();
        $this->useCompany($company);

        $response = $this->actingAs($this->adminOf($company))->put('/settings/attendance', [
            'attendance_mode' => 'automatic',
            'default_work_shift_id' => $otherShift->id,
            'grace_minutes' => 10,
            'lates_per_half_day' => 0,
            'short_hours_tolerance_minutes' => 0,
        ]);

        $response->assertSessionHasErrors('default_work_shift_id');
        $this->assertSame(AttendanceMode::Manual, $this->tenant()->settings()->attendance_mode);
    }

    public function test_payroll_settings_are_saved_and_audited(): void
    {
        $company = $this->useCompany($this->createCompany());

        $response = $this->actingAs($this->adminOf($company))->put('/settings/payroll', [
            'salary_calculation_method' => 'calendar_days',
            'payroll_period_start_day' => 1,
            'salary_payment_day' => 7,
            'overtime_rate_type' => 'fixed',
            'overtime_multiplier' => 2,
            'overtime_fixed_rate' => 250,
            'overtime_from_attendance' => true,
            'short_hours_mode' => 'deduct',
            'short_hours_rate_type' => 'fixed',
            'short_hours_fixed_rate' => 200,
            'borrow_auto_deduct' => false,
            'borrow_max_deduction_percent' => 40,
        ]);

        $response->assertSessionHasNoErrors();
        $settings = $this->tenant()->settings();
        $this->assertSame(SalaryCalculationMethod::CalendarDays, $settings->salary_calculation_method);
        $this->assertSame(7, $settings->salary_payment_day);
        $this->assertSame('fixed', $settings->overtime_rate_type);
        $this->assertSame(250.0, $settings->overtime_fixed_rate);
        $this->assertTrue($settings->overtime_from_attendance);
        $this->assertSame(ShortHoursMode::Deduct, $settings->short_hours_mode);
        $this->assertSame(200.0, $settings->short_hours_fixed_rate);
        $this->assertFalse($settings->borrow_auto_deduct);
        $this->assertSame(40.0, $settings->borrow_max_deduction_percent);

        $audit = AuditLog::query()->where('action', 'settings.payroll_updated')->sole();
        $this->assertSame('record_only', $audit->old_values['short_hours_mode']);
        $this->assertSame('deduct', $audit->new_values['short_hours_mode']);
    }

    public function test_fixed_short_hours_rate_is_required_when_the_rate_type_is_fixed(): void
    {
        $company = $this->useCompany($this->createCompany());

        $response = $this->actingAs($this->adminOf($company))->put('/settings/payroll', [
            'salary_calculation_method' => 'working_days',
            'payroll_period_start_day' => 1,
            'salary_payment_day' => 1,
            'overtime_rate_type' => 'multiplier',
            'overtime_multiplier' => 1.5,
            'overtime_from_attendance' => false,
            'short_hours_mode' => 'deduct',
            'short_hours_rate_type' => 'fixed',
            'borrow_auto_deduct' => true,
        ]);

        $response->assertSessionHasErrors(['short_hours_fixed_rate' => 'Enter the fixed short-hours rate per hour.']);
        $this->assertSame(ShortHoursMode::RecordOnly, $this->tenant()->settings()->short_hours_mode);
    }

    public function test_changing_the_weekly_holidays_changes_the_working_days(): void
    {
        $company = $this->useCompany($this->createCompany());
        $calendar = app(WorkingCalendarService::class);
        $september = [CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')];
        $before = $calendar->workingDaysBetween(...$september);

        $response = $this->actingAs($this->adminOf($company))->put('/weekly-holidays', ['days' => [6, 0]]);

        $response->assertSessionHasNoErrors();
        $this->assertSame([0, 6], WeeklyHoliday::query()->orderBy('day_of_week')->pluck('day_of_week')->all());
        // September 2026 has four Saturdays and four Sundays.
        $this->assertSame(26, $before);
        $this->assertSame(22, $calendar->workingDaysBetween(...$september));
        $audit = AuditLog::query()->where('action', 'weekly_holidays.updated')->sole();
        $this->assertSame([0], $audit->old_values['days']);
        $this->assertSame([0, 6], $audit->new_values['days']);
    }

    public function test_all_seven_days_cannot_be_weekly_holidays(): void
    {
        $company = $this->useCompany($this->createCompany());

        $response = $this->actingAs($this->adminOf($company))->put('/weekly-holidays', ['days' => [0, 1, 2, 3, 4, 5, 6]]);

        $response->assertSessionHasErrors(['days' => 'At least one day of the week must be a working day.']);
        $this->assertSame([0], WeeklyHoliday::query()->pluck('day_of_week')->all());
    }

    public function test_work_shift_derives_its_break_from_the_span_and_required_hours(): void
    {
        $company = $this->useCompany($this->createCompany());
        $this->actingAs($this->adminOf($company));

        $this->post('/work-shifts', [
            'name' => 'Morning Shift', 'start_time' => '09:00', 'end_time' => '18:00', 'required_minutes' => 480,
        ])->assertSessionHasNoErrors();
        $this->post('/work-shifts', [
            'name' => 'Too Long', 'start_time' => '09:00', 'end_time' => '13:00', 'required_minutes' => 300,
        ])->assertSessionHasErrors('required_minutes');

        $shift = WorkShift::query()->where('name', 'Morning Shift')->sole();
        $this->assertSame(60, $shift->break_minutes);
        $this->assertSame(0, WorkShift::query()->where('name', 'Too Long')->count());
        $this->assertTrue(AuditLog::query()->where('action', 'work_shift.created')->exists());
    }

    public function test_renaming_a_work_shift_audits_only_what_changed(): void
    {
        $company = $this->useCompany($this->createCompany());
        $shift = WorkShift::query()->sole();

        $response = $this->actingAs($this->adminOf($company))->put("/work-shifts/{$shift->id}", [
            'name' => 'Day Shift', 'start_time' => '09:00', 'end_time' => '18:00', 'required_minutes' => 480, 'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $audit = AuditLog::query()->where('action', 'work_shift.updated')->sole();
        $this->assertSame(['name' => 'General Shift'], $audit->old_values);
        $this->assertSame(['name' => 'Day Shift'], $audit->new_values);
    }

    public function test_default_work_shift_cannot_be_deleted(): void
    {
        $company = $this->useCompany($this->createCompany());
        $default = WorkShift::query()->sole();

        $response = $this->actingAs($this->adminOf($company))->delete("/work-shifts/{$default->id}");

        $response->assertSessionHasErrors('work_shift');
        $this->assertModelExists($default);
    }

    public function test_creating_a_role_stores_its_permissions(): void
    {
        $company = $this->useCompany($this->createCompany());

        $response = $this->actingAs($this->adminOf($company))->post('/settings/roles', [
            'name' => 'Payroll Clerk',
            'permissions' => ['payroll.view', 'payroll.manage'],
        ]);

        $response->assertSessionHasNoErrors();
        $role = Role::query()->where('slug', 'payroll-clerk')->sole();
        $this->assertSame(['payroll.view', 'payroll.manage'], $role->permissions);
        $this->assertFalse($role->is_system);
        $this->assertTrue(AuditLog::query()->where('action', 'role.created')->exists());
    }

    public function test_a_role_cannot_be_given_an_unknown_permission(): void
    {
        $company = $this->useCompany($this->createCompany());

        $response = $this->actingAs($this->adminOf($company))->post('/settings/roles', [
            'name' => 'Everything', 'permissions' => ['platform.own'],
        ]);

        $response->assertSessionHasErrors('permissions.0');
        $this->assertSame(3, Role::query()->count());
    }

    public function test_updating_a_role_changes_what_its_users_may_do(): void
    {
        $company = $this->useCompany($this->createCompany());
        $viewerRole = Role::query()->where('slug', 'viewer')->sole();
        $viewer = $this->userWithRole($company, 'viewer');

        $this->actingAs($this->adminOf($company))->put("/settings/roles/{$viewerRole->id}", [
            'name' => 'Viewer', 'permissions' => ['employees.view', 'employees.manage'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($viewer->fresh())->get('/employees/create')->assertOk();
        $this->get('/payroll')->assertForbidden();
    }

    public function test_the_company_admin_role_cannot_be_changed(): void
    {
        $company = $this->useCompany($this->createCompany());
        $adminRole = Role::query()->where('slug', Role::ADMIN_SLUG)->sole();

        $response = $this->actingAs($this->adminOf($company))->put("/settings/roles/{$adminRole->id}", [
            'name' => 'Company Admin', 'permissions' => [],
        ]);

        $response->assertSessionHasErrors([
            'role' => 'The Company Admin role always has every permission and cannot be changed.',
        ]);
        $this->assertCount(16, $adminRole->refresh()->permissions);
    }

    public function test_role_deletion_rules(): void
    {
        $company = $this->useCompany($this->createCompany());
        $builtIn = Role::query()->where('slug', 'hr-manager')->sole();
        $inUse = new Role(['name' => 'In Use', 'slug' => 'in-use', 'permissions' => ['employees.view']]);
        $inUse->save();
        User::factory()->forCompany($company, $inUse)->create();
        $unused = new Role(['name' => 'Unused', 'slug' => 'unused', 'permissions' => []]);
        $unused->save();
        $this->actingAs($this->adminOf($company));

        $this->delete("/settings/roles/{$builtIn->id}")
            ->assertSessionHasErrors(['role' => 'Built-in roles cannot be deleted.']);
        $this->delete("/settings/roles/{$inUse->id}")
            ->assertSessionHasErrors(['role' => 'This role is still assigned to users. Give them another role first.']);
        $this->delete("/settings/roles/{$unused->id}")->assertSessionHasNoErrors();

        $this->assertModelExists($builtIn);
        $this->assertModelExists($inUse);
        $this->assertModelMissing($unused);
    }

    public function test_adding_a_company_user_creates_a_verified_account_in_this_company(): void
    {
        $company = $this->useCompany($this->createCompany());
        $role = Role::query()->where('slug', 'hr-manager')->sole();

        $response = $this->actingAs($this->adminOf($company))->post('/settings/users', [
            'name' => 'Hema HR',
            'email' => 'hema@acme.test',
            'password' => 'secret-password',
            'role_id' => $role->id,
            'company_id' => 999,
            'is_super_admin' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $user = User::query()->where('email', 'hema@acme.test')->sole();
        $this->assertSame($company->id, $user->company_id);
        $this->assertSame($role->id, $user->role_id);
        $this->assertFalse($user->is_super_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertTrue(AuditLog::query()->where('action', 'user.created')->exists());
    }

    public function test_company_user_email_must_be_unique(): void
    {
        $company = $this->useCompany($this->createCompany());
        $admin = $this->adminOf($company);
        $role = Role::query()->where('slug', 'viewer')->sole();

        $response = $this->actingAs($admin)->post('/settings/users', [
            'name' => 'Copy', 'email' => $admin->email, 'password' => 'secret-password', 'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors(['email' => 'The email has already been taken.']);
        $this->assertSame(1, User::query()->where('company_id', $company->id)->count());
    }

    public function test_user_cannot_change_their_own_role_or_deactivate_themselves(): void
    {
        $company = $this->useCompany($this->createCompany());
        $admin = $this->adminOf($company);
        $viewerRole = Role::query()->where('slug', 'viewer')->sole();

        $response = $this->actingAs($admin)->put("/settings/users/{$admin->id}", [
            'name' => $admin->name, 'role_id' => $viewerRole->id, 'is_active' => true,
        ]);

        $response->assertSessionHasErrors([
            'role_id' => 'You cannot change your own role or deactivate your own account.',
        ]);
        $this->assertSame(Role::ADMIN_SLUG, $admin->refresh()->role->slug);
    }

    public function test_the_only_active_company_admin_cannot_be_demoted_or_deactivated(): void
    {
        $company = $this->useCompany($this->createCompany());
        $admin = $this->adminOf($company);
        $userManagers = new Role(['name' => 'User Manager', 'slug' => 'user-manager', 'permissions' => ['roles.manage']]);
        $userManagers->save();
        $manager = User::factory()->forCompany($company, $userManagers)->create();
        $this->actingAs($manager);

        $message = 'This is the only active Company Admin. Make another user a Company Admin first.';
        $this->put("/settings/users/{$admin->id}", ['name' => $admin->name, 'role_id' => $userManagers->id, 'is_active' => true])
            ->assertSessionHasErrors(['role_id' => $message]);
        $this->put("/settings/users/{$admin->id}", ['name' => $admin->name, 'role_id' => $admin->role_id, 'is_active' => false])
            ->assertSessionHasErrors(['role_id' => $message]);

        $admin->refresh();
        $this->assertTrue($admin->is_active);
        $this->assertSame(Role::ADMIN_SLUG, $admin->role->slug);
    }

    public function test_a_company_admin_can_be_demoted_when_another_active_admin_exists(): void
    {
        $company = $this->useCompany($this->createCompany());
        $admin = $this->adminOf($company);
        $second = User::factory()->forCompany($company, $admin->role)->create();
        $viewerRole = Role::query()->where('slug', 'viewer')->sole();

        $response = $this->actingAs($admin)->put("/settings/users/{$second->id}", [
            'name' => 'Renamed', 'role_id' => $viewerRole->id, 'is_active' => false, 'password' => 'new-secret-password',
        ]);

        $response->assertSessionHasNoErrors();
        $second->refresh();
        $this->assertSame('Renamed', $second->name);
        $this->assertSame($viewerRole->id, $second->role_id);
        $this->assertFalse($second->is_active);
        $this->assertTrue(Hash::check('new-secret-password', $second->password));
    }

    public function test_hr_manager_cannot_open_company_settings_or_roles(): void
    {
        $company = $this->useCompany($this->createCompany());
        $this->actingAs($this->userWithRole($company, 'hr-manager'));

        $this->get('/settings/company')->assertForbidden();
        $this->get('/settings/payroll')->assertForbidden();
        $this->get('/settings/roles')->assertForbidden();
        $this->put('/settings/attendance', ['attendance_mode' => 'automatic'])->assertForbidden();
        $this->get('/employees')->assertOk();

        $this->assertSame(AttendanceMode::Manual, $this->tenant()->settings()->attendance_mode);
    }

    public function test_company_profile_update_changes_the_company_and_is_audited(): void
    {
        $company = $this->useCompany($this->createCompany(['name' => 'Old Name']));

        $response = $this->actingAs($this->adminOf($company))->put('/settings/company', [
            'name' => 'New Name', 'currency' => 'USD', 'timezone' => 'Asia/Dubai', 'date_format' => 'd/m/Y', 'tax_id' => '29ABCDE1234F1Z5',
        ]);

        $response->assertSessionHasNoErrors();
        $company->refresh();
        $this->assertSame('New Name', $company->name);
        $this->assertSame('USD', $company->currency);
        $this->assertSame('29ABCDE1234F1Z5', $company->tax_id);
        $audit = AuditLog::query()->where('action', 'company.updated')->sole();
        $this->assertSame('Old Name', $audit->old_values['name']);
        $this->assertSame('New Name', $audit->new_values['name']);
    }

    private function shiftOfAnotherCompany(): WorkShift
    {
        $other = $this->createCompany();
        $this->useCompany($other);

        return WorkShift::query()->sole();
    }
}
