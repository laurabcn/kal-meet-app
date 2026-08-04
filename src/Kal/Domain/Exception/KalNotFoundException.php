<?php

declare(strict_types=1);

namespace App\Kal\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundException;

final class KalNotFoundException extends NotFoundException
{
    public static function create(): self
    {
        return new self('kal_not_found', 'Kal not found.');
    }
}
