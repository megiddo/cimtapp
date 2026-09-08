<?php

declare(strict_types=1);

namespace App\Domain\Dose;

final class DoseFormatter
{
    public static function overdraw(string $requestedIu, string $remainingIu): string
    {
        return $requestedIu . ' IU exceeds ' . $remainingIu . ' IU remaining in this vial.';
    }

    public static function bacOverdraw(string $requestedMl, string $remainingMl): string
    {
        return $requestedMl . ' mL exceeds ' . $remainingMl . ' mL remaining in bacteriostatic water.';
    }

    public static function syringeOverdraw(int $requested, int $remaining): string
    {
        return $requested . ' exceeds ' . $remaining . ' syringes remaining.';
    }

    public static function formatIu(float $iu): string
    {
        $rounded = round($iu, DoseLimits::IU_DECIMALS);
        if (abs($rounded - round($rounded)) < 1e-9) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, DoseLimits::IU_DECIMALS, '.', '');
    }

    public static function syringeLabel(float $volumeMl, float $capacityIu): string
    {
        return self::trimNumber($volumeMl) . ' mL / ' . self::trimNumber($capacityIu) . ' IU';
    }

    public static function trimNumber(float $value): string
    {
        $formatted = number_format($value, 4, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
