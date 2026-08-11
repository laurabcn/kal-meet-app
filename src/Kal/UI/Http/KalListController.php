<?php

declare(strict_types=1);

namespace App\Kal\UI\Http;

use App\Kal\Application\Query\ListKals\ListKalsQuery;
use App\Shared\Application\Query\QueryBusInterface;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpOkResponse;
use App\Shared\Infrastructure\Symfony\Security\SupabaseUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class KalListController
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    /** @throws \InvalidArgumentException */
    #[Route('/kal', name: 'kal_list', methods: ['GET'])]
    public function __invoke(#[CurrentUser] SupabaseUser $user): JsonResponse
    {
        $response = $this->queryBus->ask(new ListKalsQuery($user->id()));

        return new ApiHttpOkResponse($response);
    }
}
