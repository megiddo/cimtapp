<?php

declare(strict_types=1);

namespace Tests\Domain\Dose;

use App\Domain\Auth\ValidationException;
use App\Domain\Dose\DoseCalculator;
use App\Domain\Dose\DoseConfig;
use Tests\TestCase;

class DoseCalculatorTest extends TestCase
{
    private DoseCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new DoseCalculator();
    }

    public function testWorkedExampleTirzepatideU100EquivalentSyringe(): void
    {
        $concentration = $this->calc->concentration(10.0, 2.0);
        $this->assertEqualsWithDelta(5.0, $concentration, 1e-12);
        $this->assertEqualsWithDelta(0.01, $this->calc->mlPerIu(0.5, 50.0), 1e-12);
        $this->assertEqualsWithDelta(0.25, $this->calc->volumeMl(25.0, 0.5, 50.0), 1e-12);
        $this->assertEqualsWithDelta(1.25, $this->calc->peptideMg(25.0, 10.0, 2.0, 0.5, 50.0), 1e-12);

        $remainder = $this->calc->remaining(10.0, 1.25, 2.0, 0.5, 50.0);
        $this->assertEqualsWithDelta(8.75, $remainder->remainingMg, 1e-12);
        $this->assertEqualsWithDelta(1.75, $remainder->remainingMl, 1e-12);
        $this->assertEqualsWithDelta(175.0, $remainder->remainingIu, 1e-12);
        $this->assertEqualsWithDelta(5.0, $remainder->concentration, 1e-12);
        $this->assertSame(
            [
                'remaining_mg' => $remainder->remainingMg,
                'remaining_ml' => $remainder->remainingMl,
                'remaining_iu' => $remainder->remainingIu,
                'concentration' => $remainder->concentration,
            ],
            $remainder->toArray()
        );
    }

    public function testRemainingIncludesVolumeAdjustmentAndDepleted(): void
    {
        $remainder = $this->calc->remaining(10.0, 1.25, 2.0, 0.5, 50.0, -1.25);
        $this->assertEqualsWithDelta(7.5, $remainder->remainingMg, 1e-12);
        $this->assertEqualsWithDelta(1.5, $remainder->remainingMl, 1e-12);
        $this->assertTrue($this->calc->isDepleted(0.0));
        $this->assertTrue($this->calc->isDepleted(-0.01));
        $this->assertFalse($this->calc->isDepleted(0.001));
    }

    public function testNonU100SyringeOneMlPerFortyIu(): void
    {
        $this->assertEqualsWithDelta(0.025, $this->calc->mlPerIu(1.0, 40.0), 1e-12);
        $this->assertEqualsWithDelta(0.625, $this->calc->volumeMl(25.0, 1.0, 40.0), 1e-12);
        $this->assertEqualsWithDelta(3.125, $this->calc->peptideMg(25.0, 10.0, 2.0, 1.0, 40.0), 1e-12);

        $remainder = $this->calc->remaining(10.0, 3.125, 2.0, 1.0, 40.0);
        $this->assertEqualsWithDelta(6.875, $remainder->remainingMg, 1e-12);
        $this->assertEqualsWithDelta(1.375, $remainder->remainingMl, 1e-12);
        $this->assertEqualsWithDelta(55.0, $remainder->remainingIu, 1e-12);
    }

    public function testRoundMgUsesFourDecimalPlacesHalfUp(): void
    {
        $this->assertSame(1.2346, $this->calc->roundMg(1.23456));
        $this->assertSame(1.2345, $this->calc->roundMg(1.23454));
        $this->assertSame(1.25, $this->calc->roundMg(1.25));
        $this->assertNotSame(1.2346, $this->calc->roundMg(1.23454));
        $this->assertSame(0.0033, $this->calc->roundMg(0.01 * (1 / 3)));
    }

    public function testExactlyRemainingIuIsNotOverdraw(): void
    {
        $this->assertFalse($this->calc->exceedsRemainder(8.75, 8.75));
        $this->assertFalse($this->calc->exceedsRemainder(8.74999, 8.75));
        $this->assertTrue($this->calc->exceedsRemainder(8.7501, 8.75));
        $this->assertTrue($this->calc->exceedsRemainder(8.75005, 8.75));
        $this->assertFalse($this->calc->exceedsRemainder(0.0, 0.0));
    }

    public function testIuRejectsZeroNegativeAndTwoDecimals(): void
    {
        try {
            $this->calc->assertIu(0.0);
            $this->fail('expected validation');
        } catch (ValidationException $e) {
            $this->assertSame(['iu' => [DoseConfig::IU_NOT_POSITIVE]], $e->fields());
        }

        try {
            $this->calc->assertIu(-1.0);
            $this->fail('expected validation');
        } catch (ValidationException $e) {
            $this->assertSame(['iu' => [DoseConfig::IU_NOT_POSITIVE]], $e->fields());
        }

        $this->calc->assertIu(0.1);
        $this->calc->assertIu(25.0);
        $this->calc->assertIu(25.5);

        try {
            $this->calc->assertIu(25.12);
            $this->fail('expected validation');
        } catch (ValidationException $e) {
            $this->assertSame(['iu' => [DoseConfig::IU_ONE_DECIMAL]], $e->fields());
        }

        $this->assertTrue($this->calc->hasAtMostOneDecimal(25.5));
        $this->assertFalse($this->calc->hasAtMostOneDecimal(25.12));
        $this->assertTrue($this->calc->hasAtMostOneDecimal(25.0));
    }

    public function testPositiveGuardsOnConcentrationAndSyringe(): void
    {
        try {
            $this->calc->concentration(0.0, 2.0);
            $this->fail('expected validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('peptide_mg', $e->fields());
        }

        try {
            $this->calc->concentration(10.0, 0.0);
            $this->fail('expected validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('bac_water_ml', $e->fields());
        }

        try {
            $this->calc->mlPerIu(0.0, 50.0);
            $this->fail('expected validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('volume_ml', $e->fields());
        }

        try {
            $this->calc->mlPerIu(0.5, -1.0);
            $this->fail('expected validation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('capacity_iu', $e->fields());
        }
    }

    public function testFormatters(): void
    {
        $this->assertSame('25', DoseConfig::formatIu(25.0));
        $this->assertSame('18.5', DoseConfig::formatIu(18.5));
        $this->assertSame('18', DoseConfig::formatIu(18.0));
        $this->assertSame('0.5 mL / 50 IU', DoseConfig::syringeLabel(0.5, 50.0));
        $this->assertSame('1 mL / 40 IU', DoseConfig::syringeLabel(1.0, 40.0));
        $this->assertSame(
            '25 IU exceeds 18 IU remaining in this vial.',
            DoseConfig::overdraw('25', '18')
        );
        $this->assertSame('0.5', DoseConfig::trimNumber(0.5));
        $this->assertSame('1', DoseConfig::trimNumber(1.0));
    }

    public function testVolumeRoundingSixDecimals(): void
    {
        $this->assertSame(0.000001, $this->calc->roundVolume(0.0000014));
        $this->assertSame(0.000001, $this->calc->roundVolume(0.0000006));
        $this->assertNotSame(0.000002, $this->calc->roundVolume(0.0000004));
    }
}
