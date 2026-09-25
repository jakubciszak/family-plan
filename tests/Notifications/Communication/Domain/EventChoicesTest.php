<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Communication\Domain;

use App\Notifications\Communication\Domain\ValueObject\EventChoices;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\UserSettings\Domain\ValueObject\PreferenceOption;
use App\UserSettings\Domain\ValueObject\PreferenceType;
use App\UserSettings\Domain\ValueObject\UserPreference;
use PHPUnit\Framework\TestCase;

class EventChoicesTest extends TestCase
{
    public function testEverythingIsOnUntilTheUserSwitchesItOff(): void
    {
        $choices = EventChoices::from(null);

        foreach (NotificationEvent::all() as $event) {
            $this->assertTrue($choices->wants($event), $event->value());
        }
    }

    public function testASwitchedOffKindStaysOff(): void
    {
        $choices = EventChoices::from(UserPreference::create(PreferenceType::notificationEvents(), [
            PreferenceOption::create('task_assigned', false),
        ]));

        $this->assertFalse($choices->wants(NotificationEvent::fromString('task_assigned')));
        $this->assertTrue($choices->wants(NotificationEvent::taskApproved()));
    }

    public function testAccountEmailsCannotBeSwitchedOff(): void
    {
        $choices = EventChoices::from(UserPreference::create(PreferenceType::notificationEvents(), [
            PreferenceOption::create('account_activation', false),
        ]));

        $this->assertTrue($choices->wants(NotificationEvent::accountActivation()));

        $this->expectException(\InvalidArgumentException::class);
        $choices->with(['account_activation' => false]);
    }

    public function testAChangeOnlyTouchesWhatItNames(): void
    {
        $choices = EventChoices::from(null)->with(['streak_at_risk' => false])->with(['task_assigned' => false, 'streak_at_risk' => true]);

        $this->assertFalse($choices->wants(NotificationEvent::fromString('task_assigned')));
        $this->assertTrue($choices->wants(NotificationEvent::streakAtRisk()));
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EventChoices::from(null)->with(['birthday' => false]);
    }

    public function testAChoiceHasToBeTrueOrFalse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EventChoices::from(null)->with(['task_assigned' => 'no']);
    }

    public function testItIsStoredAsOneOptionPerKindTheUserCanChoose(): void
    {
        $options = EventChoices::from(null)->with(['task_removed' => false])->toOptions();

        $this->assertSame(
            array_map(static fn (NotificationEvent $event) => $event->value(), NotificationEvent::userChoices()),
            array_map(static fn (PreferenceOption $option) => $option->name(), $options)
        );
        $removed = array_values(array_filter($options, static fn (PreferenceOption $option) => $option->name() === 'task_removed'))[0];
        $this->assertFalse($removed->isEnabled());
    }
}
