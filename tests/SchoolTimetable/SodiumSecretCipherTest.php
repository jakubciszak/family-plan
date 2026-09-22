<?php

declare(strict_types=1);

namespace App\Tests\SchoolTimetable;

use App\SchoolTimetable\Domain\Exception\TimetableException;
use App\SchoolTimetable\Infrastructure\Security\SodiumSecretCipher;
use PHPUnit\Framework\TestCase;

final class SodiumSecretCipherTest extends TestCase
{
    public function testAPasswordSurvivesARoundTrip(): void
    {
        $cipher = new SodiumSecretCipher('application-secret');

        self::assertSame('tajne-hasło', $cipher->decrypt($cipher->encrypt('tajne-hasło')));
    }

    public function testTheStoredFormRevealsNeitherThePasswordNorItsRepetition(): void
    {
        $cipher = new SodiumSecretCipher('application-secret');
        $stored = $cipher->encrypt('tajne-hasło');

        self::assertStringNotContainsString('tajne-hasło', base64_decode($stored, true) ?: '');
        self::assertNotSame($stored, $cipher->encrypt('tajne-hasło'));
    }

    public function testAnotherSecretCannotRead(): void
    {
        $stored = (new SodiumSecretCipher('application-secret'))->encrypt('tajne-hasło');

        $this->expectException(TimetableException::class);
        (new SodiumSecretCipher('inny-sekret'))->decrypt($stored);
    }

    public function testGarbageIsRejected(): void
    {
        $this->expectException(TimetableException::class);
        (new SodiumSecretCipher('application-secret'))->decrypt('nie-szyfrogram');
    }
}
