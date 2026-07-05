<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Bus\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Preserves the original raw payload when a message is decoded from an external transport.
 * Used when sending to failure/retry transports to avoid re-encoding format mismatches.
 */
final readonly class OriginalPayloadStamp implements StampInterface
{
    public function __construct(
        private string $body,
    ) {
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
