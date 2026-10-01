<?php

namespace Tests\Feature\Http;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class UploadEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_employee_photo_is_stored_when_the_employee_is_added(): void
    {
        Storage::fake('public');
        $company = $this->useCompany($this->createCompany());

        $response = $this->actingAs($this->adminOf($company))->post('/employees', [
            ...$this->employeePayload(),
            'photo' => UploadedFile::fake()->image('rahul.jpg', 300, 300),
        ]);

        $response->assertSessionHasNoErrors();
        $employee = Employee::query()->sole();
        $this->assertStringStartsWith("employees/{$company->id}/photos/", $employee->photo_path);
        Storage::disk('public')->assertExists($employee->photo_path);
    }

    public function test_replacing_the_employee_photo_removes_the_old_file(): void
    {
        Storage::fake('public');
        $company = $this->useCompany($this->createCompany());
        $this->actingAs($this->adminOf($company));
        $this->post('/employees', [...$this->employeePayload(), 'photo' => UploadedFile::fake()->image('old.jpg')]);
        $employee = Employee::query()->sole();
        $oldPath = $employee->photo_path;

        $response = $this->put("/employees/{$employee->id}", [
            ...$this->employeePayload(),
            'photo' => UploadedFile::fake()->image('new.png'),
        ]);

        $response->assertSessionHasNoErrors();
        $newPath = $employee->refresh()->photo_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_employee_photo_must_be_an_image(): void
    {
        Storage::fake('public');
        $company = $this->useCompany($this->createCompany());

        $response = $this->actingAs($this->adminOf($company))->post('/employees', [
            ...$this->employeePayload(),
            'photo' => UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertSame(0, Employee::query()->count());
    }

    public function test_company_admin_uploads_the_company_logo(): void
    {
        Storage::fake('public');
        $company = $this->useCompany($this->createCompany(['name' => 'Acme']));

        $response = $this->actingAs($this->adminOf($company))->put('/settings/company', [
            'name' => 'Acme', 'currency' => 'INR', 'timezone' => 'Asia/Kolkata', 'date_format' => 'd M Y',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ]);

        $response->assertSessionHasNoErrors();
        $logoPath = $company->refresh()->logo_path;
        $this->assertStringStartsWith("companies/{$company->id}/", $logoPath);
        Storage::disk('public')->assertExists($logoPath);
    }

    public function test_super_admin_creates_a_company_with_a_logo(): void
    {
        Storage::fake('public');

        $response = $this->actingAs(User::factory()->superAdmin()->create())->post('/admin/companies', [
            'name' => 'Acme Technologies', 'currency' => 'INR', 'timezone' => 'Asia/Kolkata', 'date_format' => 'd M Y',
            'admin_name' => 'Asha Admin', 'admin_email' => 'admin@acme.test', 'admin_password' => 'secret-password',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertSessionHasNoErrors();
        $company = Company::query()->sole();
        Storage::disk('public')->assertExists($company->logo_path);
    }

    public function test_company_logo_must_be_an_image(): void
    {
        Storage::fake('public');
        $company = $this->useCompany($this->createCompany(['name' => 'Acme']));

        $response = $this->actingAs($this->adminOf($company))->put('/settings/company', [
            'name' => 'Acme', 'currency' => 'INR', 'timezone' => 'Asia/Kolkata', 'date_format' => 'd M Y',
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ]);

        $response->assertSessionHasErrors('logo');
        $this->assertNull($company->refresh()->logo_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(): array
    {
        return [
            'employee_code' => 'EMP-0100',
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'joining_date' => '2026-09-01',
            'employment_type' => 'full_time',
            'status' => 'active',
        ];
    }
}
