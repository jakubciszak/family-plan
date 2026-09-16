<?php

declare(strict_types=1);

namespace App\Notifications\Communication\EventSubscriber;

use App\Allowance\Domain\Event\PayoutOffered;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class PayoutOfferedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationOrchestrator $notificationOrchestrator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PayoutOffered::class => 'onPayoutOffered',
        ];
    }

    public function onPayoutOffered(PayoutOffered $event): void
    {
        $this->notificationOrchestrator->notifyUser(
            NotificationEvent::payoutOffered(),
            $event->userId(),
            sprintf('Masz %s do odebrania. Potwierdź, kiedy dostaniesz pieniądze.', $this->amount($event->amount())),
            'Kieszonkowe czeka',
            [
                'payout_id' => $event->payoutId()->value(),
                'amount' => $event->amount(),
                'url' => '/allowance',
                'tag' => 'payout-' . $event->payoutId()->value(),
            ]
        );
    }

    private function amount(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, ',', ' ') . ' zł';
    }
}
