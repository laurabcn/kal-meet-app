<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Repository;

interface MessageStoreRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function saveCommand(string $messageId, string $messageName, ?string $streamId, array $data): void;

    /** @param array<string, mixed> $data */
    public function saveEvent(string $messageId, string $messageName, string $streamId, array $data): void;

    public function saveProcessedCommand(
        string $messageId,
        string $messageName,
        ?string $streamId,
        string $bundleContext,
        string $occurredAt,
    ): void;

    public function saveProcessedEvent(
        string $messageId,
        string $messageName,
        string $streamId,
        string $bundleContext,
        string $occurredAt,
    ): void;
}
