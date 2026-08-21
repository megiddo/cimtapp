<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\DomainException\DomainException;

final class ValidationException extends DomainException
{
    /**
     * @param array<string, list<string>> $fields
     */
    public function __construct(
        private readonly array $fields,
        string $message = 'Validation failed.',
        private readonly ?float $remainingIu = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, list<string>>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function remainingIu(): ?float
    {
        return $this->remainingIu;
    }
}
