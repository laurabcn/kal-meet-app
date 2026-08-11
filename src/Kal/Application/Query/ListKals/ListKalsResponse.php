<?php

declare(strict_types=1);

namespace App\Kal\Application\Query\ListKals;

use App\Kal\Domain\KalSummary;
use App\Shared\Application\Query\ResponseInterface;

final readonly class ListKalsResponse implements ResponseInterface
{
    /** @param list<KalSummary> $kals */
    public function __construct(private array $kals)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function result(): array
    {
        return array_map(self::summary(...), $this->kals);
    }

    /**
     * Els mateixos noms que `GetKalResponse`, per no obligar el client a
     * mapar dues formes del mateix KAL. Sense `inviteToken`: compartir
     * l'enllaç és cosa de la pantalla de detall, i com menys viatgi, millor.
     *
     * @return array<string, mixed>
     */
    private static function summary(KalSummary $kal): array
    {
        return [
            'id' => $kal->id->value(),
            'name' => $kal->name->value(),
            'description' => $kal->description?->value(),
            'startsOn' => $kal->startsOn->value(),
            'endsOn' => $kal->endsOn?->value(),
            'coverPath' => $kal->coverPath,
        ];
    }
}
