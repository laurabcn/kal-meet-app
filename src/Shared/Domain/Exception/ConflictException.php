<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/** Conflicte d'estat (p.ex. id ja existent). El mapper el tradueix a 409. */
class ConflictException extends DomainException
{
}
