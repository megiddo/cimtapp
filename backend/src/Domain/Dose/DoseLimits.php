<?php

declare(strict_types=1);

namespace App\Domain\Dose;

final class DoseLimits
{
    public const MG_DECIMALS = 4;

    public const VOLUME_DECIMALS = 6;

    public const IU_DECIMALS = 1;

    public const CONCENTRATION_WARN_LOW = 0.5;

    public const CONCENTRATION_WARN_HIGH = 20.0;

    public const USES_DEFAULT_LIMIT = 50;

    public const USES_MAX_LIMIT = 100;

    public const FALLBACK_SYRINGE_VOLUME_ML = 0.5;

    public const FALLBACK_SYRINGE_CAPACITY_IU = 50.0;

    public const VIAL_NAME_MAX = 80;

    public const PROFILE_MAX = 5;

    public const PROFILE_NAME_MAX = 40;
}
