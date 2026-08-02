<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Http\Response;

use Symfony\Component\HttpFoundation\Response;

/** 204 sense cos — updates/deletes sense payload de retorn. */
final class ApiHttpNoContentResponse extends ApiHttpResponse
{
    /**
     * @param array<string, mixed> $headers
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $headers = [])
    {
        parent::__construct(null, Response::HTTP_NO_CONTENT, $headers);
        $this->setContent('');
    }
}
