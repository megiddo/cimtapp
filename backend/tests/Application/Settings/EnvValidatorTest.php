<?php

declare(strict_types=1);

namespace Tests\Application\Settings;

use App\Application\Settings\EnvValidator;
use App\Application\Settings\Settings;
use InvalidArgumentException;
use Tests\TestCase;

class EnvValidatorTest extends TestCase
{
    private const HEX_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private EnvValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new EnvValidator();
    }

    /**
     * @return array<string, mixed>
     */
    private function baseEnv(array $overrides = []): array
    {
        return array_merge([
            'APP_ENV' => 'testing',
            'CIMT_MASTER_KEY' => self::HEX_KEY,
            'DATA_DIR' => '/tmp/cimtapp-data',
            'APP_URL' => 'http://localhost:24780',
            'SESSION_SECURE' => 'false',
        ], $overrides);
    }

    public function testValidateAcceptsHexMasterKeyWithoutGoogle(): void
    {
        $result = $this->validator->validate($this->baseEnv());

        $this->assertSame('testing', $result['appEnv']);
        $this->assertSame(self::HEX_KEY, $result['masterKey']);
        $this->assertSame('/tmp/cimtapp-data', $result['dataDir']);
        $this->assertSame('http://localhost:24780', $result['appUrl']);
        $this->assertFalse($result['sessionSecure']);
        $this->assertSame('', $result['googleClientId']);
        $this->assertSame('', $result['googleClientSecret']);
        $this->assertSame('', $result['googleRedirectUri']);
    }

    public function testValidateStripsTrailingSlashFromAppUrl(): void
    {
        $result = $this->validator->validate($this->baseEnv(['APP_URL' => 'https://cimt.example/']));
        $this->assertSame('https://cimt.example', $result['appUrl']);
    }

    public function testValidateAcceptsBase64MasterKey(): void
    {
        $key = base64_encode(str_repeat('A', 32));
        $result = $this->validator->validate($this->baseEnv(['CIMT_MASTER_KEY' => $key]));
        $this->assertSame($key, $result['masterKey']);
        $this->assertTrue($this->validator->isValidMasterKey($key));
    }

    public function testValidateRejectsShortHexKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CIMT_MASTER_KEY');
        $this->validator->validate($this->baseEnv(['CIMT_MASTER_KEY' => 'abcd']));
    }

    public function testValidateRejectsEmptyMasterKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validate($this->baseEnv(['CIMT_MASTER_KEY' => '   ']));
    }

    public function testValidateRejectsInvalidBase64Length(): void
    {
        $this->assertFalse($this->validator->isValidMasterKey(base64_encode('short')));
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validate($this->baseEnv([
            'CIMT_MASTER_KEY' => base64_encode('not-32-bytes'),
        ]));
    }

    public function testIsValidMasterKeyRejectsMalformedBase64(): void
    {
        $this->assertFalse($this->validator->isValidMasterKey('***not-base64***'));
        $this->assertFalse($this->validator->isValidMasterKey(''));
        $this->assertTrue($this->validator->isValidMasterKey(strtoupper(self::HEX_KEY)));
    }

    public function testValidateRejectsUnknownAppEnv(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('APP_ENV');
        $this->validator->validate($this->baseEnv(['APP_ENV' => 'staging']));
    }

    public function testValidateRejectsWhitespaceDataDir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DATA_DIR');
        $this->validator->validate($this->baseEnv(['DATA_DIR' => '   ']));
    }

    public function testValidateRejectsNullBytePath(): void
    {
        $this->assertFalse($this->validator->isSafePath("foo\0bar"));
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validate($this->baseEnv(['DATA_DIR' => "foo\0bar"]));
    }

    public function testValidateRejectsNonHttpUrl(): void
    {
        $this->assertFalse($this->validator->isValidAppUrl('ftp://example.com'));
        $this->assertFalse($this->validator->isValidAppUrl('not-a-url'));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('APP_URL');
        $this->validator->validate($this->baseEnv(['APP_URL' => 'ftp://example.com']));
    }

    public function testSessionSecureTruthyValues(): void
    {
        $this->assertTrue($this->validator->isTruthy('true'));
        $this->assertTrue($this->validator->isTruthy('TRUE'));
        $this->assertTrue($this->validator->isTruthy('1'));
        $this->assertTrue($this->validator->isTruthy('yes'));
        $this->assertTrue($this->validator->isTruthy('on'));
        $this->assertFalse($this->validator->isTruthy('false'));
        $this->assertFalse($this->validator->isTruthy('0'));
        $this->assertFalse($this->validator->isTruthy('off'));

        $result = $this->validator->validate($this->baseEnv(['SESSION_SECURE' => 'true']));
        $this->assertTrue($result['sessionSecure']);
    }

    public function testProductionRequiresGoogle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GOOGLE_CLIENT_ID');
        $this->validator->validate($this->baseEnv(['APP_ENV' => 'production']));
    }

    public function testProductionAcceptsCompleteGoogleConfig(): void
    {
        $result = $this->validator->validate($this->baseEnv([
            'APP_ENV' => 'production',
            'GOOGLE_CLIENT_ID' => 'id.apps.googleusercontent.com',
            'GOOGLE_CLIENT_SECRET' => 'secret',
            'GOOGLE_REDIRECT_URI' => 'https://cimt.example/api/v1/auth/google/callback',
            'SESSION_SECURE' => 'on',
        ]));

        $this->assertSame('production', $result['appEnv']);
        $this->assertTrue($result['sessionSecure']);
        $this->assertSame('id.apps.googleusercontent.com', $result['googleClientId']);
        $this->assertSame('secret', $result['googleClientSecret']);
        $this->assertSame('https://cimt.example/api/v1/auth/google/callback', $result['googleRedirectUri']);
        $this->assertTrue($this->validator->requiresGoogleOAuth('production'));
        $this->assertFalse($this->validator->requiresGoogleOAuth('testing'));
        $this->assertFalse($this->validator->requiresGoogleOAuth('development'));
    }

    public function testProductionRejectsInvalidGoogleRedirect(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GOOGLE_REDIRECT_URI');
        $this->validator->validate($this->baseEnv([
            'APP_ENV' => 'production',
            'GOOGLE_CLIENT_ID' => 'id',
            'GOOGLE_CLIENT_SECRET' => 'secret',
            'GOOGLE_REDIRECT_URI' => 'not-a-url',
        ]));
    }

    public function testKnownAppEnvs(): void
    {
        $this->assertTrue($this->validator->isKnownAppEnv('testing'));
        $this->assertTrue($this->validator->isKnownAppEnv('development'));
        $this->assertTrue($this->validator->isKnownAppEnv('production'));
        $this->assertFalse($this->validator->isKnownAppEnv(''));
        $this->assertFalse($this->validator->isKnownAppEnv('prod'));
    }

    public function testSettingsGetAllAndByKey(): void
    {
        $settings = new Settings(['displayErrorDetails' => true, 'appEnv' => 'testing']);
        $this->assertTrue($settings->get('displayErrorDetails'));
        $this->assertSame('testing', $settings->get('appEnv'));
        $all = $settings->get();
        $this->assertIsArray($all);
        $this->assertSame('testing', $all['appEnv']);
    }

    public function testDefaultsFillMissingOptionalKeys(): void
    {
        $result = $this->validator->validate([
            'CIMT_MASTER_KEY' => self::HEX_KEY,
        ]);

        $this->assertSame('development', $result['appEnv']);
        $this->assertNotSame('', $result['dataDir']);
        $this->assertSame('http://localhost:24780', $result['appUrl']);
        $this->assertFalse($result['sessionSecure']);
    }

    public function testReadCoercesNumericAndBooleanEnv(): void
    {
        $boolTrue = $this->validator->validate($this->baseEnv([
            'SESSION_SECURE' => true,
        ]));
        $this->assertTrue($boolTrue['sessionSecure']);

        $boolFalse = $this->validator->validate($this->baseEnv([
            'SESSION_SECURE' => false,
        ]));
        $this->assertFalse($boolFalse['sessionSecure']);

        $intResult = $this->validator->validate($this->baseEnv([
            'SESSION_SECURE' => 1,
        ]));
        $this->assertTrue($intResult['sessionSecure']);

        $floatResult = $this->validator->validate($this->baseEnv([
            'SESSION_SECURE' => 0.0,
        ]));
        $this->assertFalse($floatResult['sessionSecure']);
    }

    public function testReadIgnoresNonScalarEnvValues(): void
    {
        $result = $this->validator->validate($this->baseEnv([
            'APP_URL' => ['http://bad.example'],
        ]));
        $this->assertSame('http://localhost:24780', $result['appUrl']);
    }

    public function testProductionRejectsMissingGoogleSecret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GOOGLE_CLIENT_SECRET');
        $this->validator->validate($this->baseEnv([
            'APP_ENV' => 'production',
            'GOOGLE_CLIENT_ID' => 'id',
            'GOOGLE_CLIENT_SECRET' => '',
            'GOOGLE_REDIRECT_URI' => 'https://cimt.example/callback',
        ]));
    }

    public function testMergeProcessEnvLetsNonEmptyDotenvBeatEmptyApachePlaceholders(): void
    {
        $merged = $this->validator->mergeProcessEnv(
            ['GOOGLE_CLIENT_ID' => '', 'CIMT_MASTER_KEY' => self::HEX_KEY],
            ['GOOGLE_CLIENT_ID' => 'id.apps.googleusercontent.com'],
            ['GOOGLE_CLIENT_SECRET' => 'secret'],
        );

        $this->assertSame('id.apps.googleusercontent.com', $merged['GOOGLE_CLIENT_ID']);
        $this->assertSame('secret', $merged['GOOGLE_CLIENT_SECRET']);
        $this->assertSame(self::HEX_KEY, $merged['CIMT_MASTER_KEY']);
    }
}
