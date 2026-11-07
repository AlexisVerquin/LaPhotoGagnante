<?php

// La seule config de style du projet, la même en local et sur la CI.
// Pas de .php-cs-fixer.php local : il passerait devant celle-ci sans prévenir.

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude([
        'var',
        'public/bundles',
        'public/assets',
        'storage',
    ])
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return new PhpCsFixer\Config()
    ->setRules([
        '@Symfony' => true,
        // On écrit new Foo()->bar(), sans parenthèses autour du new (PHP 8.4).
        '@PHP84Migration' => true,

        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],

        // Un espace autour du point : $a . '/' . $b
        'concat_space' => [
            'spacing' => 'one',
        ],
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters', 'match'],
        ],
    ])
    ->setFinder($finder)
    ->setLineEnding("\n")
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache')
;
