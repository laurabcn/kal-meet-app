<?php

declare(strict_types=1);

namespace App\Kal\UI\Http;

use App\Kal\Application\Command\CreateKal\CreateKalCommand;
use App\Shared\Application\Command\CommandBusInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class KalCreateController
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    /** @throws \InvalidArgumentException */
    #[Route('/kal', name: 'kal_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $command = self::buildCommand($request->toArray());
        } catch (JsonException) {
            return self::badRequest('kal_invalid_json');
        } catch (InvalidArgumentException $exception) {
            return self::badRequest($exception->getMessage());
        }

        $this->commandBus->dispatch($command);

        return new JsonResponse(null, Response::HTTP_CREATED);
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidArgumentException
     */
    private static function buildCommand(array $payload): CreateKalCommand
    {
        return new CreateKalCommand(
            self::requiredString($payload, 'organizerId'),
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
    private static function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (!\is_string($value) || '' === $value) {
            throw new InvalidArgumentException('kal_invalid_payload');
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
            throw new InvalidArgumentException('kal_invalid_payload');
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
            throw new InvalidArgumentException('kal_invalid_payload');
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
            throw new InvalidArgumentException('kal_invalid_payload');
        }

        return $value;
    }

    /** @throws \InvalidArgumentException */
    private static function badRequest(string $code): JsonResponse
    {
        return new JsonResponse(['error' => $code], Response::HTTP_BAD_REQUEST);
    }
}
