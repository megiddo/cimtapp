<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use App\Domain\Auth\AuthenticationException;
use App\Domain\Auth\AuthConfig;
use App\Domain\Auth\RateLimitException;
use App\Domain\Auth\ValidationException;
use App\Domain\Crypto\CryptoException;
use App\Infrastructure\Persistence\UserStoreLockedException;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;

class HttpErrorHandler extends SlimErrorHandler
{
    protected function respond(): Response
    {
        $exception = $this->exception;
        $statusCode = 500;
        $error = new ActionError(
            ActionError::SERVER_ERROR,
            'An internal error has occurred while processing your request.'
        );

        if ($exception instanceof ValidationException) {
            $statusCode = 422;
            $error = new ActionError(
                ActionError::VALIDATION_ERROR,
                $exception->getMessage(),
                $exception->fields(),
                $exception->remainingIu(),
            );
        } elseif ($exception instanceof AuthenticationException) {
            $statusCode = 401;
            $error = new ActionError(ActionError::UNAUTHENTICATED, $exception->getMessage());
        } elseif ($exception instanceof RateLimitException) {
            $statusCode = 429;
            $error = new ActionError(ActionError::TOO_MANY_REQUESTS, AuthConfig::RATE_LIMIT_MESSAGE);
        } elseif ($exception instanceof UserStoreLockedException) {
            $statusCode = 503;
            $error = new ActionError(ActionError::SERVICE_UNAVAILABLE, AuthConfig::STORE_BUSY);
        } elseif ($exception instanceof CryptoException) {
            $statusCode = 500;
            $error = new ActionError(
                ActionError::SERVER_ERROR,
                'An internal error has occurred while processing your request.'
            );
        } elseif ($exception instanceof HttpException) {
            $statusCode = $exception->getCode();
            $error->setDescription($exception->getMessage());

            if ($exception instanceof HttpNotFoundException) {
                $error->setType(ActionError::RESOURCE_NOT_FOUND);
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $error->setType(ActionError::NOT_ALLOWED);
            } elseif ($exception instanceof HttpUnauthorizedException) {
                $error->setType(ActionError::UNAUTHENTICATED);
            } elseif ($exception instanceof HttpForbiddenException) {
                $error->setType(ActionError::INSUFFICIENT_PRIVILEGES);
            } elseif ($exception instanceof HttpBadRequestException) {
                $error->setType(ActionError::BAD_REQUEST);
            } elseif ($exception instanceof HttpNotImplementedException) {
                $error->setType(ActionError::NOT_IMPLEMENTED);
            } elseif ($statusCode === 503) {
                $error->setType(ActionError::SERVICE_UNAVAILABLE);
            }
        }

        if (
            !($exception instanceof HttpException)
            && !($exception instanceof ValidationException)
            && !($exception instanceof AuthenticationException)
            && !($exception instanceof RateLimitException)
            && !($exception instanceof UserStoreLockedException)
            && !($exception instanceof CryptoException)
            && $this->displayErrorDetails
        ) {
            $error->setDescription($exception->getMessage());
        }

        $payload = new ActionPayload($statusCode, null, $error);
        $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write($encodedPayload);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
