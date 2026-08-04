<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\EventSubscriber;

use App\Shared\Infrastructure\Symfony\Http\Response\ApiHttpErrorResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Tradueix excepcions no gestionades a `ApiHttpErrorResponse` i, als 5xx,
 * emet log estructurat (+ Slack via Notifier/Chatter quan `SLACK_DSN` ho permet).
 */
final readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ApiExceptionMapper $mapper,
        private LoggerInterface $logger,
        private ChatterInterface $chatter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = self::unwrap($event->getThrowable());
        $mapped = $this->mapper->map($exception);

        if (null === $mapped) {
            return;
        }

        $event->setResponse(new ApiHttpErrorResponse(
            $mapped->message,
            $mapped->errorCode,
            $mapped->statusCode,
        ));

        if ($mapped->statusCode < 500) {
            return;
        }

        $request = $event->getRequest();
        $context = [
            'exception' => $exception,
            'error' => $mapped->message,
            'code' => $mapped->errorCode,
            'http_status' => $mapped->statusCode,
            'route' => $request->attributes->get('_route'),
            'method' => $request->getMethod(),
        ];

        $this->logger->error('Unhandled API exception', $context);
        $this->notifySlack($mapped, $request->getMethod(), $request->attributes->get('_route'));
    }

    private function notifySlack(MappedHttpError $mapped, string $method, mixed $route): void
    {
        $routeLabel = \is_string($route) && '' !== $route ? $route : '-';
        $text = sprintf(
            '*[KAL][%d]* `%s` · `%s` · route=%s',
            $mapped->statusCode,
            $mapped->errorCode,
            $method,
            $routeLabel,
        );

        try {
            $this->chatter->send(new ChatMessage($text));
        } catch (\Throwable $e) {
            // Mai fer fallar la resposta HTTP perquè Slack no respongui.
            $this->logger->warning('Failed to send Slack alert for API exception', [
                'error' => $e->getMessage(),
                'http_status' => $mapped->statusCode,
                'exception_error' => $mapped->errorCode,
            ]);
        }
    }

    private static function unwrap(\Throwable $exception): \Throwable
    {
        if ($exception instanceof HandlerFailedException && null !== $exception->getPrevious()) {
            return $exception->getPrevious();
        }

        return $exception;
    }
}
