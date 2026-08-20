<?php

declare(strict_types=1);

namespace App\Domain\Crypto;

use App\Domain\Auth\UserRepository;

/**
 * Re-wraps every users.encrypted_dek with a new AMK. Does not rewrite .enc files.
 */
final class AmkRotator
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function rotate(Crypto $from, Crypto $to): int
    {
        $rotated = 0;
        foreach ($this->users->listAll() as $user) {
            $wrapped = $from->rewrapDek($user->dekNonce, $user->encryptedDek, $to);
            $this->users->updateWrappedDek($user->id, $wrapped);
            $rotated++;
        }

        return $rotated;
    }
}
