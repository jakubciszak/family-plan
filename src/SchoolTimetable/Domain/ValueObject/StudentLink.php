<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Domain\ValueObject;

use App\SchoolTimetable\Domain\Exception\TimetableException;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class StudentLink
{
    public function __construct(
        public string $studentId,
        public string $studentName,
        public ?string $userId = null,
    ) {
        if (trim($studentId) === '' || mb_strlen($studentId) > 64 || mb_strlen($studentName) > 190) {
            throw TimetableException::invalid('Invalid student.');
        }
        if ($userId !== null && !Uuid::isValid($userId)) {
            throw TimetableException::invalid('Invalid family member.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self((string) ($data['studentId'] ?? ''), (string) ($data['studentName'] ?? ''), isset($data['userId']) && is_string($data['userId']) ? strtolower($data['userId']) : null);
    }

    public function withUser(?string $userId): self
    {
        return new self($this->studentId, $this->studentName, $userId);
    }

    public function withName(string $studentName): self
    {
        return new self($this->studentId, $studentName, $this->userId);
    }

    public function describe(): array
    {
        return ['studentId' => $this->studentId, 'studentName' => $this->studentName, 'userId' => $this->userId];
    }
}
