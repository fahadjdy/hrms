<?php

namespace App\Models;

use App\Enums\PayrollBucket;
use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual change to one bucket of a payroll item. The amount is a signed
 * delta added to the system-calculated amount of that bucket.
 *
 * @property int $id
 * @property int $company_id
 * @property int $payroll_item_id
 * @property PayrollBucket $bucket
 * @property float $amount
 * @property string $reason
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $user
 */
#[Fillable(['payroll_item_id', 'bucket', 'amount', 'reason', 'user_id'])]
class PayrollAdjustment extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bucket' => PayrollBucket::class,
            'amount' => 'float',
        ];
    }

    /**
     * @return BelongsTo<PayrollItem, $this>
     */
    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
