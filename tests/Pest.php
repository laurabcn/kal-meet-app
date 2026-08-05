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
