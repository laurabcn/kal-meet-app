<?php

declare(strict_types=1);

namespace App\Kal\Domain\Exception;

use App\Shared\Domain\Exception\CorruptedStateException;

/**
 * Els errors del context Kal que NO són culpa de qui fa la petició: files que
 * un invariant garanteix i no hi són, o feina del servidor que ha fallat.
 *
 * Viuen a part de `KalException` perquè el status el decideix el TIPUS, no el
 * text del codi d'error (CLAUDE.md). Tot el que és aquí surt com a 500 i queda
 * registrat; el que és a `KalException` és un 400 que l'organitzadora pot
 * arreglar canviant el que envia.
 */
final class KalStateException extends CorruptedStateException
{
    /** Zero o més d'una aula trenquen l'invariant MVP (índex únic + CreateKal). */
    public static function missingDebateRoom(): self
    {
        return new self(
            'kal_debate_room_missing',
            'The kal does not have exactly one debate room.',
        );
    }

    /** Una pista persistida sense reunió trencaria la invariant de «reunió obligatòria». */
    public static function missingClueMeeting(): self
    {
        return new self('kal_clue_meeting_missing', 'A clue is missing its required meeting.');
    }

    /** L'hidratador ha rebut una cosa que no és un Kal: error de programació, no d'entrada. */
    public static function invalidKal(): self
    {
        return new self('kal_invalid', 'The value is not a valid kal.');
    }

    /** L'escriptura a Postgres ha fallat: connexió caiguda, FK violada, disc ple. */
    public static function persistenceFailed(\Throwable $cause): self
    {
        return new self('kal_persistence_failed', 'Failed to persist the kal.', 0, $cause);
    }

    /** `random_bytes()` no ha pogut llegir del generador del sistema. */
    public static function inviteTokenGenerationFailed(): self
    {
        return new self('kal_invite_token_generation_failed', 'Failed to generate an invite token.');
    }
}
