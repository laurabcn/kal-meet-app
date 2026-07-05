<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Bus\Serializer;

use App\Shared\Application\Message\EventMessageInterface;
use App\Shared\Application\Message\ExternalMessageInterface;
use App\Shared\Application\Message\UndecodifiableMessage;
use App\Shared\Application\Message\UnknownExternalMessage;
use App\Shared\Infrastructure\Symfony\Bus\Factory\DefaultMessageFactory;
use App\Shared\Infrastructure\Symfony\Bus\Stamp\OriginalPayloadStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

abstract class AbstractRetryMessageSerializer extends EventMessageSerializer
{
    private const string STAMPS = '__stamps';
    private const string BODY = 'body';
    private const string HEADERS = 'headers';
    private const string BUS_NAME = 'external_message.bus';

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct()
    {
        parent::__construct($this->getFactoryWithMappedMessages());
    }

    /**
     * @return array<class-string<ExternalMessageInterface>>
     */
    abstract protected function getMappedMessages(): array;

    /**
     * @throws \InvalidArgumentException
     */
    private function getFactoryWithMappedMessages(): DefaultMessageFactory
    {
        $messageFactory = new DefaultMessageFactory();
        $messageFactory->addMessagesToMap($this->getMappedMessages());

        return $messageFactory;
    }

    /**
     * @param array<string, mixed> $encodedEnvelope
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        try {
            $decodedEnvelope = parent::decode($encodedEnvelope);

            if ($decodedEnvelope->getMessage() instanceof UnknownExternalMessage) {
                return new Envelope(UndecodifiableMessage::create($encodedEnvelope));
            }

            $bodyString = $encodedEnvelope[self::BODY];
            if (!is_string($bodyString)) {
                return new Envelope(UndecodifiableMessage::create($encodedEnvelope));
            }

            /** @var array<string, mixed> $body */
            $body = json_decode($bodyString, true, 512, JSON_THROW_ON_ERROR);

            /** @var array<StampInterface> $stamps */
            $stamps = [new BusNameStamp(self::BUS_NAME), new OriginalPayloadStamp($bodyString)];
            if (isset($body[self::STAMPS]) && is_array($body[self::STAMPS])) {
                foreach ($body[self::STAMPS] as $stampInfo) {
                    if (is_string($stampInfo)) {
                        $deserializedStamp = unserialize($stampInfo);
                        if ($deserializedStamp instanceof StampInterface) {
                            $stamps[] = $deserializedStamp;
                        }
                    }
                }
            }

            return new Envelope($decodedEnvelope->getMessage(), $stamps);
        } catch (\Throwable) {
            return new Envelope(UndecodifiableMessage::create($encodedEnvelope));
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     * @throws \JsonException
     */
    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();

        if (!$message instanceof EventMessageInterface) {
            throw new \InvalidArgumentException('Message must implement EventMessageInterface');
        }

        $mappedMessages = $this->getMappedMessages();
        if (!in_array($message::class, $mappedMessages, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Message type %s is not supported by this serializer. Supported: %s',
                $message::class,
                implode(', ', $mappedMessages)
            ));
        }

        $bodyContent = $this->serializeMessage($message);
        $bodyContent = $this->addStampsToBody($envelope, $bodyContent);

        return [
            self::BODY => json_encode($bodyContent, JSON_THROW_ON_ERROR),
            self::HEADERS => [],
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function addStampsToBody(Envelope $envelope, array $body): array
    {
        $stamps = $this->getStamps($envelope);
        if (count($stamps) > 0) {
            $body[self::STAMPS] = $stamps;
        }

        return $body;
    }

    /**
     * @return array<int, string>
     */
    private function getStamps(Envelope $envelope): array
    {
        $envelope = $envelope
            ->withoutStampsOfType(NonSendableStampInterface::class)
            ->withoutStampsOfType(ErrorDetailsStamp::class);

        $serializedStamps = [];
        foreach ($envelope->all() as $stamps) {
            foreach ($stamps as $stamp) {
                try {
                    $serializedStamps[] = serialize($stamp);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $serializedStamps;
    }
}
