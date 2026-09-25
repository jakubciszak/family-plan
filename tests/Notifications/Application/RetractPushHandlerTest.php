<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Application;

use App\Notifications\Application\Command\RetractPushCommand;
use App\Notifications\Application\Handler\RetractPushHandler;
use App\Notifications\Domain\Entity\NativePushDevice;
use App\Notifications\Domain\Port\NativePushSenderInterface;
use App\Notifications\Domain\Port\PushDelivery;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\PushOptions;
use App\Notifications\Infrastructure\Persistence\InMemoryNativePushDeviceRepository;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class RetractPushHandlerTest extends TestCase
{
    private InMemoryNativePushDeviceRepository $devices;

    private RetractingPhones $phones;

    private RetractPushHandler $handler;

    protected function setUp(): void
    {
        $this->devices = new InMemoryNativePushDeviceRepository();
        $this->phones = new RetractingPhones();
        $this->handler = new RetractPushHandler($this->devices, $this->phones);
    }

    public function testEveryPhoneOfThosePeopleDropsTheTags(): void
    {
        $mum = Uuid::generate();
        $dad = Uuid::generate();
        $this->phone($mum, 'mum-phone');
        $this->phone($mum, 'mum-tablet');
        $this->phone($dad, 'dad-phone');
        $this->phone(Uuid::generate(), 'child-phone');

        ($this->handler)(new RetractPushCommand([$mum->value(), $dad->value()], ['task-1']));

        $this->assertEqualsCanonicalizing(
            [['mum-phone', ['task-1']], ['mum-tablet', ['task-1']], ['dad-phone', ['task-1']]],
            $this->phones->retractions
        );
    }

    public function testWithoutFirebaseNothingGoesOut(): void
    {
        $mum = Uuid::generate();
        $this->phone($mum, 'mum-phone');
        $this->phones->configured = false;

        ($this->handler)(new RetractPushCommand([$mum->value()], ['task-1']));

        $this->assertSame([], $this->phones->retractions);
    }

    public function testManyTagsTravelInSeveralMessages(): void
    {
        $mum = Uuid::generate();
        $this->phone($mum, 'mum-phone');
        $tags = array_map(static fn (int $number): string => 'task-' . $number, range(1, 30));

        ($this->handler)(new RetractPushCommand([$mum->value()], $tags));

        $this->assertCount(2, $this->phones->retractions);
        $this->assertSame(array_slice($tags, 0, 25), $this->phones->retractions[0][1]);
        $this->assertSame(array_slice($tags, 25), $this->phones->retractions[1][1]);
    }

    public function testAPhoneFirebaseNoLongerKnowsIsForgotten(): void
    {
        $mum = Uuid::generate();
        $this->phone($mum, 'mum-phone');
        $this->phones->answer = PushDelivery::Gone;

        ($this->handler)(new RetractPushCommand([$mum->value(), 'not-a-uuid'], array_map(static fn (int $number): string => 'task-' . $number, range(1, 30))));

        $this->assertCount(1, $this->phones->retractions);
        $this->assertSame([], $this->devices->findForUser($mum));
    }

    private function phone(Uuid $userId, string $token): void
    {
        $this->devices->save(NativePushDevice::register(Uuid::generate(), $userId, NativePushDevice::ANDROID, $token, null, new DateTimeImmutable('2026-09-25 18:00:00')));
    }
}

final class RetractingPhones implements NativePushSenderInterface
{
    public bool $configured = true;

    public PushDelivery $answer = PushDelivery::Delivered;

    /**
     * @var list<array{0: string, 1: list<string>}>
     */
    public array $retractions = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(NativePushDevice $device, NotificationMessage $message, PushOptions $options): PushDelivery
    {
        return PushDelivery::Delivered;
    }

    public function retract(NativePushDevice $device, array $tags): PushDelivery
    {
        $this->retractions[] = [$device->token(), $tags];

        return $this->answer;
    }
}
