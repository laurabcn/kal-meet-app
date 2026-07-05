<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Bus;

use App\Shared\Application\Query\QueryBusInterface;
use App\Shared\Application\Query\QueryInterface;
use App\Shared\Application\Query\ResponseInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class SymfonyQueryBus implements QueryBusInterface
{
    public function __construct(private MessageBusInterface $queryBus)
    {
    }

    /**
     * @throws ExceptionInterface
     * @throws \Throwable
     */
    public function ask(QueryInterface $query): ResponseInterface
    {
        try {
            $envelope = $this->queryBus->dispatch($query);

            /** @var HandledStamp|null $handledStamp */
            $handledStamp = $envelope->last(HandledStamp::class);

            $result = $handledStamp?->getResult();

            if (!$result instanceof ResponseInterface) {
                throw new HandlerFailedException($envelope, [new \RuntimeException('Query not handled')]);
            }

            return $result;
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?: $exception;
        }
    }
}
