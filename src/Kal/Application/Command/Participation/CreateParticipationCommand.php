<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Participation;

use App\Shared\Application\Command\CommandInterface;

final readonly class CreateParticipationCommand implements CommandInterface
{
    public function __construct(
        public string $kalId,
        public string $inviteToken,
        public string $userId,
    ) {
    }
}
