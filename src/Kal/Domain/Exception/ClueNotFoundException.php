<?php

declare(strict_types=1);

namespace App\Kal\Domain\Exception;

use App\Shared\Domain\Exception\NotFoundException;

final class ClueNotFoundException extends NotFoundException
{
    public static function create(): self
    {
        return new self('clue_not_found', 'Clue not found.');
    }
}
