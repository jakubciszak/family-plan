<?php

declare(strict_types=1);

namespace App\Tests;

final class TestDatabase
{
    private static bool $prepared = false;

    public static function prepareOnce(): void
    {
        if (self::$prepared) {
            return;
        }

        self::$prepared = true;

        self::console('doctrine:database:create --if-not-exists --no-interaction --quiet');
        self::console('doctrine:schema:drop --full-database --force --no-interaction --quiet');
        self::console('doctrine:migrations:migrate --no-interaction --allow-no-migration --quiet');
    }

    private static function console(string $command): void
    {
        $projectDir = realpath(dirname(__DIR__));
        $output = [];
        $status = 0;

        exec(
            sprintf(
                'APP_ENV=test TEST_TOKEN=%s php %s/bin/console %s 2>&1',
                escapeshellarg((string) ($_SERVER['TEST_TOKEN'] ?? '')),
                escapeshellarg($projectDir),
                $command
            ),
            $output,
            $status
        );

        if ($status !== 0) {
            echo sprintf("Failed to prepare the test database with \"%s\":\n%s\n", $command, implode("\n", $output));
            exit(1);
        }
    }
}
