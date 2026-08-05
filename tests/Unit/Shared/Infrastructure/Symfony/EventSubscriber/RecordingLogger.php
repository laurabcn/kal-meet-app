<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\EventSubscriber;

use Psr\Log\AbstractLogger;

/**
 * Guarda el nivell, el missatge i **el context** de cada línia. El context és
 * el que interessa: que el log surti buit és un error que aquest projecte ja ha
 * comès una vegada (CLAUDE.md, «Errors ja comesos»), i no peta ni es nota.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /** @return list<array{level: string, message: string, context: array<string, mixed>}> */
    public function ofLevel(string $level): array
    {
        return array_values(array_filter($this->records, static fn (array $r): bool => $r['level'] === $level));
    }
}
