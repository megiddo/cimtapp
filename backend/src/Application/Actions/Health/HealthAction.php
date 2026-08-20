<?php

declare(strict_types=1);

namespace App\Application\Actions\Health;

use App\Application\Actions\Action;
use App\Domain\Health\HealthStatus;
use Psr\Http\Message\ResponseInterface as Response;

class HealthAction extends Action
{
    protected function action(): Response
    {
        $status = HealthStatus::ok();
        $this->response->getBody()->write(
            json_encode($status->toArray(), JSON_THROW_ON_ERROR)
        );

        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
