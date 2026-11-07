<?php

// Configuration php-cs-fixer UNIQUE du projet (utilisée en local et par la CI).
// Ne pas créer de .php-cs-fixer.php local : il serait prioritaire sur ce fichier
// et le style vérifié en local ne serait plus celui vérifié par la CI.

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
        // Syntaxe PHP 8.4 : new Foo()->bar() sans parenthèses autour du new (convention du projet)
        '@PHP84Migration' => true,

        // Imports
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],

        // Un espace autour du « . » de concaténation : $a . '/' . $b
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
