<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Middleware;

use App\Shared\Application\Message\StorableCommandInterface;
use App\Shared\Application\Message\StorableEventInterface;
use App\Shared\Application\Message\StorableMessageInterface;
use App\Shared\Infrastructure\Repository\MessageStoreRepositoryInterface;
use App\Shared\Infrastructure\Repository\MessageStoreRepositoryMapper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final readonly class MessageStoreMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MessageStoreRepositoryMapper $repositoryMapper,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if (!$message instanceof StorableMessageInterface) {
            return $stack->next()->handle($envelope, $stack);
        }

        $receivedStamp = $envelope->last(ReceivedStamp::class);
        $isConsuming = null !== $receivedStamp;
        $contextKey = $isConsuming
            ? $receivedStamp->getTransportName()
            : $message::boundedContext()->value();

        $repository = $this->repositoryMapper->find($contextKey);
        if (null === $repository) {
            return $stack->next()->handle($envelope, $stack);
        }

        if ($isConsuming) {
            $result = $stack->next()->handle($envelope, $stack);

            // (it will also cover handlers that early-return without processing).
            if (!empty($result->all(HandledStamp::class))) {
                $this->persistConsuming($repository, $message, $contextKey);
            }

            return $result;
        }

        $this->persistProducing($repository, $message, $contextKey);

        return $stack->next()->handle($envelope, $stack);
    }

    private function persistProducing(
        MessageStoreRepositoryInterface $repository,
        StorableMessageInterface $message,
        string $contextKey,
    ): void {
        try {
            match (true) {
                $message instanceof StorableCommandInterface => $repository->saveCommand(
                    $message->id()->value(),
                    $message::name()->value(),
                    $message->streamId()?->value(),
                    ['command' => $message->command(), 'data' => $message->data(), 'metadata' => $message->metadata()],
                ),
                $message instanceof StorableEventInterface => $repository->saveEvent(
                    $message->id()->value(),
                    $message::name()->value(),
                    $message->streamId()->value(),
                    ['event' => $message->event(), 'data' => $message->data(), 'metadata' => $message->metadata()],
                ),
                default => null,
            };
        } catch (\Throwable $e) {
            $this->logger->error(
                'MessageStoreMiddleware: failed to persist produced message to store',
                [
                    'message_id' => $message->id()->value(),
                    'message_name' => $message::name()->value(),
                    'context' => $contextKey,
                    'error' => $e->getMessage(),
                ],
            );
        }
    }

    private function persistConsuming(
        MessageStoreRepositoryInterface $repository,
        StorableMessageInterface $message,
        string $contextKey,
    ): void {
        try {
            $occurredAt = $message->id()->toDateTime()->value();
            $bundleContext = $message::boundedContext()->value();

            match (true) {
                $message instanceof StorableCommandInterface => $repository->saveProcessedCommand(
                    $message->id()->value(),
                    $message::name()->value(),
                    $message->streamId()?->value(),
                    $bundleContext,
                    $occurredAt,
                ),
                $message instanceof StorableEventInterface => $repository->saveProcessedEvent(
                    $message->id()->value(),
                    $message::name()->value(),
                    $message->streamId()->value(),
                    $bundleContext,
                    $occurredAt,
                ),
                default => null,
            };
        } catch (\Throwable $e) {
            $this->logger->error(
                'MessageStoreMiddleware: failed to persist consumed message to processed store',
                [
                    'message_id' => $message->id()->value(),
                    'message_name' => $message::name()->value(),
                    'context' => $contextKey,
                    'error' => $e->getMessage(),
                ],
            );
        }
    }
}
