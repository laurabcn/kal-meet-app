<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\EventSubscriber;

use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;

/**
 * Recull els missatges enviats i, amb `failWith()`, simula un Slack caigut —
 * que és l'escenari que fa falta per provar que una avaria del monitoratge no
 * s'emporta la resposta HTTP.
 */
final class StubChatter implements ChatterInterface
{
    /** @var list<string> */
    public array $sent = [];

    private ?\Throwable $failure = null;

    /** `\Throwable` i no `TransportException`: el subscriber captura qualsevol cosa. */
    public function failWith(\Throwable $failure): void
    {
        $this->failure = $failure;
    }

    public function send(MessageInterface $message): ?SentMessage
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $this->sent[] = $message->getSubject();

        return null;
    }

    public function supports(MessageInterface $message): bool
    {
        return true;
    }

    public function __toString(): string
    {
        return 'stub://chatter';
    }
}
