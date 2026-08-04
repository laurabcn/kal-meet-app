<?php

declare(strict_types=1);

// Un test que no és a cap `<testsuite>` no falla: simplement no s'executa mai.
// Ja va passar — `tests/User/` va viure un temps sencer sense estar a
// `phpunit.xml.dist` i els seus 24 tests no s'havien corregut ni un cop. Ara hi
// ha DUES configs (`phpunit.xml.dist` = Arch/Unit/Feature; `phpunit.db.xml.dist`
// = Integration contra Postgres), o sigui que és encara més fàcil que un fitxer
// caigui entremig.

/** @return list<string> Directoris coberts per alguna suite, relatius a l'arrel. */
function suiteDirectories(): array
{
    $root = \dirname(__DIR__, 2);
    $directories = [];

    foreach (['phpunit.xml.dist', 'phpunit.db.xml.dist'] as $config) {
        $xml = simplexml_load_file($root.'/'.$config);

        if (false === $xml) {
            throw new RuntimeException("No es pot llegir {$config}");
        }

        foreach ($xml->xpath('//testsuite/directory') ?: [] as $directory) {
            $directories[] = trim((string) $directory, '/');
        }
    }

    return $directories;
}

it('runs every test file: none is left out of both phpunit configs', function (): void {
    $root = \dirname(__DIR__, 2);
    $covered = suiteDirectories();

    expect($covered)->not->toBeEmpty();

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/tests', FilesystemIterator::SKIP_DOTS),
    );

    $orphans = [];

    foreach ($files as $file) {
        \assert($file instanceof SplFileInfo);

        if (!str_ends_with($file->getFilename(), 'Test.php')) {
            continue;
        }

        $relative = str_replace($root.'/', '', str_replace('\\', '/', $file->getPathname()));

        foreach ($covered as $directory) {
            if (str_starts_with($relative, $directory.'/')) {
                continue 2;
            }
        }

        $orphans[] = $relative;
    }

    expect($orphans)->toBeEmpty(
        'Aquests tests no són a cap testsuite i no s\'executen mai: '.implode(', ', $orphans),
    );
});
