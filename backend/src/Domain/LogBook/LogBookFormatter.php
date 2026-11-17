<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

use App\Domain\Dose\DoseLimits;

/**
 * Presentation formatter aligned with {@see frontend/src/lib/dose.ts} formatMg/formatConcentration.
 */
final class LogBookFormatter
{
    public static function formatMg(float $mg): string
    {
        $three = self::roundTo($mg, 3);
        $two = self::roundTo($mg, 2);
        if (abs($three - $two) < 1e-9) {
            return number_format($two, 2, '.', '');
        }

        return number_format($three, 3, '.', '');
    }

    public static function formatConcentration(float $mgPerMl): string
    {
        return self::formatMg($mgPerMl) . ' mg/mL';
    }

    private static function roundTo(float $value, int $decimals): float
    {
        $factor = 10 ** $decimals;

        return round($value * $factor) / $factor;
    }

    public static function roundMg(float $mg): float
    {
        return round($mg, DoseLimits::MG_DECIMALS);
    }

    public static function roundVolume(float $volumeMl): float
    {
        return round($volumeMl, DoseLimits::VOLUME_DECIMALS);
    }
}