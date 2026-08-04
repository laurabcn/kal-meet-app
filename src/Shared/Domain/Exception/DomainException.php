<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

abstract class DomainException extends \DomainException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
