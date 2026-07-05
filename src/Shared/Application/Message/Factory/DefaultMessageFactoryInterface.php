<?php

declare(strict_types=1);

namespace App\Shared\Application\Message\Factory;

use App\Shared\Application\Message\ExternalMessageInterface;

interface DefaultMessageFactoryInterface
{
    /**
     * @param array<string, mixed> $payloadData
     * @param array<string, mixed> $messageData
     * @param array<string, mixed> $metadata
     */
    public function create(
        string $messageName,
        array $payloadData,
        array $messageData,
        array $metadata,
    ): ExternalMessageInterface;

    /**
     * @param array<class-string> $messageClassNames
     */
    public function addMessagesToMap(array $messageClassNames): void;
}
