<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $code
 * @property bool $is_paid
 * @property float $annual_allowance
 * @property bool $is_active
 */
#[Fillable(['name', 'code', 'is_paid', 'annual_allowance', 'is_active'])]
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'annual_allowance' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<EmployeeLeave, $this>
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class);
    }
}
