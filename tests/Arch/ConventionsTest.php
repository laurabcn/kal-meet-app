<?php

declare(strict_types=1);

// Template: the two "App\Domain" checks pass vacuously until you add
// classes there — expected for a fresh challenge. The controller checks
// below are real and pass today against src/Controller/HealthController.php,
// proving the arch suite actually works out of the box.

arch('domain and application code use strict types')
    ->expect(['App\Domain', 'App\Application'])
    ->toUseStrictTypes();

arch('domain classes are final')
    ->expect('App\Domain')
    ->classes()
    ->toBeFinal();

arch('controllers are final and invokable single-action controllers')
    ->expect('App\Controller')
    ->classes()
    ->toBeFinal()
    ->toBeInvokable();

arch('no debugging leftovers anywhere in the app')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r']);

// Real guardrails for the Kal bounded context (App\Kal\...): these mirror the
// generic template rules above, scoped to the actual namespaces so they
// enforce conventions as Kal\Domain/Application classes are built out.

arch('kal domain and application code use strict types')
    ->expect(['App\Kal\Domain', 'App\Kal\Application'])
    ->toUseStrictTypes();

arch('kal domain classes are final')
    ->expect('App\Kal\Domain')
    ->classes()
    ->toBeFinal();
