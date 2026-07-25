<?php

declare(strict_types=1);

namespace Tests\Kal\Domain\Mother;

use App\Kal\Domain\Clue;
use App\Kal\Domain\Clues;

final class CluesMother
{
    public static function empty(): Clues
    {
        return Clues::create();
    }

    public static function of(Clue ...$clues): Clues
    {
        return Clues::create(...$clues);
    }
}
