<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Clean up test database before running tests
$testDbPath = dirname(__DIR__).'/var/test.db';
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

// Recreate the test database schema
$output = [];
$result = 0;
exec(sprintf(
    'APP_ENV=test php %s/bin/console doctrine:schema:create --no-interaction --quiet 2>&1',
    escapeshellarg(dirname(__DIR__))
), $output, $result);

if ($result !== 0) {
    echo "Failed to create test database schema:\n";
    echo implode("\n", $output);
    exit(1);
}
