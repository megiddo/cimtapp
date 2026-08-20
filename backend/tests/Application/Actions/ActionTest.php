<?php

declare(strict_types=1);

namespace Tests\Application\Actions;

use App\Application\Actions\Action;
use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use App\Domain\DomainException\DomainRecordNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\NullLogger;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Tests\TestCase;

class ActionTest extends TestCase
{
    public function testRespondWithDataWrapsPayload(): void
    {
        $action = new class (new NullLogger()) extends Action {
            protected function action(): Response
            {
                return $this->respondWithData(['hello' => 'world'], 201);
            }
        };

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/unused');
        $response = $app->getResponseFactory()->createResponse();
        $result = $action($request, $response, []);

        $this->assertSame(201, $result->getStatusCode());
        $payload = json_decode((string) $result->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(201, $payload['statusCode']);
        $this->assertSame(['hello' => 'world'], $payload['data']);
    }

    public function testResolveArgAndFormData(): void
    {
        $action = new class (new NullLogger()) extends Action {
            protected function action(): Response
            {
                $id = $this->resolveArg('id');
                $body = $this->getFormData();

                return $this->respondWithData(['id' => $id, 'body' => $body]);
            }
        };

        $request = $this->createRequest('POST', '/unused')
            ->withParsedBody(['note' => 'hi']);
        $response = $this->getAppInstance()->getResponseFactory()->createResponse();
        $result = $action($request, $response, ['id' => 'abc']);

        $payload = json_decode((string) $result->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('abc', $payload['data']['id']);
        $this->assertSame(['note' => 'hi'], $payload['data']['body']);
    }

    public function testResolveArgMissingThrows(): void
    {
        $action = new class (new NullLogger()) extends Action {
            protected function action(): Response
            {
                $this->resolveArg('missing');

                return $this->respondWithData([]);
            }
        };

        $request = $this->createRequest('GET', '/unused');
        $response = $this->getAppInstance()->getResponseFactory()->createResponse();

        $this->expectException(HttpBadRequestException::class);
        $action($request, $response, []);
    }

    public function testDomainRecordNotFoundBecomesHttpNotFound(): void
    {
        $action = new class (new NullLogger()) extends Action {
            protected function action(): Response
            {
                throw new DomainRecordNotFoundException('nope');
            }
        };

        $request = $this->createRequest('GET', '/unused');
        $response = $this->getAppInstance()->getResponseFactory()->createResponse();

        $this->expectException(HttpNotFoundException::class);
        $action($request, $response, []);
    }

    public function testGetFormDataReturnsNullWhenBodyMissing(): void
    {
        $action = new class (new NullLogger()) extends Action {
            protected function action(): Response
            {
                return $this->respondWithData(['body' => $this->getFormData()]);
            }
        };

        $request = $this->createRequest('POST', '/unused')->withParsedBody(null);
        $response = $this->getAppInstance()->getResponseFactory()->createResponse();
        $result = $action($request, $response, []);
        $payload = json_decode((string) $result->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNull($payload['data']['body']);
    }

    public function testActionPayloadSerializesErrorWhenDataIsNull(): void
    {
        $error = new ActionError(ActionError::VALIDATION_ERROR, 'bad field');
        $payload = new ActionPayload(422, null, $error);
        $this->assertSame(422, $payload->getStatusCode());
        $this->assertNull($payload->getData());
        $this->assertSame($error, $payload->getError());

        $json = $payload->jsonSerialize();
        $this->assertSame(422, $json['statusCode']);
        $this->assertInstanceOf(ActionError::class, $json['error']);
        $this->assertArrayNotHasKey('data', $json);
    }

    public function testActionPayloadOmitsDataAndErrorWhenBothNull(): void
    {
        $payload = new ActionPayload(204, null, null);
        $json = $payload->jsonSerialize();
        $this->assertSame(['statusCode' => 204], $json);
    }

    public function testActionErrorMutators(): void
    {
        $error = new ActionError(ActionError::SERVER_ERROR, 'boom');
        $this->assertSame(ActionError::SERVER_ERROR, $error->getType());
        $this->assertSame('boom', $error->getDescription());

        $error->setType(ActionError::UNAUTHENTICATED);
        $error->setDescription('nope');
        $this->assertSame(
            ['type' => ActionError::UNAUTHENTICATED, 'description' => 'nope'],
            $error->jsonSerialize()
        );

        $error->setDescription(null);
        $this->assertNull($error->getDescription());
    }
}
