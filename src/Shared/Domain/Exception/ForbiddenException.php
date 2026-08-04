<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use Symfony\Component\HttpFoundation\Response;

class ForbiddenException extends \Exception
{
    public const string ACCESS_DENIED = 'Access denied.';
    public const int CODE = Response::HTTP_FORBIDDEN;

    public function __construct(
        string $message = self::ACCESS_DENIED,
        int $code = self::CODE,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
