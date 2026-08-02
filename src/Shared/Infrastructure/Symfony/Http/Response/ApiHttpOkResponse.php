<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Http\Response;

use App\Shared\Application\Query\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/** 200 + `{"data": ...}` — lectures (queries). */
final class ApiHttpOkResponse extends ApiHttpResponse
{
    /**
     * @param array<string, mixed> $headers
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(ResponseInterface $response, array $headers = [])
    {
        parent::__construct(
            ['data' => $response->result()],
            Response::HTTP_OK,
            $headers,
        );
    }
}
