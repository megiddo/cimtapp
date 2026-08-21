<?php

declare(strict_types=1);

namespace App\Domain\Auth;

interface OauthStateRepository
{
    public function insert(OauthState $state): void;

    public function findByState(string $state): ?OauthState;

    public function delete(string $state): void;
}
