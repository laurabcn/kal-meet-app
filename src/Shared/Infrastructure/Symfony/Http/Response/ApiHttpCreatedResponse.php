<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Http\Response;

use Symfony\Component\HttpFoundation\Response;

/**
 * 201 — creates. Sense arguments, cos JSON buit (`{}`): és el cas de quan l'id
 * el porta el client, com al `POST /kal`. Amb `$data`, cos `{"data": …}` amb el
 * mateix envelope que les lectures, per als creates on l'id el mina el servidor
 * i el client no té cap altra manera de saber-lo.
 */
final class ApiHttpCreatedResponse extends ApiHttpResponse
{
    /**
     * @param array<string, mixed>|null $data
     * @param array<string, mixed>      $headers
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(?array $data = null, array $headers = [])
    {
        parent::__construct(
            null === $data ? null : ['data' => $data],
            Response::HTTP_CREATED,
            $headers,
        );
    }
}
