<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Http\Response;

use Symfony\Component\HttpFoundation\Response;

/** Error API: `{"error":"<codi>"}` — mateixa forma que auth. */
final class ApiHttpErrorResponse extends ApiHttpResponse
{
    /**
     * @param array<string, mixed> $headers
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $errorCode, int $statusCode, array $headers = [])
    {
        parent::__construct(['error' => $errorCode], $statusCode, $headers);
    }

    /** @throws \InvalidArgumentException */
    public static function badRequest(string $errorCode): self
    {
        return new self($errorCode, Response::HTTP_BAD_REQUEST);
    }
}
