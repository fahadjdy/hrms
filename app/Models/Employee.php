<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\ExitType;
use App\Enums\Gender;
use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $company_id
 * @property string $employee_code
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $photo_path
 * @property CarbonImmutable|null $date_of_birth
 * @property Gender|null $gender
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $postal_code
 * @property CarbonImmutable $joining_date
 * @property int|null $department_id
 * @property int|null $designation_id
 * @property EmploymentType $employment_type
 * @property int|null $reporting_manager_id
 * @property EmployeeStatus $status
 * @property CarbonImmutable|null $probation_end_date
 * @property string|null $notes
 * @property CarbonImmutable|null $exit_date
 * @property CarbonImmutable|null $last_working_date
 * @property ExitType|null $exit_type
 * @property string|null $exit_reason
 * @property string|null $exit_notes
 * @property-read string $full_name
 * @property-read Department|null $department
 * @property-read Designation|null $designation
 * @property-read Collection<int, EmployeeDesignationChange> $designationChanges
 */
#[Fillable([
    'employee_code', 'first_name', 'last_name', 'date_of_birth', 'gender', 'phone', 'email',
    'address', 'city', 'state', 'country', 'postal_code', 'joining_date', 'department_id',
    'designation_id', 'employment_type', 'reporting_manager_id', 'status', 'probation_end_date', 'notes',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * Mirrors the column defaults so a newly created model has them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'employment_type' => 'full_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'probation_end_date' => 'date',
            'exit_date' => 'date',
            'last_working_date' => 'date',
            'gender' => Gender::class,
            'employment_type' => EmploymentType::class,
            'status' => EmployeeStatus::class,
            'exit_type' => ExitType::class,
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function isPast(): bool
    {
        return $this->status === EmployeeStatus::Past;
    }

    /**
     * The few fields lists and pickers need to identify an employee.
     * Expects `department` and `designation` to be eager loaded.
     *
     * @return array{id: int, name: string, code: string, photo_url: string|null, department: string|null, designation: string|null, status: string}
     */
    public function toBrief(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->full_name,
            'code' => $this->employee_code,
            'photo_url' => $this->photoUrl(),
            'department' => $this->department?->name,
            'designation' => $this->designation?->name,
            'status' => $this->status->value,
        ];
    }

    /**
     * Whether the employee was employed on the given calendar date.
     */
    public function isEmployedOn(CarbonImmutable $date): bool
    {
        if ($date->lt($this->joining_date)) {
            return false;
        }

        return $this->last_working_date === null || $date->lte($this->last_working_date);
    }

    /**
     * Employees who currently work at the company.
     *
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->whereIn('status', EmployeeStatus::currentValues());
    }

    /**
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function past(Builder $query): void
    {
        $query->where('status', EmployeeStatus::Past->value);
    }

    /**
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $inner) use ($term): void {
            $inner->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('employee_code', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporting_manager_id');
    }

    /**
     * @return HasMany<EmployeeDesignationChange, $this>
     */
    public function designationChanges(): HasMany
    {
        return $this->hasMany(EmployeeDesignationChange::class);
    }

    /**
     * @return HasMany<EmployeeShiftAssignment, $this>
     */
    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @return HasMany<EmployeeSalaryRevision, $this>
     */
    public function salaryRevisions(): HasMany
    {
        return $this->hasMany(EmployeeSalaryRevision::class);
    }

    /**
     * @return HasMany<EmployeeBorrow, $this>
     */
    public function borrows(): HasMany
    {
        return $this->hasMany(EmployeeBorrow::class);
    }

    /**
     * @return HasMany<EmployeeLeave, $this>
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    /**
     * @return HasMany<Overtime, $this>
     */
    public function overtimes(): HasMany
    {
        return $this->hasMany(Overtime::class);
    }

    /**
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * @return HasOne<FinalSettlement, $this>
     */
    public function finalSettlement(): HasOne
    {
        return $this->hasOne(FinalSettlement::class);
    }
}
