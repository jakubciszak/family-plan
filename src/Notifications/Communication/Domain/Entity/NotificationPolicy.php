<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Domain\Entity;

use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_policies')]
#[ORM\UniqueConstraint(name: 'uniq_notification_policy_event', columns: ['event_name'])]
class NotificationPolicy
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(name: 'event_name', type: 'string', length: 50)]
        private string $event,

        #[ORM\Column(type: 'json')]
        private array $channels,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(Uuid $id, NotificationEvent $event, NotificationChannels $channels): self
    {
        if (!$event->isConfigurable()) {
            throw new \InvalidArgumentException(
                sprintf('Notification event "%s" is transactional and cannot be configured', $event->value())
            );
        }

        return new self($id, $event->value(), $channels->toArray(), new DateTimeImmutable());
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function event(): NotificationEvent
    {
        return NotificationEvent::fromString($this->event);
    }

    public function channels(): NotificationChannels
    {
        return NotificationChannels::fromArray($this->channels);
    }

    public function changeChannels(NotificationChannels $channels): void
    {
        $this->channels = $channels->toArray();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
