<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$projectDir = realpath(dirname(__DIR__));
$databaseUrl = sprintf('sqlite:///%s/var/test.db', $projectDir);

if (!isset($_SERVER['APP_ENV'])) {
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_ENV'] === 'test') {
    $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;
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
    'APP_ENV=test DATABASE_URL=%s php %s/bin/console doctrine:schema:create --no-interaction --quiet 2>&1',
    escapeshellarg($databaseUrl),
    escapeshellarg($projectDir)
), $output, $result);

if ($result !== 0) {
    echo "Failed to create test database schema:\n";
    echo implode("\n", $output);
    exit(1);
}
