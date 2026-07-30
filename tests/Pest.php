<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// El tier d'un test l'implica on viu, no un base class per fitxer:
// tests/Kal/Ui són tests que van pel kernel HTTP de veritat.
pest()->extends(WebTestCase::class)->in('Kal/Ui');
