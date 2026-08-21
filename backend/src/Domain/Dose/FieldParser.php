<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\ValidationException;

final class FieldParser
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public static function from(array|object|null $body): self
    {
        $data = [];
        if (is_array($body)) {
            $data = $body;
        } elseif (is_object($body)) {
            $data = get_object_vars($body);
        }

        return new self($data);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function optionalString(string $key): ?string
    {
        if (!$this->has($key) || $this->data[$key] === null) {
            return null;
        }
        if (!is_string($this->data[$key])) {
            throw new ValidationException([$key => [DoseConfig::MUST_BE_TEXT]]);
        }
        $trimmed = trim($this->data[$key]);

        return $trimmed === '' ? null : $trimmed;
    }

    public function requireString(string $key): string
    {
        $value = $this->optionalString($key);
        if ($value === null) {
            throw new ValidationException([$key => [DoseConfig::MUST_BE_TEXT]]);
        }

        return $value;
    }

    public function optionalFloat(string $key): ?float
    {
        if (!$this->has($key) || $this->data[$key] === null) {
            return null;
        }

        return $this->requireFloat($key);
    }

    public function requireFloat(string $key): float
    {
        if (!$this->has($key)) {
            throw new ValidationException([$key => [DoseConfig::MUST_BE_NUMBER]]);
        }
        $raw = $this->data[$key];
        if (is_int($raw) || is_float($raw)) {
            $value = (float) $raw;
        } elseif (is_string($raw) && is_numeric($raw)) {
            $value = (float) $raw;
        } else {
            throw new ValidationException([$key => [DoseConfig::MUST_BE_NUMBER]]);
        }
        if (!is_finite($value)) {
            throw new ValidationException([$key => [DoseConfig::MUST_BE_NUMBER]]);
        }

        return $value;
    }

    public function requirePositiveFloat(string $key): float
    {
        $value = $this->requireFloat($key);
        if ($value <= 0.0) {
            throw new ValidationException([$key => [DoseConfig::MUST_BE_POSITIVE]]);
        }

        return $value;
    }

    public function requirePositiveInt(string $key): int
    {
        $value = $this->requireFloat($key);
        if ($value <= 0.0 || abs($value - round($value)) > 1e-9) {
            throw new ValidationException([$key => [DoseConfig::MUST_BE_WHOLE]]);
        }

        return (int) round($value);
    }

    public function optionalPositiveInt(string $key): ?int
    {
        if (!$this->has($key) || $this->data[$key] === null) {
            return null;
        }

        return $this->requirePositiveInt($key);
    }

    public function optionalBool(string $key): ?bool
    {
        if (!$this->has($key) || $this->data[$key] === null) {
            return null;
        }
        $raw = $this->data[$key];
        if (is_bool($raw)) {
            return $raw;
        }
        if ($raw === 1 || $raw === '1' || $raw === 'true') {
            return true;
        }
        if ($raw === 0 || $raw === '0' || $raw === 'false') {
            return false;
        }

        throw new ValidationException([$key => [DoseConfig::MUST_BE_BOOLEAN]]);
    }

    public function optionalDatetime(string $key): ?string
    {
        $value = $this->optionalString($key);
        if ($value === null) {
            return null;
        }

        return $this->normalizeDatetime($key, $value);
    }

    public function requireDatetime(string $key): string
    {
        $value = $this->optionalDatetime($key);
        if ($value === null) {
            throw new ValidationException([$key => [DoseConfig::MUST_BE_DATETIME]]);
        }

        return $value;
    }

    private function normalizeDatetime(string $key, string $value): string
    {
        $parsed = date_create($value);
        if ($parsed === false) {
            throw new ValidationException([$key => [DoseConfig::MUST_BE_DATETIME]]);
        }

        return $value;
    }
}
