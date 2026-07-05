<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class UndecodifiableMessageHandler implements ExternalMessageHandlerInterface
{
    /**
     * @throws UnrecoverableMessageHandlingException
     */
    public function __invoke(UndecodifiableMessage $message): void
    {
        throw new UnrecoverableMessageHandlingException(
            'Message could not be decoded and will be sent to DLQ.'
        );
    }
}
