<?php

declare(strict_types=1);

namespace App\Application\Actions\LogBook\Log;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\Auth\ValidationException;
use App\Domain\Dose\DoseConfig;
use App\Domain\Dose\FieldParser;
use App\Domain\LogBook\LogEntryService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;

final class LogDefaultsAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly LogEntryService $log,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $params = $this->request->getQueryParams();
        try {
            $personId = FieldParser::from($params)->requireString('person_id');
        } catch (ValidationException $e) {
            throw new HttpBadRequestException($this->request, DoseConfig::MUST_BE_TEXT);
        }

        return $this->respondWithData($this->log->defaults($this->userPdo(), $personId));
    }
}