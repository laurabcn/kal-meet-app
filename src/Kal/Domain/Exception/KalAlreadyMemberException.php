<?php

declare(strict_types=1);

namespace App\Kal\Domain\Exception;

use App\Shared\Domain\Exception\ConflictException;

final class KalAlreadyMemberException extends ConflictException
{
    public static function create(): self
    {
        return new self('kal_already_member', 'User is already a member of this kal.');
    }
}
