<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// El tier d'un test l'implica on viu, no un base class per fitxer:
// tests/Feature/* van pel kernel HTTP de veritat.
pest()->extends(WebTestCase::class)->in('Feature');

// tests/Unit/Bootstrap comprova l'arrencada en si (entorn, container), sense HTTP.
pest()->extends(KernelTestCase::class)->in('Unit/Bootstrap');
