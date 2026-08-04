<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use App\Shared\Domain\ValueObject\UlidValue;

class ResourceAlreadyExists extends ConflictException
{
    public function __construct(UlidValue $id, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            'resource_already_exists',
            sprintf('This resource already exists "%s"', $id->value()),
            $code,
            $previous,
        );
    }
}
