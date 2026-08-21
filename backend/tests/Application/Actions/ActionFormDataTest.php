<?php

declare(strict_types=1);

namespace Tests\Application\Actions;

use App\Application\Actions\Action;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\NullLogger;
use Tests\TestCase;

class ActionFormDataTest extends TestCase
{
    public function testGetFormDataAcceptsObjectBody(): void
    {
        $action = new class (new NullLogger()) extends Action {
            protected function action(): Response
            {
                $body = $this->getFormData();
                $name = is_object($body) && isset($body->name) ? (string) $body->name : '';

                return $this->respondWithData(['name' => $name]);
            }
        };

        $request = $this->createRequest('POST', '/unused')
            ->withParsedBody((object) ['name' => 'vial']);
        $response = $this->getAppInstance()->getResponseFactory()->createResponse();
        $result = $action($request, $response, []);
        $payload = json_decode((string) $result->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('vial', $payload['data']['name']);
    }
}
