<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Bus\Serializer;

use App\Shared\Infrastructure\Symfony\Bus\Stamp\OriginalPayloadStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * When encoding, uses OriginalPayloadStamp if present to preserve the exact payload.
 * Falls back to the wrapped serializer otherwise.
 */
final class PreserveOriginalPayloadSerializer implements SerializerInterface
{
    public function __construct(
        private readonly SerializerInterface $fallbackSerializer,
    ) {
    }

    /**
     * @param array{body: string, headers?: array<string, string>} $encodedEnvelope
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        return $this->fallbackSerializer->decode($encodedEnvelope);
    }

    /**
     * @return array{body: string, headers: array<string, string>}
     */
    public function encode(Envelope $envelope): array
    {
        $stamp = $envelope->last(OriginalPayloadStamp::class);

        if ($stamp instanceof OriginalPayloadStamp) {
            return [
                'body' => $stamp->getBody(),
                'headers' => [],
            ];
        }

        /** @var array{body: string, headers: array<string, string>} $encoded */
        $encoded = $this->fallbackSerializer->encode($envelope);

        return $encoded;
    }
}
