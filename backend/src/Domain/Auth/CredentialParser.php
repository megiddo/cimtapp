<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * Reads email/password from a JSON or form body.
 */
final class CredentialParser
{
    /**
     * @param array<string, mixed>|object|null $body
     * @return array{email: string, password: string}
     */
    public function parse(array|object|null $body): array
    {
        $data = [];
        if (is_array($body)) {
            $data = $body;
        } elseif (is_object($body)) {
            $data = get_object_vars($body);
        }

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (!is_string($email)) {
            $email = '';
        }
        if (!is_string($password)) {
            $password = '';
        }

        return ['email' => $email, 'password' => $password];
    }
}
