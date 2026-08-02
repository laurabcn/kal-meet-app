<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Base per a les respostes d'èxit de l'API. Els controllers usen les
 * subclasses tipades (`ApiHttpCreatedResponse`, …), no aquesta classe.
 *
 * `abstract` tanca el patró (no `new ApiHttpResponse(...)`) i evita que el
 * glob `App\` la registri com a servei amb args no autowirejables.
 */
abstract class ApiHttpResponse extends JsonResponse
{
    /**
     * @param array<string, mixed> $headers
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        mixed $data,
        int $statusCode,
        array $headers = [],
    ) {
        // Abans del parent: setEncodingOptions() fa setData() sobre $this->data
        // encara no inicialitzat i petaria.
        $this->encodingOptions = self::DEFAULT_ENCODING_OPTIONS | \JSON_PRESERVE_ZERO_FRACTION;
        parent::__construct($data, $statusCode, $headers);
    }
}
