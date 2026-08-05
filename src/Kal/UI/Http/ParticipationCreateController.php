<?php

declare(strict_types=1);

namespace App\Kal\UI\Http;

use App\Kal\Application\Command\Participation\CreateParticipationCommand;
use App\Shared\Application\Command\CommandBusInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpCreatedResponse;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpErrorResponse;
use App\Shared\Infrastructure\Symfony\Security\SupabaseUser;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class ParticipationCreateController
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    /** @throws \InvalidArgumentException */
    #[Route('/kal/participation', name: 'kal_participation_create', methods: ['POST'])]
    public function __invoke(Request $request, #[CurrentUser] SupabaseUser $user): JsonResponse
    {
        try {
            $command = self::buildCommand($request->toArray(), $user->id());
        } catch (JsonException) {
            $error = InvalidArgumentException::invalidJson();

            return ApiHttpErrorResponse::badRequest($error->getMessage(), $error->errorCode());
        } catch (InvalidArgumentException $exception) {
            return ApiHttpErrorResponse::badRequest($exception->getMessage(), $exception->errorCode());
        }

        $this->commandBus->dispatch($command);

        return new ApiHttpCreatedResponse();
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidArgumentException
     */
    private static function buildCommand(array $payload, string $userId): CreateParticipationCommand
    {
        return new CreateParticipationCommand(
            self::requiredUlid($payload, 'kalId'),
            self::requiredString($payload, 'inviteToken'),
            $userId,
        );
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidArgumentException
     */
    private static function requiredUlid(array $payload, string $key): string
    {
        $value = self::requiredString($payload, $key);

        try {
            return UlidValue::create($value)->value();
        } catch (InvalidArgumentException) {
            throw InvalidArgumentException::invalidPayload();
        }
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidArgumentException
     */
    private static function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (!\is_string($value) || '' === $value) {
            throw InvalidArgumentException::invalidPayload();
        }

        return $value;
    }
}
