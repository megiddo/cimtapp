<?php

declare(strict_types=1);

namespace App\Domain\Dose;

final class DoseConfig
{
    public const MG_DECIMALS = DoseLimits::MG_DECIMALS;

    public const VOLUME_DECIMALS = DoseLimits::VOLUME_DECIMALS;

    public const IU_DECIMALS = DoseLimits::IU_DECIMALS;

    public const CONCENTRATION_WARN_LOW = DoseLimits::CONCENTRATION_WARN_LOW;

    public const CONCENTRATION_WARN_HIGH = DoseLimits::CONCENTRATION_WARN_HIGH;

    public const USES_DEFAULT_LIMIT = DoseLimits::USES_DEFAULT_LIMIT;

    public const USES_MAX_LIMIT = DoseLimits::USES_MAX_LIMIT;

    public const FALLBACK_SYRINGE_VOLUME_ML = DoseLimits::FALLBACK_SYRINGE_VOLUME_ML;

    public const FALLBACK_SYRINGE_CAPACITY_IU = DoseLimits::FALLBACK_SYRINGE_CAPACITY_IU;

    public const IU_NOT_POSITIVE = DoseMessages::IU_NOT_POSITIVE;

    public const IU_ONE_DECIMAL = DoseMessages::IU_ONE_DECIMAL;

    public const MUST_BE_POSITIVE = DoseMessages::MUST_BE_POSITIVE;

    public const MUST_BE_NON_NEGATIVE = DoseMessages::MUST_BE_NON_NEGATIVE;

    public const MUST_BE_NUMBER = DoseMessages::MUST_BE_NUMBER;

    public const MUST_BE_TEXT = DoseMessages::MUST_BE_TEXT;

    public const MUST_BE_BOOLEAN = DoseMessages::MUST_BE_BOOLEAN;

    public const MUST_BE_DATETIME = DoseMessages::MUST_BE_DATETIME;

    public const PEPTIDE_UNKNOWN = DoseMessages::PEPTIDE_UNKNOWN;

    public const PEPTIDE_NAME_TAKEN = DoseMessages::PEPTIDE_NAME_TAKEN;

    public const PEPTIDE_NAME_TOO_LONG = DoseMessages::PEPTIDE_NAME_TOO_LONG;

    public const VIAL_NAME_MAX = DoseLimits::VIAL_NAME_MAX;

    public const VIAL_NAME_TOO_LONG = DoseMessages::VIAL_NAME_TOO_LONG;

    public const NO_COMPOUND = DoseMessages::NO_COMPOUND;

    public const COMPOUND_UNKNOWN = DoseMessages::COMPOUND_UNKNOWN;

    public const SYRINGE_UNKNOWN = DoseMessages::SYRINGE_UNKNOWN;

    public const SYRINGE_STOCK_EMPTY = DoseMessages::SYRINGE_STOCK_EMPTY;

    public const BAC_UNKNOWN = DoseMessages::BAC_UNKNOWN;

    public const NO_BAC_BOTTLE = DoseMessages::NO_BAC_BOTTLE;

    public const BAC_IN_USE = DoseMessages::BAC_IN_USE;

    public const MUST_BE_WHOLE = DoseMessages::MUST_BE_WHOLE;

    public const USE_UNKNOWN = DoseMessages::USE_UNKNOWN;

    public const DEFAULT_REQUIRED = DoseMessages::DEFAULT_REQUIRED;

    public const SYRINGE_LAST = DoseMessages::SYRINGE_LAST;

    public const COMPOUND_HAS_USES = DoseMessages::COMPOUND_HAS_USES;

    public const COMPOUND_OVERDRAW = DoseMessages::COMPOUND_OVERDRAW;

    public const COMPOUND_ARCHIVED = DoseMessages::COMPOUND_ARCHIVED;

    public const ALREADY_ARCHIVED = DoseMessages::ALREADY_ARCHIVED;

    public const ARCHIVE_NOT_EMPTY = DoseMessages::ARCHIVE_NOT_EMPTY;

    public const REMAINING_EXCEEDS_MIX = DoseMessages::REMAINING_EXCEEDS_MIX;

    public const LIMIT_INVALID = DoseMessages::LIMIT_INVALID;

    public const BEFORE_INVALID = DoseMessages::BEFORE_INVALID;

    public const PROFILE_DEFAULT_NAME = DoseMessages::PROFILE_DEFAULT_NAME;

    public const PROFILE_MAX = DoseLimits::PROFILE_MAX;

    public const PROFILE_NAME_MAX = DoseLimits::PROFILE_NAME_MAX;

    public const PROFILE_UNKNOWN = DoseMessages::PROFILE_UNKNOWN;

    public const PROFILE_NAME_TOO_LONG = DoseMessages::PROFILE_NAME_TOO_LONG;

    public const PROFILE_LIMIT = DoseMessages::PROFILE_LIMIT;

    public const PROFILE_DEFAULT_REQUIRED = DoseMessages::PROFILE_DEFAULT_REQUIRED;

    public const PROFILE_HAS_USES = DoseMessages::PROFILE_HAS_USES;

    public const PROFILE_VIAL_MISMATCH = DoseMessages::PROFILE_VIAL_MISMATCH;

    public const MUST_BE_ID_LIST = DoseMessages::MUST_BE_ID_LIST;

    public const PROFILE_REQUIRED = DoseMessages::PROFILE_REQUIRED;

    public static function overdraw(string $requestedIu, string $remainingIu): string
    {
        return DoseFormatter::overdraw($requestedIu, $remainingIu);
    }

    public static function bacOverdraw(string $requestedMl, string $remainingMl): string
    {
        return DoseFormatter::bacOverdraw($requestedMl, $remainingMl);
    }

    public static function syringeOverdraw(int $requested, int $remaining): string
    {
        return DoseFormatter::syringeOverdraw($requested, $remaining);
    }

    public static function formatIu(float $iu): string
    {
        return DoseFormatter::formatIu($iu);
    }

    public static function syringeLabel(float $volumeMl, float $capacityIu): string
    {
        return DoseFormatter::syringeLabel($volumeMl, $capacityIu);
    }

    public static function trimNumber(float $value): string
    {
        return DoseFormatter::trimNumber($value);
    }
}
