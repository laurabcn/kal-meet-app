<?php

declare(strict_types=1);

namespace App\Kal\UI\Http;

use App\Kal\Application\Command\CreateKal\CreateKalCommand;
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
final readonly class KalCreateController
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    /** @throws \InvalidArgumentException */
    #[Route('/kal', name: 'kal_create', methods: ['POST'])]
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
    private static function buildCommand(array $payload, string $organizerId): CreateKalCommand
    {
        self::rejectTokenDerived($payload, 'organizerId');

        return new CreateKalCommand(
            self::requiredUlid($payload, 'id'),
            $organizerId,
            self::requiredString($payload, 'name'),
            self::requiredString($payload, 'startsOn'),
            self::requiredList($payload, 'locales'),
            self::optionalList($payload, 'files'),
            self::optionalList($payload, 'clues'),
            self::optionalString($payload, 'description'),
            self::optionalString($payload, 'endsOn'),
            self::optionalString($payload, 'coverPath'),
            self::optionalList($payload, 'meetings'),
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
    private static function rejectTokenDerived(array $payload, string $key): void
    {
        if (\array_key_exists($key, $payload)) {
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

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidArgumentException
     */
    private static function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_string($value) || '' === $value) {
            throw InvalidArgumentException::invalidPayload();
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return list<mixed>
     *
     * @throws InvalidArgumentException
     */
    private static function requiredList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (!\is_array($value) || !array_is_list($value) || [] === $value) {
            throw InvalidArgumentException::invalidPayload();
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return list<mixed>
     *
     * @throws InvalidArgumentException
     */
    private static function optionalList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (null === $value) {
            return [];
        }

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidArgumentException::invalidPayload();
        }

        return $value;
    }
}
