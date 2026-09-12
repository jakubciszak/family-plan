<?php

declare(strict_types=1);

namespace App\Party\Domain\Entity;

use App\Party\Domain\ValueObject\ResponsibilityType;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'party_signatures')]
#[ORM\Index(columns: ['signatory_id'])]
#[ORM\Index(columns: ['subject_id'])]
class Signature
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\ManyToOne(targetEntity: PartyRole::class)]
        #[ORM\JoinColumn(name: 'signatory_id', nullable: false)]
        private PartyRole $signatory,

        #[ORM\Column(type: 'responsibility_type', length: 50)]
        private ResponsibilityType $act,

        #[ORM\Column(type: 'uuid')]
        private Uuid $subjectId,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $signedAt
    ) {
    }

    public static function sign(
        Uuid $id,
        PartyRole $signatory,
        ResponsibilityType $act,
        Uuid $subjectId
    ): self {
        return new self($id, $signatory, $act, $subjectId, new DateTimeImmutable());
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function signatory(): PartyRole
    {
        return $this->signatory;
    }

    public function signatoryPartyId(): Uuid
    {
        return $this->signatory->partyId();
    }

    public function act(): ResponsibilityType
    {
        return $this->act;
    }

    public function subjectId(): Uuid
    {
        return $this->subjectId;
    }

    public function signedAt(): DateTimeImmutable
    {
        return $this->signedAt;
    }
}
