<?php

declare(strict_types=1);

namespace App\Kal\UI\Http;

use App\Kal\Application\Command\Clue\UpdateClueCommand;
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
final readonly class ClueUpdateController
{
    /** El PDF i la reunió tenen (o tindran) el seu propi camí; aquí no entren. */
    private const array REJECTED_FIELDS = ['id', 'kalId', 'file', 'meeting', 'updatedAt'];

    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    /** @throws \InvalidArgumentException */
    #[Route('/kal/{kalId}/clue/{clueId}', name: 'clue_update', methods: ['PATCH'])]
    public function __invoke(
        string $kalId,
        string $clueId,
        Request $request,
        #[CurrentUser] SupabaseUser $user,
    ): JsonResponse {
        try {
            $command = self::buildCommand($kalId, $clueId, $request->toArray(), $user->id());
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
    private static function buildCommand(
        string $kalId,
        string $clueId,
        array $payload,
        string $organizerId,
    ): UpdateClueCommand {
        foreach (self::REJECTED_FIELDS as $field) {
            if (\array_key_exists($field, $payload)) {
                throw InvalidArgumentException::invalidPayload();
            }
        }

        $changes = [];
        foreach (['name', 'startsOn', 'endsOn', 'locale'] as $key) {
            if (\array_key_exists($key, $payload)) {
                $changes[$key] = self::requiredStringValue($payload[$key]);
            }
        }
        if (\array_key_exists('description', $payload)) {
            $changes['description'] = self::nullableStringValue($payload['description']);
        }

        return new UpdateClueCommand(
            self::requiredUlid($kalId),
            $organizerId,
            self::requiredUlid($clueId),
            $changes,
        );
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

        return self::requiredStringValue($value);
    }
}
