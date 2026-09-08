<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class UserMeMapper
{
    /**
     * Identity for GET /me. Never includes DEK material.
     *
     * @return array{email: string, has_password: bool, has_google: bool, remainder: null, open_vials: list<empty>}
     */
    public static function fromUser(User $user): array
    {
        return [
            'email' => $user->email,
            'has_password' => $user->hasPassword(),
            'has_google' => $user->hasGoogle(),
            'remainder' => null,
            'open_vials' => [],
        ];
    }
}
