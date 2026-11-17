<?php

declare(strict_types=1);

namespace Tests\Domain\LogBook;

use App\Domain\LogBook\LogBookFormatter;
use PHPUnit\Framework\TestCase;

final class LogBookFormatterTest extends TestCase
{
    public function testFormatConcentrationUsesTwoDecimalsWhenRound(): void
    {
        $this->assertSame('5.00 mg/mL', LogBookFormatter::formatConcentration(5.0));
    }

    public function testFormatMgUsesThreeDecimalsWhenNeeded(): void
    {
        $this->assertSame('5.005', LogBookFormatter::formatMg(5.005));
    }

    public function testRoundMgAndVolume(): void
    {
        $this->assertEqualsWithDelta(1.2346, LogBookFormatter::roundMg(1.23456), 1e-9);
        $this->assertEqualsWithDelta(0.123457, LogBookFormatter::roundVolume(0.1234567), 1e-9);
    }
}