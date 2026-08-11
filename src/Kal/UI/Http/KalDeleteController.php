<?php

declare(strict_types=1);

namespace App\Kal\UI\Http;

use App\Kal\Application\Command\Kal\DeleteKalCommand;
use App\Shared\Application\Command\CommandBusInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpErrorResponse;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpNoContentResponse;
use App\Shared\Infrastructure\Symfony\Security\SupabaseUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class KalDeleteController
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    /** @throws \InvalidArgumentException */
    #[Route('/kal/{id}', name: 'kal_delete', methods: ['DELETE'])]
    public function __invoke(string $id, #[CurrentUser] SupabaseUser $user): JsonResponse
    {
        try {
            $kalId = self::requiredUlid($id);
        } catch (InvalidArgumentException $exception) {
            return ApiHttpErrorResponse::badRequest($exception->getMessage(), $exception->errorCode());
        }

        $this->commandBus->dispatch(new DeleteKalCommand($kalId, $user->id()));

        return new ApiHttpNoContentResponse();
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
