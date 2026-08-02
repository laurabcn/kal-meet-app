<?php

declare(strict_types=1);

// Els `%env(...)%` de config/ només es resolen si algú ha carregat .env.test.
// Cap test ho notava perquè cap servei els llegia; el primer que ho faci ha de
// fallar aquí, no en la seva pròpia implementació amb un EnvNotFoundException.
// `kernel.secret` és el placeholder que ja existeix a
// config/packages/framework.yaml (`secret: '%env(APP_SECRET)%'`).
it('resolves an env placeholder from .env.test in the test container', function (): void {
    static::bootKernel();

    expect(static::getContainer()->getParameter('kernel.secret'))->toBe('test_secret');
});

// La segona garantia de .env.test: la connexió que es construeix als tests
// apunta al host deliberadament inabastable, mai a la BD local ni a la remota.
it('builds the dbal connection against the unreachable test host', function (): void {
    static::bootKernel();

    $connection = static::getContainer()->get('doctrine.dbal.default_connection');
    expect($connection)->toBeInstanceOf(Doctrine\DBAL\Connection::class)
        ->and($connection->getParams()['host'] ?? null)->toBe('127.0.0.1')
        ->and($connection->getParams()['port'] ?? null)->toBe(1);
});
