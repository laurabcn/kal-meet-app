<?php

declare(strict_types=1);

namespace App\Kal\Application\Command\Participation;

use App\Kal\Domain\Exception\KalAlreadyMemberException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\InviteToken;
use App\Kal\Domain\Participation;
use App\Kal\Domain\Repository\KalRepositoryInterface;
use App\Kal\Domain\Repository\ParticipationRepositoryInterface;
use App\Kal\Domain\Service\JoinPolicy;
use App\Shared\Application\Command\CommandHandlerInterface;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Domain\ValueObject\UlidValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateParticipationCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private KalRepositoryInterface $kalRepository,
        private ParticipationRepositoryInterface $participationRepository,
        private JoinPolicy $joinPolicy,
    ) {
    }

    /**
     * @throws KalAlreadyMemberException
     * @throws KalNotFoundException
     * @throws KalException
     * @throws KalStateException
     * @throws InvalidArgumentException
     */
    public function __invoke(CreateParticipationCommand $command): void
    {
        $kalId = UlidValue::create($command->kalId);
        $userId = UlidValue::create($command->userId);
        $inviteToken = InviteToken::fromString($command->inviteToken);

        $kal = $this->kalRepository->findByToken($kalId, $inviteToken);

        $this->joinPolicy->ensureCanJoin($kal, $userId);

        $this->participationRepository->create(
            Participation::create(UlidValue::generate(), $kalId, $userId),
        );
    }
}
