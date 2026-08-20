<?php

declare(strict_types=1);

namespace App\Domain\Dose;

final readonly class Remainder
{
    public function __construct(
        public float $remainingMg,
        public float $remainingMl,
        public float $remainingIu,
        public float $concentration,
    ) {
    }

    /**
     * @return array{
     *     remaining_mg: float,
     *     remaining_ml: float,
     *     remaining_iu: float,
     *     concentration: float
     * }
     */
    public function toArray(): array
    {
        return [
            'remaining_mg' => $this->remainingMg,
            'remaining_ml' => $this->remainingMl,
            'remaining_iu' => $this->remainingIu,
            'concentration' => $this->concentration,
        ];
    }
}
