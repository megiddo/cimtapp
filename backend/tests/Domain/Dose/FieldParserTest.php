<?php

declare(strict_types=1);

namespace Tests\Domain\Dose;

use App\Domain\Auth\ValidationException;
use App\Domain\Dose\DoseConfig;
use App\Domain\Dose\FieldParser;
use Tests\TestCase;

class FieldParserTest extends TestCase
{
    public function testReadsArrayAndObjectBodies(): void
    {
        $fromArray = FieldParser::from(['notes' => '  hi  ', 'iu' => 25]);
        $this->assertSame('hi', $fromArray->optionalString('notes'));
        $this->assertTrue($fromArray->has('iu'));
        $this->assertFalse($fromArray->has('missing'));

        $fromObject = FieldParser::from((object) ['notes' => 'x']);
        $this->assertSame('x', $fromObject->optionalString('notes'));

        $empty = FieldParser::from(null);
        $this->assertNull($empty->optionalString('notes'));
        $this->assertNull($empty->optionalFloat('iu'));
        $this->assertNull($empty->optionalBool('is_default'));
        $this->assertNull($empty->optionalDatetime('used_at'));
    }

    public function testStringRules(): void
    {
        $parser = FieldParser::from(['notes' => '', 'label' => 1]);
        $this->assertNull($parser->optionalString('notes'));
        try {
            $parser->optionalString('label');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['label' => [DoseConfig::MUST_BE_TEXT]], $e->fields());
        }

        try {
            FieldParser::from([])->requireString('label');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('label', $e->fields());
        }

        $this->assertSame('ok', FieldParser::from(['label' => 'ok'])->requireString('label'));
    }

    public function testFloatRules(): void
    {
        $this->assertSame(2.5, FieldParser::from(['n' => 2.5])->requireFloat('n'));
        $this->assertSame(3.0, FieldParser::from(['n' => 3])->requireFloat('n'));
        $this->assertSame(4.5, FieldParser::from(['n' => '4.5'])->requireFloat('n'));
        $this->assertSame(1.0, FieldParser::from(['n' => 1])->requirePositiveFloat('n'));
        $this->assertSame(0.0, FieldParser::from(['n' => 0])->requireNonNegativeFloat('n'));
        $this->assertSame(1.5, FieldParser::from(['n' => 1.5])->requireNonNegativeFloat('n'));

        try {
            FieldParser::from([])->requireFloat('n');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['n' => [DoseConfig::MUST_BE_NUMBER]], $e->fields());
        }

        try {
            FieldParser::from(['n' => 'nope'])->requireFloat('n');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['n' => [DoseConfig::MUST_BE_NUMBER]], $e->fields());
        }

        try {
            FieldParser::from(['n' => INF])->requireFloat('n');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['n' => [DoseConfig::MUST_BE_NUMBER]], $e->fields());
        }

        try {
            FieldParser::from(['n' => 0])->requirePositiveFloat('n');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['n' => [DoseConfig::MUST_BE_POSITIVE]], $e->fields());
        }

        try {
            FieldParser::from(['n' => -1])->requirePositiveFloat('n');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['n' => [DoseConfig::MUST_BE_POSITIVE]], $e->fields());
        }

        try {
            FieldParser::from(['n' => -0.1])->requireNonNegativeFloat('n');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['n' => [DoseConfig::MUST_BE_NON_NEGATIVE]], $e->fields());
        }

        $this->assertSame(2.0, FieldParser::from(['n' => 2])->optionalFloat('n'));
        $this->assertNull(FieldParser::from(['n' => null])->optionalFloat('n'));
    }

    public function testPositiveIntRules(): void
    {
        $this->assertSame(3, FieldParser::from(['n' => 3])->requirePositiveInt('n'));
        $this->assertSame(4, FieldParser::from(['n' => '4'])->requirePositiveInt('n'));
        $this->assertSame(5, FieldParser::from(['n' => 5.0])->requirePositiveInt('n'));
        $this->assertSame(2, FieldParser::from(['n' => 2])->optionalPositiveInt('n'));
        $this->assertNull(FieldParser::from(['n' => null])->optionalPositiveInt('n'));
        $this->assertNull(FieldParser::from([])->optionalPositiveInt('n'));

        try {
            FieldParser::from(['n' => 0])->requirePositiveInt('n');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['n' => [DoseConfig::MUST_BE_WHOLE]], $e->fields());
        }

        try {
            FieldParser::from(['n' => 1.5])->requirePositiveInt('n');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['n' => [DoseConfig::MUST_BE_WHOLE]], $e->fields());
        }
    }

    public function testBoolAndDatetime(): void
    {
        $this->assertTrue(FieldParser::from(['f' => true])->optionalBool('f'));
        $this->assertFalse(FieldParser::from(['f' => false])->optionalBool('f'));
        $this->assertTrue(FieldParser::from(['f' => 1])->optionalBool('f'));
        $this->assertFalse(FieldParser::from(['f' => 0])->optionalBool('f'));
        $this->assertTrue(FieldParser::from(['f' => '1'])->optionalBool('f'));
        $this->assertFalse(FieldParser::from(['f' => '0'])->optionalBool('f'));
        $this->assertTrue(FieldParser::from(['f' => 'true'])->optionalBool('f'));
        $this->assertFalse(FieldParser::from(['f' => 'false'])->optionalBool('f'));

        try {
            FieldParser::from(['f' => 'yes'])->optionalBool('f');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['f' => [DoseConfig::MUST_BE_BOOLEAN]], $e->fields());
        }

        $this->assertSame(
            '2026-08-20T15:00',
            FieldParser::from(['t' => '2026-08-20T15:00'])->requireDatetime('t')
        );
        $this->assertSame(
            '2026-08-20T15:00:00Z',
            FieldParser::from(['t' => '2026-08-20T15:00:00Z'])->optionalDatetime('t')
        );

        try {
            FieldParser::from(['t' => 'not-a-date'])->optionalDatetime('t');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['t' => [DoseConfig::MUST_BE_DATETIME]], $e->fields());
        }

        try {
            FieldParser::from([])->requireDatetime('t');
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['t' => [DoseConfig::MUST_BE_DATETIME]], $e->fields());
        }
    }
}
