<?php

declare(strict_types=1);

use App\Kal\Domain\Exception\KalException;
use App\Kal\Domain\Exception\KalNotFoundException;
use App\Shared\Infrastructure\Symfony\EventSubscriber\ApiExceptionMapper;
use App\Shared\Infrastructure\Symfony\EventSubscriber\ApiExceptionSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Tests\Unit\Shared\Infrastructure\Symfony\EventSubscriber\RecordingLogger;
use Tests\Unit\Shared\Infrastructure\Symfony\EventSubscriber\StubChatter;

// El mapatge excepció → status/codi el prova `ApiExceptionMapperTest`. Aquí es
// prova el que el subscriber hi afegeix i que només passa quan alguna cosa ja
// ha petat: el log dels 5xx, l'avís a Slack, i el desembolcallat de les
// excepcions que Messenger amaga dins d'una `HandlerFailedException`.

beforeEach(function (): void {
    $this->logger = new RecordingLogger();
    $this->chatter = new StubChatter();
    $this->subscriber = new ApiExceptionSubscriber(new ApiExceptionMapper(), $this->logger, $this->chatter);

    $this->dispatch = function (Throwable $exception, string $route = 'kal_get'): ExceptionEvent {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $request->setMethod('GET');

        $event = new ExceptionEvent(
            new class implements HttpKernelInterface {
                public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
                {
                    return new Response();
                }
            },
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        $this->subscriber->onKernelException($event);

        return $event;
    };
});

it('answers the mapped error body for a domain exception', function (): void {
    $event = ($this->dispatch)(KalNotFoundException::create());

    expect($event->getResponse()?->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($event->getResponse()?->getContent())
        ->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

// Messenger embolcalla tot el que llancen els handlers. Si el desembolcallat
// regressés, CADA excepció de domini llançada dins d'un handler passaria a ser
// un 500 en comptes del seu status: tot el contracte d'errors depèn d'aquí.
it('unwraps the exception Messenger hides inside HandlerFailedException', function (): void {
    $wrapped = new HandlerFailedException(new Envelope(new stdClass()), [KalNotFoundException::create()]);

    $event = ($this->dispatch)($wrapped);

    expect($event->getResponse()?->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($event->getResponse()?->getContent())
        ->toBe('{"error":"Kal not found.","code":"kal_not_found"}');
});

// Els 404/405 de routing ja porten el seu status: reescriure'ls els trencaria.
it('leaves framework http exceptions to symfony', function (): void {
    $event = ($this->dispatch)(new NotFoundHttpException('No route found'));

    expect($event->getResponse())->toBeNull();
});

// Res no s'escapa cap a una traça de Symfony: el que no sap mapar acaba igualment
// al contracte `{"error","code"}`, i queda registrat perquè és un 500.
it('turns an unknown throwable into internal_error and logs it', function (): void {
    $event = ($this->dispatch)(new RuntimeException('not ours'));

    expect($event->getResponse()?->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($event->getResponse()?->getContent())
        ->toBe('{"error":"An internal error occurred.","code":"internal_error"}')
        ->and($this->logger->ofLevel('error'))->toHaveCount(1);
});

it('does not log a 4xx', function (): void {
    ($this->dispatch)(KalNotFoundException::create());

    expect($this->logger->records)->toBeEmpty()
        ->and($this->chatter->sent)->toBeEmpty();
});

// Que el log surti amb el context complet és la meitat de la seva utilitat: un
// `logger->error` sense `route` ni `code` no serveix per diagnosticar res, i
// perdre'l no peta ni es nota (CLAUDE.md, «Errors ja comesos»).
it('logs a 5xx with the full structured context', function (): void {
    ($this->dispatch)(KalException::persistenceFailed(new RuntimeException('db down')), 'kal_create');

    $errors = $this->logger->ofLevel('error');

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['message'])->toBe('Unhandled API exception')
        ->and($errors[0]['context']['code'])->toBe('kal_persistence_failed')
        ->and($errors[0]['context']['http_status'])->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($errors[0]['context']['route'])->toBe('kal_create')
        ->and($errors[0]['context']['method'])->toBe('GET')
        ->and($errors[0]['context']['exception'])->toBeInstanceOf(KalException::class);
});

it('alerts slack on a 5xx with the status, code and route', function (): void {
    ($this->dispatch)(KalException::persistenceFailed(new RuntimeException('db down')), 'kal_create');

    expect($this->chatter->sent)->toHaveCount(1)
        ->and($this->chatter->sent[0])->toContain('500')
        ->and($this->chatter->sent[0])->toContain('kal_persistence_failed')
        ->and($this->chatter->sent[0])->toContain('kal_create');
});

// El cas que el `try/catch` del subscriber existeix per evitar, i que fins ara
// res no subjectava: si Slack cau, la petició ha de seguir responent el seu 500
// en comptes de rebentar amb l'error del notificador.
it('still answers the 500 when slack is down', function (): void {
    $this->chatter->failWith(new RuntimeException('slack unreachable'));

    $event = ($this->dispatch)(KalException::persistenceFailed(new RuntimeException('db down')));

    expect($event->getResponse()?->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($event->getResponse()?->getContent())
        ->toBe('{"error":"Failed to persist the kal.","code":"kal_persistence_failed"}');
});

it('records the slack failure as a warning, without losing the original error', function (): void {
    $this->chatter->failWith(new RuntimeException('slack unreachable'));

    ($this->dispatch)(KalException::persistenceFailed(new RuntimeException('db down')));

    $warnings = $this->logger->ofLevel('warning');

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]['context']['error'])->toBe('slack unreachable')
        ->and($warnings[0]['context']['exception_error'])->toBe('kal_persistence_failed')
        // L'error original s'ha de seguir registrant: la caiguda de Slack no
        // el pot substituir.
        ->and($this->logger->ofLevel('error'))->toHaveCount(1);
});
