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

// Real guardrails for the Kal bounded context (App\Kal\...): these mirror the
// generic template rules above, scoped to the actual namespaces so they
// enforce layering as Kal\Domain/Application/Infrastructure are built out.

arch('kal domain does not depend on kal application')
    ->expect('App\Kal\Domain')
    ->not->toUse('App\Kal\Application');

arch('kal domain does not depend on kal infrastructure')
    ->expect('App\Kal\Domain')
    ->not->toUse('App\Kal\Infrastructure');

arch('kal domain does not depend on any framework')
    ->expect('App\Kal\Domain')
    ->not->toUse(['Symfony', 'Doctrine']);

arch('kal application does not depend on kal infrastructure')
    ->expect('App\Kal\Application')
    ->not->toUse('App\Kal\Infrastructure');

arch('nothing depends on kal infrastructure except kal infrastructure itself and the composition root')
    ->expect('App\Kal\Infrastructure')
    ->toOnlyBeUsedIn('App\Kal\Infrastructure')
    ->ignoring('App\Kernel');

// Real guardrails for the User bounded context (App\User\...). It ships without
// an Application layer on purpose (read path only, see
// docs/specs/supabase-jwt-authentication.md §4), so there is no vacuous
// App\User\Application rule here: add one the day a command or query exists.

arch('user domain does not depend on user infrastructure')
    ->expect('App\User\Domain')
    ->not->toUse('App\User\Infrastructure');

arch('user domain does not depend on any framework')
    ->expect('App\User\Domain')
    ->not->toUse(['Symfony', 'Doctrine']);

arch('nothing depends on user infrastructure except user infrastructure itself and the composition root')
    ->expect('App\User\Infrastructure')
    ->toOnlyBeUsedIn('App\User\Infrastructure')
    ->ignoring('App\Kernel');

// Bounded contexts talk through Shared, never to each other directly.

arch('the user context does not depend on the kal context')
    ->expect('App\User')
    ->not->toUse('App\Kal');

arch('the kal context does not depend on the user context')
    ->expect('App\Kal')
    ->not->toUse('App\User');
