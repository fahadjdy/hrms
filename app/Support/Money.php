<?php

namespace App\Support;

/**
 * Money amounts are stored as DECIMAL(…, 2) and rounded to 2 places at every
 * line item, so the lines shown to the admin always add up to the totals.
 */
class Money
{
    public static function round(float|int|string|null $amount): float
    {
        return round((float) $amount, 2);
    }

    /**
     * @param  iterable<float|int|string|null>  $amounts
     */
    public static function sum(iterable $amounts): float
    {
        $total = 0.0;

        foreach ($amounts as $amount) {
            $total += (float) $amount;
        }

        return self::round($total);
    }
}
