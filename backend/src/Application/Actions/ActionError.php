<?php

declare(strict_types=1);

namespace App\Application\Actions;

use JsonSerializable;

class ActionError implements JsonSerializable
{
    public const BAD_REQUEST = 'BAD_REQUEST';
    public const INSUFFICIENT_PRIVILEGES = 'INSUFFICIENT_PRIVILEGES';
    public const NOT_ALLOWED = 'NOT_ALLOWED';
    public const NOT_IMPLEMENTED = 'NOT_IMPLEMENTED';
    public const RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    public const SERVER_ERROR = 'SERVER_ERROR';
    public const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    public const TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const VERIFICATION_ERROR = 'VERIFICATION_ERROR';

    private string $type;

    private ?string $description;

    /** @var array<string, list<string>>|null */
    private ?array $fields;

    private ?float $remainingIu;

    /**
     * @param array<string, list<string>>|null $fields
     */
    public function __construct(
        string $type,
        ?string $description = null,
        ?array $fields = null,
        ?float $remainingIu = null,
    ) {
        $this->type = $type;
        $this->description = $description;
        $this->fields = $fields;
        $this->remainingIu = $remainingIu;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description = null): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return array<string, list<string>>|null
     */
    public function getFields(): ?array
    {
        return $this->fields;
    }

    /**
     * @param array<string, list<string>>|null $fields
     */
    public function setFields(?array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function getRemainingIu(): ?float
    {
        return $this->remainingIu;
    }

    public function setRemainingIu(?float $remainingIu): self
    {
        $this->remainingIu = $remainingIu;

        return $this;
    }

    /**
     * @return array{
     *     type: string,
     *     description: ?string,
     *     fields?: array<string, list<string>>,
     *     remaining_iu?: float
     * }
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'type' => $this->type,
            'description' => $this->description,
        ];
        if ($this->fields !== null) {
            $payload['fields'] = $this->fields;
        }
        if ($this->remainingIu !== null) {
            $payload['remaining_iu'] = $this->remainingIu;
        }

        return $payload;
    }
}
