<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Number;

/**
 * Display formatting for server-rendered output such as salary slip PDFs.
 */
class Format
{
    public static function money(float|int|string|null $amount, string $currency = 'INR'): string
    {
        $locale = $currency === 'INR' ? 'en_IN' : 'en';

        return (string) Number::currency((float) $amount, $currency, $locale);
    }

    /**
     * Minutes as hours and minutes, e.g. 429 => "7h 09m".
     */
    public static function minutes(int|float|null $minutes): string
    {
        $minutes = (int) $minutes;

        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }

    public static function date(?CarbonInterface $date, string $format = 'd M Y'): string
    {
        return $date?->format($format) ?? '';
    }
}
