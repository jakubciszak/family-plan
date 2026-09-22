<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Infrastructure\Security;

use App\SchoolTimetable\Domain\Exception\TimetableException;
use App\SchoolTimetable\Domain\Service\SecretCipherInterface;

final readonly class SodiumSecretCipher implements SecretCipherInterface
{
    private string $key;

    public function __construct(string $secret)
    {
        if ($secret === '') {
            throw new \LogicException('School timetable credentials need an application secret to be encrypted with.');
        }

        $this->key = sodium_crypto_generichash($secret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $cipher): string
    {
        $raw = base64_decode($cipher, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new TimetableException('stored_password_unreadable', 500);
        }

        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);
        if ($plain === false) {
            throw new TimetableException('stored_password_unreadable', 500);
        }

        return $plain;
    }
}
