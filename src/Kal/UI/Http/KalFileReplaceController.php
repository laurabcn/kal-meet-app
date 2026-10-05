<?php

declare(strict_types=1);

namespace App\Kal\UI\Http;

use App\Kal\Application\Command\Kal\ReplaceKalFileCommand;
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
final readonly class KalFileReplaceController
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    /** @throws \InvalidArgumentException */
    #[Route('/kal/{kalId}/file', name: 'kal_file_replace', methods: ['PUT'])]
    public function __invoke(string $kalId, Request $request, #[CurrentUser] SupabaseUser $user): JsonResponse
    {
        try {
            $id = self::requiredUlid($kalId);
            $payload = $request->toArray();
        } catch (JsonException) {
            $error = InvalidArgumentException::invalidJson();

            return ApiHttpErrorResponse::badRequest($error->getMessage(), $error->errorCode());
        } catch (InvalidArgumentException $exception) {
            return ApiHttpErrorResponse::badRequest($exception->getMessage(), $exception->errorCode());
        }

        // El cos és el fitxer, sense embolcall: el valida el handler, després
        // de comprovar que el KAL és teu (404 abans que 400).
        $this->commandBus->dispatch(new ReplaceKalFileCommand($id, $user->id(), $payload));

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
