<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per salary change. Revisions are append-only: a salary is never
 * edited in place, a new revision is added instead.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property CarbonImmutable $effective_date
 * @property float $previous_gross
 * @property float $new_gross
 * @property list<array{code: string, name: string, type: string, amount: float}>|null $previous_components
 * @property string|null $reason
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property-read Collection<int, EmployeeSalaryComponent> $components
 * @property-read Employee $employee
 */
#[Fillable(['employee_id', 'effective_date', 'previous_gross', 'new_gross', 'previous_components', 'reason', 'notes', 'created_by'])]
class EmployeeSalaryRevision extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'previous_gross' => 'float',
            'new_gross' => 'float',
            'previous_components' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<EmployeeSalaryComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
