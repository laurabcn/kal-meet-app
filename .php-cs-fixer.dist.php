<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        getcwd() . '/config',
        getcwd() . '/public',
        getcwd() . '/src',
        getcwd() . '/tests',
    ])
    ->notPath('reference.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@Symfony' => true,
        'declare_strict_types' => true,
        'php_unit_method_casing' => ['case' => 'snake_case'],
        'single_line_throw' => false,
    ])
    ->setFinder($finder)
    ;