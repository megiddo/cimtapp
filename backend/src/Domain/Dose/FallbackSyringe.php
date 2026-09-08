<?php

declare(strict_types=1);

namespace App\Domain\Dose;

/**
 * Null Object for IU math when the user logs without a syringe type.
 */
final class FallbackSyringe
{
    /**
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        return [
            'id' => null,
            'label' => DoseConfig::syringeLabel(
                DoseConfig::FALLBACK_SYRINGE_VOLUME_ML,
                DoseConfig::FALLBACK_SYRINGE_CAPACITY_IU,
            ),
            'volume_ml' => DoseConfig::FALLBACK_SYRINGE_VOLUME_ML,
            'capacity_iu' => DoseConfig::FALLBACK_SYRINGE_CAPACITY_IU,
            'is_default' => false,
            'quantity' => 0,
            'archived_at' => null,
        ];
    }
}
