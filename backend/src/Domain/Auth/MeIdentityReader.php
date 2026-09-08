<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use PDO;

final class MeIdentityReader
{
    /**
     * Identity snapshot from the unlocked user sqlite (not global DEK columns).
     *
     * @return array{email: string, has_password: bool, has_google: bool, remainder: null, open_vials: list<empty>}
     */
    public function fromUserDb(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT email, password_hash, google_sub FROM account LIMIT 1');
        if ($stmt === false) {
            throw new AuthenticationException(AuthConfig::AUTH_REQUIRED);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !isset($row['email']) || !is_string($row['email'])) {
            throw new AuthenticationException(AuthConfig::AUTH_REQUIRED);
        }

        $passwordHash = $row['password_hash'] ?? null;
        $googleSub = $row['google_sub'] ?? null;

        return [
            'email' => $row['email'],
            'has_password' => is_string($passwordHash) && $passwordHash !== '',
            'has_google' => is_string($googleSub) && $googleSub !== '',
            'remainder' => null,
            'open_vials' => [],
        ];
    }
}
