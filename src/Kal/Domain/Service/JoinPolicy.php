<?php

declare(strict_types=1);

namespace App\Kal\Domain\Service;

use App\Kal\Domain\Exception\KalAlreadyMemberException;
use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalStateException;
use App\Kal\Domain\Kal;
use App\Kal\Domain\Repository\ParticipationRepositoryInterface;
use App\Shared\Domain\ValueObject\UlidValue;

/**
 * Qui ja és membre no es pot tornar a apuntar. «Membre» té dues formes que
 * l'agregat no pot resoldre sol: l'organitzadora ho és pel seu rol (viu al
 * `Kal`) i la resta ho són per una participació (viu en una altra taula). Com
 * que la regla travessa les dues, no cap dins de `Kal` ni de `Participation`.
 */
final readonly class JoinPolicy
{
    public function __construct(
        private ParticipationRepositoryInterface $participations,
    ) {
    }

    /**
     * @throws KalAlreadyMemberException
     * @throws KalException
     * @throws KalStateException
     */
    public function ensureCanJoin(Kal $kal, UlidValue $userId): void
    {
        if ($kal->organizerId->equals($userId)) {
            throw KalAlreadyMemberException::create();
        }

        if ($this->participations->exists($kal->id, $userId)) {
            throw KalAlreadyMemberException::create();
        }
    }
}
