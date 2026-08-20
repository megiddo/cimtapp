<?php

declare(strict_types=1);

namespace Tests\Domain\Health;

use App\Domain\Health\HealthStatus;
use InvalidArgumentException;
use Tests\TestCase;

class HealthStatusTest extends TestCase
{
    public function testOkFactory(): void
    {
        $status = HealthStatus::ok();
        $this->assertTrue($status->isOk());
        $this->assertFalse($status->isDegraded());
        $this->assertSame(HealthStatus::OK, $status->status());
        $this->assertSame(['status' => 'ok'], $status->toArray());
    }

    public function testDegradedFactory(): void
    {
        $status = HealthStatus::degraded();
        $this->assertFalse($status->isOk());
        $this->assertTrue($status->isDegraded());
        $this->assertSame(HealthStatus::DEGRADED, $status->status());
        $this->assertSame(['status' => 'degraded'], $status->toArray());
    }

    public function testFromStringAcceptsKnownValues(): void
    {
        $this->assertTrue(HealthStatus::fromString('ok')->isOk());
        $this->assertTrue(HealthStatus::fromString('degraded')->isDegraded());
    }

    public function testFromStringRejectsUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid health status: down');
        HealthStatus::fromString('down');
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HealthStatus::fromString('');
    }

    public function testEqualsComparesStatusOnly(): void
    {
        $this->assertTrue(HealthStatus::ok()->equals(HealthStatus::ok()));
        $this->assertFalse(HealthStatus::ok()->equals(HealthStatus::degraded()));
        $this->assertTrue(HealthStatus::degraded()->equals(HealthStatus::fromString('degraded')));
    }
}
