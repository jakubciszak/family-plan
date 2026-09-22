<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Domain\ValueObject;

use App\SchoolTimetable\Domain\Exception\TimetableException;

final readonly class SchoolCredentials
{
    public function __construct(
        public string $schoolId,
        public string $login,
        public string $password,
    ) {
        self::assertSchool($schoolId);
        self::assertLogin($login);
        self::assertPassword($password);
    }

    public static function assertSchool(string $schoolId): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/D', $schoolId)) {
            throw TimetableException::invalid('A school identifier is the subdomain of the mobidziennik address.');
        }
    }

    public static function assertLogin(string $login): void
    {
        if (trim($login) === '' || mb_strlen($login) > 190) {
            throw TimetableException::invalid('A sign in needs a login.');
        }
    }

    public static function assertPassword(string $password): void
    {
        if ($password === '' || mb_strlen($password) > 190) {
            throw TimetableException::invalid('A sign in needs a password.');
        }
    }

    public function url(string $path): string
    {
        return sprintf('https://%s.mobidziennik.pl%s', $this->schoolId, $path);
    }
}
