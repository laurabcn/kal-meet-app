<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Http\Response;

use Symfony\Component\HttpFoundation\Response;

/** 201 + body JSON buit (`{}`) — creates (commands void, l'id el porta el client). */
final class ApiHttpCreatedResponse extends ApiHttpResponse
{
    /**
     * @param array<string, mixed> $headers
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $headers = [])
    {
        parent::__construct(null, Response::HTTP_CREATED, $headers);
    }
}
