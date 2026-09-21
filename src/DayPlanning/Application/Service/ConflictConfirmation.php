<?php

declare(strict_types=1);

namespace App\DayPlanning\Application\Service;

final readonly class ConflictConfirmation
{
    public function __construct(private string $secret)
    {
    }

    public function issue(string $actorId, array $definition, array $conflicts): string
    {
        $expires = time() + 600;
        $digest = $this->digest($actorId, $definition, $conflicts);
        $payload = $expires.'.'.$digest;
        return $payload.'.'.hash_hmac('sha256', $payload, $this->secret);
    }

    public function valid(mixed $token, string $actorId, array $definition, array $conflicts): bool
    {
        if (!is_string($token) || !preg_match('/^(\d+)\.([a-f0-9]{64})\.([a-f0-9]{64})$/D', $token, $parts) || (int) $parts[1] < time()) {
            return false;
        }
        return hash_equals($parts[2], $this->digest($actorId, $definition, $conflicts)) && hash_equals($parts[3], hash_hmac('sha256', $parts[1].'.'.$parts[2], $this->secret));
    }

    public function hash(array $data): string
    {
        return hash('sha256', json_encode($this->canonical($data), JSON_THROW_ON_ERROR));
    }

    private function digest(string $actorId, array $definition, array $conflicts): string
    {
        return $this->hash(['actorId' => $actorId, 'definition' => $definition, 'conflicts' => $conflicts]);
    }

    private function canonical(array $data): array
    {
        if (!array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->canonical($value);
            }
        }
        return $data;
    }
}
