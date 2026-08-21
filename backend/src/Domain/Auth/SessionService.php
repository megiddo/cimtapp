<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;
use DateTimeZone;

final class SessionService
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    public function create(string $userId): Session
    {
        $now = $this->nowUtc();
        $session = new Session(
            $this->ids->sessionId(),
            $userId,
            $this->format($now->modify('+' . AuthConfig::SESSION_TTL_SECONDS . ' seconds')),
            $this->format($now),
        );
        $this->sessions->insert($session);

        return $session;
    }

    public function loadValid(string $id): ?Session
    {
        if ($id === '' || !preg_match('/^[0-9a-f]{64}$/', $id)) {
            return null;
        }

        $session = $this->sessions->findById($id);
        if ($session === null) {
            return null;
        }
        if ($this->isExpired($session)) {
            $this->sessions->delete($id);

            return null;
        }

        return $session;
    }

    public function touch(Session $session): Session
    {
        $expiresAt = $this->format($this->nowUtc()->modify('+' . AuthConfig::SESSION_TTL_SECONDS . ' seconds'));
        $this->sessions->updateExpiry($session->id, $expiresAt);

        return $session->withExpiresAt($expiresAt);
    }

    public function destroy(string $id): void
    {
        if ($id === '') {
            return;
        }
        $this->sessions->delete($id);
    }

    public function isExpired(Session $session): bool
    {
        $expires = new DateTimeImmutable($session->expiresAt);

        return $this->nowUtc() >= $expires;
    }

    public function ttlSeconds(): int
    {
        return AuthConfig::SESSION_TTL_SECONDS;
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
