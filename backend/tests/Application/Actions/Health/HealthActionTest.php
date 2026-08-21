<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Health;

use Tests\TestCase;

class HealthActionTest extends TestCase
{
    public function testHealthReturnsOkJson(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/api/v1/health');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['status' => 'ok'], $payload);
        $this->assertArrayNotHasKey('statusCode', $payload);
    }

    public function testHealthRejectsPost(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('POST', '/api/v1/health');
        $response = $app->handle($request);

        $this->assertSame(405, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(405, $payload['statusCode']);
        $this->assertSame('NOT_ALLOWED', $payload['error']['type']);
    }
}
