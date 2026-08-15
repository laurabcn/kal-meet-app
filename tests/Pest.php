<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Unit\Shared\Infrastructure\Symfony\Security\StubTokenHandler;

// El tier d'un test l'implica on viu, no un base class per fitxer:
// tests/Feature/* van pel kernel HTTP de veritat.
pest()->extends(WebTestCase::class)->in('Feature');

// tests/Unit/Bootstrap comprova l'arrencada en si (entorn, container), sense HTTP.
pest()->extends(KernelTestCase::class)->in('Unit/Bootstrap');

// El firewall protegeix `^/` sencer: sense capçalera els tests de Feature reben
// 401 i no arriben mai al controller. El que proven és el contracte de cada
// endpoint, no l'auth — d'això se n'ocupa Feature/Security/Http/FirewallTest.php.
//
// Els helpers viuen aquí i no a cada fitxer: declarats com a funcions globals
// dins d'un test, el segon fitxer que en repetís el nom petaria amb un fatal
// de redeclaració.

/** @return array<string, string> */
function apiAuthHeaders(): array
{
    return ['HTTP_AUTHORIZATION' => 'Bearer '.StubTokenHandler::TOKEN];
}

/** @return array<string, string> */
function apiJsonHeaders(): array
{
    return ['CONTENT_TYPE' => 'application/json'] + apiAuthHeaders();
}

/**
 * Payload vàlid de pista per als tests d'HTTP. El PDF i la reunió hi són perquè
 * el domini no admet una pista sense cap dels dos.
 *
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function cluePayload(array $overrides = []): array
{
    return [
        'name' => 'Pista 2',
        'startsOn' => '2026-08-02 00:00:00',
        'endsOn' => '2026-08-07 00:00:00',
        'locale' => 'ca',
        'file' => [
            'fileName' => 'pista-2.pdf',
            'filePath' => 'kal/clue/pista-2.pdf',
            'fileSize' => 184320,
            'fileExtension' => 'pdf',
            'locale' => 'ca',
            'uploadId' => '01J5M6XQBR4GTYHN8KZXP0F1A1',
            'uploadedAt' => '2026-07-30 12:00:00',
        ],
        'meeting' => [
            'scheduledAt' => '2026-08-03 18:00:00',
            'url' => 'https://meet.example.com/pista-2',
            'title' => 'Trobada de la pista 2',
        ],
    ] + $overrides;
}
