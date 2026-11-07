<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}

// On reconstruit la base de test avec les migrations, ce qui vérifie au passage qu'elles marchent.
// SKIP_DB_RESET=1 saute cette étape pour les tests qui n'ont pas besoin de base.
if ('test' === $_SERVER['APP_ENV'] && !($_SERVER['SKIP_DB_RESET'] ?? false)) {
    $console = sprintf('php %s/bin/console --env=test -q ', escapeshellarg(dirname(__DIR__)));
    foreach ([
        'doctrine:database:drop --force --if-exists',
        'doctrine:database:create',
        'doctrine:migrations:migrate --no-interaction',
    ] as $command) {
        passthru($console . $command, $exitCode);
        if (0 !== $exitCode) {
            fwrite(STDERR, sprintf("Échec de la préparation de la base de test : %s\n", $command));
            exit(1);
        }
    }
}
