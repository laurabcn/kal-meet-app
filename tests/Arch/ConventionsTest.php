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

// Un controller sense `#[AsController]` es registra igualment com a servei (el
// glob d'App\ l'agafa) però es queda sense `controller.service_arguments`, i
// això no peta: només deixen de resoldre's els arguments d'acció
// (#[CurrentUser], #[MapRequestPayload]...). Sense aquesta regla la convenció
// seria un acord de paraula amb una fallada silenciosa a sota.
//
// Es fa escanejant el disc i no amb `expect('App\Kal\UI\Http')` a propòsit: una
// regla per namespace només cobreix els contexts que ja existeixen, i el dia
// que algú creï `src/Pattern/UI/Http/` la convenció deixaria de valer-hi sense
// que res ho digués. El que ha de valer és "tot el que sigui un controller".
it('requires the AsController attribute on every controller, including contexts that do not exist yet', function (): void {
    $root = \dirname(__DIR__, 2).'/src';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    $controllers = [];

    foreach ($files as $file) {
        \assert($file instanceof SplFileInfo);
        $path = str_replace('\\', '/', $file->getPathname());

        if ('php' !== $file->getExtension() || !preg_match('#/(UI/Http|src/Controller)/#', $path)) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        if (1 === preg_match('/^namespace\s+([^;]+);/m', $source, $namespace)) {
            $controllers[] = trim($namespace[1]).'\\'.$file->getBasename('.php');
        }
    }

    // Si el glob deixés de trobar res, la resta passaria en va.
    expect($controllers)->not->toBeEmpty();

    foreach ($controllers as $controller) {
        expect((new ReflectionClass($controller))->getAttributes(Symfony\Component\HttpKernel\Attribute\AsController::class))
            ->not->toBeEmpty("{$controller} no porta #[AsController]: es registrarà sense el tag controller.service_arguments.");
    }
});

// Real guardrails for the User bounded context (App\User\...).

arch('user domain code uses strict types')
    ->expect('App\User\Domain')
    ->toUseStrictTypes();

arch('user domain classes are final')
    ->expect('App\User\Domain')
    ->classes()
    ->toBeFinal();

arch('user repositories implement the domain port')
    ->expect('App\User\Infrastructure\Persistence')
    ->classes()
    ->toImplement('App\User\Domain\UserRepositoryInterface')
    ->ignoring('App\User\Infrastructure\Persistence\Hydrator');
