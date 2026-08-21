<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;
use DateTimeZone;

final class OauthStateService
{
    public function __construct(
        private readonly OauthStateRepository $states,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    public function issue(string $codeVerifier): OauthState
    {
        $row = new OauthState(
            $this->ids->oauthState(),
            $this->format($this->nowUtc()->modify('+' . AuthConfig::OAUTH_STATE_TTL_SECONDS . ' seconds')),
            '/',
            $codeVerifier,
        );
        $this->states->insert($row);

        return $row;
    }

    public function consume(string $state): ?OauthState
    {
        if ($state === '') {
            return null;
        }

        $row = $this->states->findByState($state);
        if ($row === null) {
            return null;
        }
        $this->states->delete($state);

        $expires = new DateTimeImmutable($row->expiresAt);
        if ($this->nowUtc() >= $expires) {
            return null;
        }
        if ($row->codeVerifier === '') {
            return null;
        }

        return $row;
    }

    private function nowUtc(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
