<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Bus\Serializer;

use App\Shared\Application\Message\ExternalMessageInterface;
use App\Shared\Application\Message\UndecodifiableMessage;
use App\Shared\Domain\Exception\InvalidArgumentException;
use App\Shared\Infrastructure\Exception\InvalidMessageException;
use App\Shared\Infrastructure\Symfony\Bus\Factory\DefaultMessageFactory;
use App\Shared\Infrastructure\Symfony\Bus\Stamp\OriginalPayloadStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

abstract class ExternalMessageSerializer implements SerializerInterface
{
    private const string BUS_NAME = 'external_message.bus';

    public function __construct(protected readonly DefaultMessageFactory $messageFactory)
    {
    }

    abstract protected function getMessageKey(): string;

    abstract protected function getMessageClass(): string;

    /**
     * @param array<string, mixed> $encodedEnvelope
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        try {
            if (!isset($encodedEnvelope['body']) || !is_string($encodedEnvelope['body'])) {
                throw InvalidMessageException::missingBody();
            }
            /** @var array<string, mixed> $body */
            $body = json_decode($encodedEnvelope['body'], true, 512, JSON_THROW_ON_ERROR);

            $message = $this->tryBuildMessage($body);

            return new Envelope($message, [
                new BusNameStamp(self::BUS_NAME),
                new OriginalPayloadStamp($encodedEnvelope['body']),
            ]);
        } catch (\Throwable $throwable) {
            $stamps = [ErrorDetailsStamp::create($throwable)];
            if (isset($encodedEnvelope['body']) && is_string($encodedEnvelope['body'])) {
                $stamps[] = new OriginalPayloadStamp($encodedEnvelope['body']);
            }

            return new Envelope(
                UndecodifiableMessage::create($encodedEnvelope),
                $stamps
            );
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \JsonException
     * @throws InvalidMessageException
     */
    public function encode(Envelope $envelope): array
    {
        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);
        $message = $envelope->getMessage();
        $expectedClass = $this->getMessageClass();

        if ($message instanceof ExternalMessageInterface && $message instanceof $expectedClass) {
            return [
                'body' => json_encode($this->serializeMessage($message), JSON_THROW_ON_ERROR),
                'headers' => [],
            ];
        }

        throw InvalidMessageException::invalidSerializer();
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws InvalidMessageException
     * @throws InvalidArgumentException
     * @throws \InvalidArgumentException
     */
    protected function tryBuildMessage(array $body): ExternalMessageInterface
    {
        $messageKey = $this->getMessageKey();
        $messageData = !empty($body[$messageKey]) ? (array) $body[$messageKey] : [];
        $messageName = $messageData['name'] ?? '';
        $data = !empty($body['data']) ? (array) $body['data'] : [];
        $metadata = !empty($body['metadata']) ? (array) $body['metadata'] : [];

        // Ensure all arrays are array<string, mixed>
        $messageData = self::filterStringKeys($messageData);
        $data = self::filterStringKeys($data);
        $metadata = self::filterStringKeys($metadata);

        if (!is_string($messageName) || empty($messageName)) {
            throw InvalidMessageException::missingMessageName();
        }

        return $this->messageFactory->create(
            messageName: $messageName,
            payloadData: $data,
            messageData: $messageData,
            metadata: $metadata
        );
    }

    /**
     * @param array<mixed,mixed> $arr
     *
     * @return array<string, mixed>
     */
    private static function filterStringKeys(array $arr): array
    {
        return array_filter(
            $arr,
            fn ($v, $k) => is_string($k),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeMessage(ExternalMessageInterface $message): array
    {
        $messageKey = $this->getMessageKey();

        return [
            'data' => $message->data(),
            $messageKey => $message->{$messageKey}(),
            'metadata' => $message->metadata(),
        ];
    }
}
