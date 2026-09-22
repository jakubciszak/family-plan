<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Application\Service;

use App\SchoolTimetable\Domain\Entity\SchoolAccount;
use App\SchoolTimetable\Domain\Repository\SchoolAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class TimetableSync
{
    public function __construct(
        private SchoolAccountRepositoryInterface $accounts,
        private TimetableAccess $access,
        private TimetableImport $import,
    ) {
    }

    /**
     * @return array<int, array{teamId: string, weeks?: array<string, array<string, mixed>>, skipped?: string, failed?: string}>
     */
    public function run(int $weeks = 2): array
    {
        $report = [];
        foreach ($this->accounts->all() as $account) {
            $report[] = ['teamId' => $account->teamId()->value()] + $this->account($account, $weeks);
        }

        return $report;
    }

    private function account(SchoolAccount $account, int $weeks): array
    {
        if ($account->linkedStudents() === []) {
            return ['skipped' => 'no_linked_students'];
        }

        $caller = $this->caller($account);
        if ($caller === null) {
            return ['skipped' => 'no_admin_to_act_for'];
        }

        $week = ImportWeek::from(null);
        $done = [];
        for ($index = 0; $index < max(1, $weeks); ++$index) {
            try {
                $summary = $this->import->run($caller, $account->teamId()->value(), $week);
                $done[$week] = array_intersect_key($summary, ['added' => null, 'updated' => null, 'unchanged' => null, 'removed' => null, 'substitutions' => null, 'skipped' => null]);
            } catch (\Throwable $failure) {
                return ['weeks' => $done, 'failed' => $failure->getMessage()];
            }

            $week = (new \DateTimeImmutable($week))->modify('+7 days')->format('Y-m-d');
        }

        return ['weeks' => $done];
    }

    private function caller(SchoolAccount $account): ?Uuid
    {
        $owner = $account->ownerId();
        if ($owner !== null && $this->access->member(Uuid::fromString($owner), $account->teamId())) {
            return Uuid::fromString($owner);
        }

        $admin = $this->access->anyAdmin($account->teamId());
        if ($admin !== null) {
            $account->configuredBy($admin->value());
            $this->accounts->save($account);
        }

        return $admin;
    }
}
