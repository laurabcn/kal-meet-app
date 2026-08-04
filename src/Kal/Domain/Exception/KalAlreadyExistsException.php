<?php

declare(strict_types=1);

namespace App\Kal\Domain\Exception;

use App\Shared\Domain\Exception\ConflictException;

final class KalAlreadyExistsException extends ConflictException
{
    public static function create(): self
    {
        return new self('kal_already_exists', 'A kal with this id already exists.');
    }
}
