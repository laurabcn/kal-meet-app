<?php

declare(strict_types=1);

namespace App\Kal\UI\Http;

use App\Kal\Application\Query\GetKal\GetKalQuery;
use App\Shared\Application\Query\QueryBusInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpErrorResponse;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpOkResponse;
use App\Shared\Infrastructure\Symfony\Security\SupabaseUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class KalGetController
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    /** @throws \InvalidArgumentException */
    #[Route('/kal/{id}', name: 'kal_get', methods: ['GET'])]
    public function __invoke(string $id, #[CurrentUser] SupabaseUser $user): JsonResponse
    {
        try {
            $kalId = self::requiredUlid($id);
        } catch (InvalidArgumentException $exception) {
            return ApiHttpErrorResponse::badRequest($exception->getMessage(), $exception->errorCode());
        }

        $response = $this->queryBus->ask(new GetKalQuery($kalId, $user->id()));

        return new ApiHttpOkResponse($response);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function requiredUlid(string $id): string
    {
        try {
            return UlidValue::create($id)->value();
        } catch (InvalidArgumentException) {
            throw InvalidArgumentException::invalidPayload();
        }
    }
}
