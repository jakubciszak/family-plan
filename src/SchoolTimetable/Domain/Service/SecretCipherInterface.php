<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Domain\Service;

interface SecretCipherInterface
{
    public function encrypt(string $plain): string;

    public function decrypt(string $cipher): string;
}
