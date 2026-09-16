<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if (($_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? '') === '') {
    $token = '_'.substr(hash('sha256', (string) realpath(dirname(__DIR__))), 0, 8);

    $_SERVER['TEST_TOKEN'] = $_ENV['TEST_TOKEN'] = $token;
    putenv('TEST_TOKEN='.$token);
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
