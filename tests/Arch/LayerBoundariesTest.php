<?php

declare(strict_types=1);

// Template: adjust the "App\..." prefixes below if you rename the root
// namespace in composer.json, or add bounded contexts (e.g.
// "App\<Context>\Domain"). These pass vacuously until src/Domain,
// src/Application and src/Infrastructure actually contain classes — that's
// expected; they become real guardrails as you build the domain out.

arch('domain does not depend on application')
    ->expect('App\Domain')
    ->not->toUse('App\Application');

arch('domain does not depend on infrastructure')
    ->expect('App\Domain')
    ->not->toUse('App\Infrastructure');

arch('domain does not depend on any framework')
    ->expect('App\Domain')
    ->not->toUse(['Symfony', 'Doctrine']);

arch('application does not depend on infrastructure')
    ->expect('App\Application')
    ->not->toUse('App\Infrastructure');

arch('nothing depends on infrastructure except infrastructure itself and the composition root')
    ->expect('App\Infrastructure')
    ->toOnlyBeUsedIn('App\Infrastructure')
    ->ignoring('App\Kernel');
