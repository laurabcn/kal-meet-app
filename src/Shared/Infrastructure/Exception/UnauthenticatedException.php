<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Exception;

use Symfony\Component\HttpFoundation\Response;

class UnauthenticatedException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message, Response::HTTP_UNAUTHORIZED);
    }

    public static function invalidUserAuthToken(): self
    {
        return new self('Invalid user auth token');
    }

    public static function unsupportedUserAuthToken(): self
    {
        return new self('Unsupported user auth token');
    }

    public static function invalidApiSecret(): self
    {
        return new self('You are not authorized to access this resource');
    }
}
