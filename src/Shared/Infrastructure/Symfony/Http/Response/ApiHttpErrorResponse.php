<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Http\Response;

use Symfony\Component\HttpFoundation\Response;

/**
 * Error API: `{"error":"<missatge llegible>","code":"<codi estable>"}`.
 * El FE mostra `error` o el tradueix amb `code`.
 */
final class ApiHttpErrorResponse extends ApiHttpResponse
{
    /**
     * @param array<string, mixed> $headers
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $message,
        string $errorCode,
        int $statusCode,
        array $headers = [],
    ) {
        parent::__construct(
            ['error' => $message, 'code' => $errorCode],
            $statusCode,
            $headers,
        );
    }

    /** @throws \InvalidArgumentException */
    public static function badRequest(string $message, string $errorCode): self
    {
        return new self($message, $errorCode, Response::HTTP_BAD_REQUEST);
    }
}
