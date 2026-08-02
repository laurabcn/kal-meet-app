<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Es carrega NOMÉS .env.test, no `Dotenv::bootEnv()`, a propòsit:
//
// - `bootEnv()` carrega primer `.env`, que està al .gitignore i porta les
//   credencials reals de Supabase de cada màquina. Els tests han de ser
//   hermètics i iguals a tot arreu; el DATABASE_URL inabastable de .env.test
//   no protegeix de res si abans hi ha entrat el de producció.
// - `bootEnv()` peta amb PathException si no hi ha `.env` — cosa que passa en
//   un clone net, on l'únic que hi ha és `.env.example`.
//
// `load()` (no `overload()`) no trepitja les variables que ja vinguin de
// l'entorn real: qui executi els tests amb variables pel compose o per CI mana
// per sobre del fitxer.
$dotenv = new Dotenv();
$dotenv->load(dirname(__DIR__).'/.env.test');

if (is_file($local = dirname(__DIR__).'/.env.test.local')) {
    $dotenv->load($local);
}
