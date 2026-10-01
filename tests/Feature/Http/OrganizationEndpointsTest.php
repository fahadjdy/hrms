<?php

namespace Tests\Feature\Http;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeDocument;
use App\Models\Holiday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class OrganizationEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_department_name_must_be_unique_within_the_company(): void
    {
        $company = $this->prepareCompany();
        $this->actingAs($this->adminOf($company));

        $this->post('/departments', ['name' => 'Finance', 'description' => 'Accounts and payroll'])->assertSessionHasNoErrors();
        $this->post('/departments', ['name' => 'Finance'])->assertSessionHasErrors(['name' => 'The name has already been taken.']);

        $this->assertSame(1, Department::query()->count());
    }

    public function test_department_is_renamed_and_can_be_made_inactive(): void
    {
        $company = $this->prepareCompany();
        $department = Department::factory()->create(['name' => 'Sales']);

        $response = $this->actingAs($this->adminOf($company))
            ->put("/departments/{$department->id}", ['name' => 'Sales', 'description' => 'Field team', 'is_active' => false]);

        $response->assertSessionHasNoErrors();
        $department->refresh();
        $this->assertSame('Field team', $department->description);
        $this->assertFalse($department->is_active);
    }

    public function test_department_with_employees_cannot_be_deleted(): void
    {
        $company = $this->prepareCompany();
        $used = Department::factory()->create(['name' => 'Sales']);
        $empty = Department::factory()->create(['name' => 'Legal']);
        $this->createEmployee(['department_id' => $used->id]);
        $this->actingAs($this->adminOf($company));

        $this->delete("/departments/{$used->id}")->assertSessionHasErrors('department');
        $this->delete("/departments/{$empty->id}")->assertSessionHasNoErrors();

        $this->assertModelExists($used);
        $this->assertModelMissing($empty);
    }

    public function test_department_list_counts_only_current_employees(): void
    {
        $company = $this->prepareCompany();
        $department = Department::factory()->create(['name' => 'Sales']);
        $this->createEmployee(['department_id' => $department->id]);
        $this->createEmployee(['department_id' => $department->id, 'status' => 'past', 'last_working_date' => '2026-08-31']);

        $response = $this->actingAs($this->adminOf($company))->get('/departments');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('departments', 1)
            ->where('departments.0.name', 'Sales')
            ->where('departments.0.employees_count', 1));
    }

    public function test_designation_with_employees_cannot_be_deleted(): void
    {
        $company = $this->prepareCompany();
        $used = Designation::factory()->create(['name' => 'Engineer']);
        $empty = Designation::factory()->create(['name' => 'Intern']);
        $this->createEmployee(['designation_id' => $used->id]);
        $this->actingAs($this->adminOf($company));

        $this->post('/designations', ['name' => 'Engineer'])->assertSessionHasErrors(['name' => 'The name has already been taken.']);
        $this->delete("/designations/{$used->id}")->assertSessionHasErrors('designation');
        $this->delete("/designations/{$empty->id}")->assertSessionHasNoErrors();

        $this->assertModelExists($used);
        $this->assertModelMissing($empty);
    }

    public function test_holiday_is_stored_and_only_one_holiday_is_allowed_per_date(): void
    {
        $company = $this->prepareCompany();
        $this->actingAs($this->adminOf($company));

        $this->post('/holidays', ['name' => 'Diwali', 'date' => '2026-11-08', 'type' => 'public', 'description' => 'Festival of lights'])
            ->assertSessionHasNoErrors();
        $this->post('/holidays', ['name' => 'Another', 'date' => '2026-11-08', 'type' => 'company'])
            ->assertSessionHasErrors(['date' => 'A holiday already exists on this date.']);

        $holiday = Holiday::query()->sole();
        $this->assertSame('Diwali', $holiday->name);
        $this->assertSame('2026-11-08', $holiday->date->toDateString());
        $this->assertTrue(AuditLog::query()->where('action', 'holiday.created')->exists());
    }

    public function test_adding_a_holiday_takes_it_out_of_the_working_days_immediately(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->actingAs($this->adminOf($company));
        $before = $this->get("/employees/{$employee->id}/attendance?month=2026-09");
        $before->assertInertia(fn (Assert $page) => $page->where('summary.working_days', 26));

        $this->post('/holidays', ['name' => 'Founders Day', 'date' => '2026-09-16', 'type' => 'company']);

        $this->get("/employees/{$employee->id}/attendance?month=2026-09")->assertInertia(fn (Assert $page) => $page
            ->where('summary.working_days', 25)
            ->where('summary.holidays', 1)
            ->where('days.15.status', 'holiday')
            ->where('days.15.holiday_name', 'Founders Day'));
    }

    public function test_holiday_list_is_limited_to_the_chosen_year(): void
    {
        $company = $this->prepareCompany();
        Holiday::factory()->create(['name' => 'Republic Day', 'date' => '2026-01-26']);
        Holiday::factory()->create(['name' => 'New Year', 'date' => '2027-01-01']);

        $response = $this->actingAs($this->adminOf($company))->get('/holidays?year=2026');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('year', 2026)
            ->has('holidays', 1)
            ->where('holidays.0.name', 'Republic Day')
            ->where('holidays.0.weekday', 'Monday'));
    }

    public function test_document_upload_is_stored_privately_and_can_be_downloaded(): void
    {
        Storage::fake('local');
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $admin = $this->adminOf($company);
        $this->actingAs($admin);

        $response = $this->post("/employees/{$employee->id}/documents", [
            'title' => 'Employment contract',
            'type' => 'Contract',
            'file' => UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf'),
        ]);

        $response->assertSessionHasNoErrors();
        $document = EmployeeDocument::query()->sole();
        $this->assertSame('contract.pdf', $document->original_name);
        $this->assertSame($admin->id, $document->uploaded_by);
        $this->assertStringStartsWith("employee-documents/{$company->id}/{$employee->id}/", $document->file_path);
        Storage::disk('local')->assertExists($document->file_path);

        $this->get("/documents/{$document->id}")->assertDownload('contract.pdf');
        $this->assertTrue(AuditLog::query()->where('action', 'document.uploaded')->where('employee_id', $employee->id)->exists());
    }

    public function test_document_upload_rejects_executable_files(): void
    {
        Storage::fake('local');
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post("/employees/{$employee->id}/documents", [
            'title' => 'Script',
            'file' => UploadedFile::fake()->create('run.php', 10, 'application/x-php'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, EmployeeDocument::query()->count());
    }

    public function test_deleting_a_document_removes_its_file(): void
    {
        Storage::fake('local');
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->actingAs($this->adminOf($company));
        $this->post("/employees/{$employee->id}/documents", [
            'title' => 'ID proof', 'file' => UploadedFile::fake()->image('id.jpg'),
        ]);
        $document = EmployeeDocument::query()->sole();

        $response = $this->delete("/documents/{$document->id}");

        $response->assertSessionHasNoErrors();
        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_audit_log_lists_actions_newest_first_and_filters_by_area(): void
    {
        $company = $this->prepareCompany();
        $this->actingAs($this->adminOf($company));
        $this->post('/departments', ['name' => 'Finance']);
        $this->post('/holidays', ['name' => 'Diwali', 'date' => '2026-11-08', 'type' => 'public']);
        $this->put('/weekly-holidays', ['days' => [0, 6]]);

        $this->get('/audit-logs')->assertInertia(fn (Assert $page) => $page
            ->where('logs.data.0.action', 'weekly_holidays.updated')
            ->where('logs.data.0.user', 'Company Admin')
            ->where('logs.data.0.old_values.days', [0])
            ->where('logs.data.0.new_values.days', [0, 6])
            ->where('logs.data.1.action', 'holiday.created')
            ->has('areas'));
        $this->get('/audit-logs?action=holiday')->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'holiday.created'));
    }

    public function test_viewer_role_cannot_read_the_audit_log(): void
    {
        $company = $this->prepareCompany();

        $response = $this->actingAs($this->userWithRole($company, 'viewer'))->get('/audit-logs');

        $response->assertForbidden();
    }

    private function prepareCompany(): Company
    {
        $this->travelTo('2026-10-01 10:00:00');

        return $this->useCompany($this->createCompany());
    }
}
