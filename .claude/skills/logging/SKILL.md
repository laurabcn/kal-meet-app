---
name: Logging
description: 'Use when emitting a log line in this backend. Trigger keywords: logger->info, logger->warning, logger->error, context array, structured logging, log context, info vs warning, PSR-3, LoggerInterface.'
---

ALWAYS prepend to your messages "following Logging skill..."

<purpose>
This project uses PSR-3 (`Psr\Log\LoggerInterface`) — no custom logger
wrapper. A class gets a logger via constructor injection
(`private LoggerInterface $logger`), autowired by
`config/services.yaml`; no monolog bundle is configured yet, so whatever
implementation the container resolves is whatever a given environment wires
up (a no-op/null logger until one is added). The failure modes this skill
guards against: interpolating context into the message string, passing
non-primitive values inside the context array, and catching-logging-rethrowing
the same exception.
</purpose>

<getting-a-logger>
```php
final readonly class SomeService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }
}
```

Constructor-injected, autowired — never instantiated manually, never a static/
global call. See `src/Shared/Infrastructure/Symfony/Middleware/MessageStoreMiddleware.php`
for the pattern already used in this repo.
</getting-a-logger>

<message-is-static-context-is-extra>
Every log line follows the same shape: a static, indexable message string
plus a context array (PSR-3's third parameter) carrying the dynamic data.

Avoid (interpolates context into the message):

```php
$this->logger->info("Order {$order->id()->value()} created by {$userId}");
```

Prefer (static message, structured payload) — this is the existing pattern in
`MessageStoreMiddleware`:

```php
$this->logger->error(
    'MessageStoreMiddleware: failed to persist produced message to store',
    [
        'message_id' => $message->id()->value(),
        'message_name' => $message::name()->value(),
        'context' => $contextKey,
        'error' => $e->getMessage(),
    ],
);
```

Why: if a formatter or log aggregator gets added later, context fields become
searchable/filterable attributes — "find every log line for
`message_id=...`" — regardless of message wording. An interpolated string is
just an opaque string to search through.
</message-is-static-context-is-extra>

<context-types>
Convert non-primitive values before putting them in the context array — value
objects and entities are not guaranteed to serialize the way you want if a
JSON formatter is added later. Pass `$order->id()->value()`, not
`$order->id()`; pass `$exception->getMessage()` (or the exception object
under an `'exception'` key, which Monolog-style handlers know to expand),
not the exception cast to string.
</context-types>

<severity-choice>
- `info`: a happy-path event worth tracing in production (an order placed, a
  reminder email sent). Use sparingly — every `info` is a permanent line.
- `warning`: a partial success or fallback path — the operation still
  completed, but a human may want to look later.
- `error`: an unexpected failure, typically at a boundary that turns it into
  an HTTP response or swallows it — pass the exception's message (or the
  exception itself) in the context array; PSR-3 has no separate "exception"
  method, `error`/`critical` carry that role.

`debug` is for local development only; don't rely on it being visible
anywhere else.
</severity-choice>

<level-per-error-branch>
At a boundary that converts a domain exception into an HTTP response (a
controller, an exception listener):

- An expected, mapped client error (a not-found → 404, a validation failure
  → 422) is NOT logged at all by default — the HTTP response is the signal.
  Add `$logger->warning(...)` only if an operator genuinely needs visibility
  into how often this happens.
- An unmapped exception that becomes a generic 500 should be logged at that
  boundary — that's the only place this specific failure gets recorded, since
  it's being turned into a response instead of propagating.
</level-per-error-branch>

<no-catch-log-and-rethrow>
Don't catch an exception only to log it and re-throw — if something upstream
also logs it (a controller's exception listener, a future global handler),
that's a duplicate entry for the same failure.

Avoid:

```php
try {
    $repository->save($order);
} catch (\Throwable $e) {
    $this->logger->error('Failed to save order', ['error' => $e->getMessage()]);
    throw $e;
}
```

Prefer: let it propagate, and log once at the boundary that actually turns it
into a response (or add context by wrapping it in a domain exception with
`throw new SomeDomainException(..., previous: $e)`, not by logging and
re-throwing the same one).
</no-catch-log-and-rethrow>

<volume-discipline>
A log line inside a tight loop is a cost/noise problem waiting to happen.
Log a single summary at the loop boundary instead of once per iteration:

```php
$this->logger->info('Reminder emails sent', ['order_id' => $orderId->value(), 'count' => count($recipients)]);
```
</volume-discipline>
