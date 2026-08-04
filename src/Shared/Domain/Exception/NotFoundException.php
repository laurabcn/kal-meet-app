<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/** Recurs inexistent (o no visible per a qui demana). El mapper el tradueix a 404. */
class NotFoundException extends DomainException
{
}
