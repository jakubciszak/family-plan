<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Application\Service;

use App\SchoolTimetable\Domain\Entity\SchoolAccount;
use App\SchoolTimetable\Domain\Exception\TimetableException;
use App\SchoolTimetable\Domain\Repository\SchoolAccountRepositoryInterface;
use App\SchoolTimetable\Domain\Service\SecretCipherInterface;
use App\SchoolTimetable\Domain\Service\TimetableProviderInterface;
use App\SchoolTimetable\Domain\ValueObject\SchoolCredentials;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class SchoolAccounts
{
    public function __construct(
        private SchoolAccountRepositoryInterface $accounts,
        private TimetableAccess $access,
        private SecretCipherInterface $cipher,
        private TimetableProviderInterface $provider,
    ) {
    }

    public function read(Uuid $caller, mixed $teamId): array
    {
        $team = $this->access->team($caller, $teamId);
        $account = $this->accounts->ofTeam($team);

        return ['account' => $account?->describe()];
    }

    public function save(Uuid $caller, array $data): array
    {
        $team = $this->access->team($caller, $data['teamId'] ?? null);
        $account = $this->accounts->ofTeam($team);

        $schoolId = mb_strtolower(trim((string) ($data['schoolId'] ?? '')));
        $login = trim((string) ($data['login'] ?? ''));
        $password = $data['password'] ?? null;
        if ($password !== null && !is_string($password)) {
            throw TimetableException::invalid('Invalid password.');
        }
        if ($password === null && $account === null) {
            throw TimetableException::invalid('A first sign in needs a password.');
        }

        SchoolCredentials::assertSchool($schoolId);
        SchoolCredentials::assertLogin($login);
        if ($password !== null) {
            SchoolCredentials::assertPassword($password);
        }

        $secret = $password === null ? null : $this->cipher->encrypt($password);
        if ($account === null) {
            $account = SchoolAccount::create(Uuid::generate(), $team, $schoolId, $login, (string) $secret);
        } else {
            $account->changeSignIn($schoolId, $login, $secret);
        }

        $account->configuredBy($caller->value());

        if (array_key_exists('tagId', $data)) {
            $account->useTag($this->tag($data['tagId']));
        }

        $this->accounts->save($account);

        return ['account' => $account->describe()];
    }

    private function tag(mixed $tagId): ?string
    {
        if ($tagId === null || $tagId === '') {
            return null;
        }
        if (!is_string($tagId) || !Uuid::isValid(strtolower($tagId))) {
            throw TimetableException::invalid('Invalid calendar tag.');
        }

        return strtolower($tagId);
    }

    public function forget(Uuid $caller, mixed $teamId): void
    {
        $team = $this->access->team($caller, $teamId);
        $account = $this->accounts->ofTeam($team) ?? throw TimetableException::notConfigured();

        $this->accounts->remove($account);
    }

    public function refreshStudents(Uuid $caller, mixed $teamId, mixed $weekStart): array
    {
        $team = $this->access->team($caller, $teamId);
        $account = $this->accounts->ofTeam($team) ?? throw TimetableException::notConfigured();

        $week = $this->provider->week($this->credentials($account), ImportWeek::from($weekStart));
        $account->rememberStudents($week->students);
        $this->accounts->save($account);

        return ['account' => $account->describe()];
    }

    public function linkStudents(Uuid $caller, mixed $teamId, mixed $links): array
    {
        $team = $this->access->team($caller, $teamId);
        $account = $this->accounts->ofTeam($team) ?? throw TimetableException::notConfigured();

        if (!is_array($links)) {
            throw TimetableException::invalid('Invalid student links.');
        }

        $wanted = [];
        foreach ($links as $studentId => $userId) {
            if ($userId !== null && (!is_string($userId) || !Uuid::isValid(strtolower($userId)))) {
                throw TimetableException::invalid('Invalid student links.');
            }
            if ($userId !== null && !$this->access->member(Uuid::fromString(strtolower($userId)), $team)) {
                throw TimetableException::invalid('A timetable can only go to a member of this team.');
            }
            $wanted[(string) $studentId] = $userId === null ? null : strtolower($userId);
        }

        $account->linkStudents($wanted);
        $this->accounts->save($account);

        return ['account' => $account->describe()];
    }

    public function credentials(SchoolAccount $account): SchoolCredentials
    {
        return new SchoolCredentials($account->schoolId(), $account->login(), $this->cipher->decrypt($account->secret()));
    }
}
