<?php

declare(strict_types=1);

namespace App\Application\Actions\Syringes;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\Clock;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\ArchivePolicy;
use App\Domain\Dose\SyringeService;
use App\Domain\Dose\SyringeStock;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class ArchiveSyringeAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly SyringeService $syringes,
        private readonly ArchivePolicy $archivePolicy,
        private readonly Clock $clock,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $pdo = $this->userPdo();
        $id = (string) $this->resolveArg('id');
        $existing = $this->syringes->get($pdo, $id);
        $this->archivePolicy->apply(new SyringeStock($pdo, $existing), $this->clock);

        return $this->respondWithData($this->syringes->get($pdo, $id));
    }
}
