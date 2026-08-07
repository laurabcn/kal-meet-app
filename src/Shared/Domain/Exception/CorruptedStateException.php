<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * L'estat persistit no es pot fer servir: falta una fila que un invariant
 * garanteix, o el servidor no ha pogut fer una feina seva. La petició era
 * correcta — la culpa és nostra.
 *
 * El mapper el tradueix a 500, i això no és cosmètica: `ApiExceptionSubscriber`
 * només escriu al log i avisa per Slack a partir de 500, o sigui que el tipus
 * és el que decideix si te n'assabentes. I un 4xx li diria al client que
 * revisi el que ha enviat, quan el que ha de fer és reintentar.
 */
class CorruptedStateException extends DomainException
{
}
