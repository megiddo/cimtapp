<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class EmailNormalizer
{
    public function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    public function isValid(string $email): bool
    {
        if ($email === '' || str_contains($email, "\0")) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
