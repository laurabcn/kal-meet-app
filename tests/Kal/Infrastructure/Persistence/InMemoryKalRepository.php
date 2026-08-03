<?php

declare(strict_types=1);

namespace Tests\Kal\Infrastructure\Persistence;

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Kal;
use App\Kal\Domain\KalRepositoryInterface;

/**
 * Doble del port per als tests que van d'una altra cosa (el handler, el
 * controller, el firewall): res d'això necessita Postgres.
 *
 * Dues regles que el mantenen honest:
 *
 * 1. **Ha de saber fer tot el que el port declara, incloent-hi fallar.**
 *    `create()` declara `@throws KalException` i un doble que no pugui llençar
 *    res deixa el camí de fallada de persistència invisible a tot arreu.
 *    D'aquí `failWith()`.
 * 2. **No asserim aquí res que en producció visqui al SQL.** L'aula de debat,
 *    per exemple, la crea `DbalKalRepository::insertDebateRoom()`; un comptador
 *    aquí només comptaria crides a `create()` i donaria un test verd que no pot
 *    fallar. Aquella invariant necessita un test contra BD de veritat, que
 *    encara no existeix.
 */
final class InMemoryKalRepository implements KalRepositoryInterface
{
    /** @var Kal[] */
    private array $kals = [];

    private ?KalException $failure = null;

    /** La propera escriptura peta, com quan cau la BD a mig `create()`. */
    public function failWith(KalException $failure): void
    {
        $this->failure = $failure;
    }

    /** @throws KalException */
    public function create(Kal $kal): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $id = $kal->id->value();
        if (isset($this->kals[$id])) {
            throw KalException::alreadyExists();
        }

        $this->kals[$id] = $kal;
    }

    /** @return Kal[] */
    public function all(): array
    {
        return array_values($this->kals);
    }
}
