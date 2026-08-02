<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// El tier d'un test l'implica on viu, no un base class per fitxer:
// tests/Kal/Ui són tests que van pel kernel HTTP de veritat.
pest()->extends(WebTestCase::class)->in('Kal/Ui');

// tests/Security prova el firewall a través del kernel HTTP: els codis d'error
// d'autenticació només es poden comprovar de veritat passant-hi pel mig.
pest()->extends(WebTestCase::class)->in('Security');

// tests/Bootstrap comprova l'arrencada en si (entorn, container), sense HTTP.
pest()->extends(KernelTestCase::class)->in('Bootstrap');
