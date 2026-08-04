<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\EventSubscriber;

final readonly class MappedHttpError
{
    public function __construct(
        public int $statusCode,
        public string $errorCode,
        public string $message,
    ) {
    }
}
