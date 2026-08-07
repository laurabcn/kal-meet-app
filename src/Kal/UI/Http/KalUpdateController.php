<?php

declare(strict_types=1);

namespace App\Kal\UI\Http;

use App\Kal\Application\Command\Kal\UpdateKalCommand;
use App\Shared\Application\Command\CommandBusInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpErrorResponse;
use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpNoContentResponse;
use App\Shared\Infrastructure\Symfony\Security\SupabaseUser;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final readonly class KalUpdateController
{
    private const array REJECTED_FIELDS = ['id', 'organizerId', 'inviteToken', 'locales', 'files', 'clues', 'meetings'];

    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    /** @throws \InvalidArgumentException */
    #[Route('/kal/{id}', name: 'kal_update', methods: ['PATCH'])]
    public function __invoke(string $id, Request $request, #[CurrentUser] SupabaseUser $user): JsonResponse
    {
        try {
            $command = self::buildCommand($id, $request->toArray(), $user->id());
        } catch (JsonException) {
            $error = InvalidArgumentException::invalidJson();

            return ApiHttpErrorResponse::badRequest($error->getMessage(), $error->errorCode());
        } catch (InvalidArgumentException $exception) {
            return ApiHttpErrorResponse::badRequest($exception->getMessage(), $exception->errorCode());
        }

        $this->commandBus->dispatch($command);

        return new ApiHttpNoContentResponse();
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidArgumentException
     */
    private static function buildCommand(string $id, array $payload, string $organizerId): UpdateKalCommand
    {
        $kalId = self::requiredUlid($id);

        foreach (self::REJECTED_FIELDS as $field) {
            if (\array_key_exists($field, $payload)) {
                throw InvalidArgumentException::invalidPayload();
            }
        }

        $changes = [];
        if (\array_key_exists('name', $payload)) {
            $changes['name'] = self::requiredStringValue($payload['name']);
        }
        if (\array_key_exists('startsOn', $payload)) {
            $changes['startsOn'] = self::requiredStringValue($payload['startsOn']);
        }
        if (\array_key_exists('description', $payload)) {
            $changes['description'] = self::nullableStringValue($payload['description']);
        }
        if (\array_key_exists('endsOn', $payload)) {
            $changes['endsOn'] = self::nullableStringValue($payload['endsOn']);
        }
        if (\array_key_exists('coverPath', $payload)) {
            $changes['coverPath'] = self::nullableStringValue($payload['coverPath']);
        }

        return new UpdateKalCommand($kalId, $organizerId, $changes);
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

    /**
     * @throws InvalidArgumentException
     */
    private static function requiredStringValue(mixed $value): string
    {
        if (!\is_string($value) || '' === $value) {
            throw InvalidArgumentException::invalidPayload();
        }

        return $value;
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function nullableStringValue(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value) || '' === $value) {
            throw InvalidArgumentException::invalidPayload();
        }

        return $value;
    }
}
