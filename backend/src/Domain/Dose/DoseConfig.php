<?php

declare(strict_types=1);

namespace App\Domain\Dose;

final class DoseConfig
{
    public const MG_DECIMALS = 4;

    public const VOLUME_DECIMALS = 6;

    public const IU_DECIMALS = 1;

    public const CONCENTRATION_WARN_LOW = 0.5;

    public const CONCENTRATION_WARN_HIGH = 20.0;

    public const USES_DEFAULT_LIMIT = 50;

    public const USES_MAX_LIMIT = 100;

    public const IU_NOT_POSITIVE = 'IU must be greater than 0.';

    public const IU_ONE_DECIMAL = 'IU allows one decimal place.';

    public const MUST_BE_POSITIVE = 'Must be greater than 0.';

    public const MUST_BE_NUMBER = 'Enter a number greater than 0.';

    public const MUST_BE_TEXT = 'Must be text.';

    public const MUST_BE_BOOLEAN = 'Must be true or false.';

    public const MUST_BE_DATETIME = 'Enter a valid date and time.';

    public const PEPTIDE_UNKNOWN = 'Choose a peptide from the catalog.';

    public const NO_COMPOUND = 'Mix a vial before logging a use.';

    public const COMPOUND_UNKNOWN = 'Compound not found.';

    public const SYRINGE_UNKNOWN = 'Syringe not found.';

    public const USE_UNKNOWN = 'Use not found.';

    public const DEFAULT_REQUIRED = 'Keep one default syringe.';

    public const PEPTIDE_LOCKED = 'Peptide type cannot change after the first use.';

    public const MG_LOCKED = 'Peptide milligrams cannot change after the first use.';

    public const BAC_LOCKED = 'BAC water cannot change after the first use.';

    public const LIMIT_INVALID = 'Limit must be between 1 and 100.';

    public const BEFORE_INVALID = 'before must be an ISO timestamp.';

    public static function overdraw(string $requestedIu, string $remainingIu): string
    {
        return $requestedIu . ' IU exceeds ' . $remainingIu . ' IU remaining in this vial.';
    }

    public static function formatIu(float $iu): string
    {
        $rounded = round($iu, self::IU_DECIMALS);
        if (abs($rounded - round($rounded)) < 1e-9) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, self::IU_DECIMALS, '.', '');
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
