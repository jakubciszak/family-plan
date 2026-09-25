<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Infrastructure;

use App\Notifications\Application\Command\RetractPushCommand;
use App\Notifications\Infrastructure\Adapter\PushRetractionAdapter;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PushRetractionAdapterTest extends TestCase
{
    public function testTheRetractionGoesThroughTheQueueOncePerPersonAndTag(): void
    {
        $bus = new RetractionBus();
        $mum = Uuid::generate();

        (new PushRetractionAdapter($bus))->retract([$mum, $mum], ['task-1', 'task-1', '', 'payout-2']);

        $this->assertEquals([new RetractPushCommand([$mum->value()], ['task-1', 'payout-2'])], $bus->messages);
    }

    public function testNothingToRetractSendsNothing(): void
    {
        $bus = new RetractionBus();

        (new PushRetractionAdapter($bus))->retract([Uuid::generate()], []);
        (new PushRetractionAdapter($bus))->retract([], ['task-1']);

        $this->assertSame([], $bus->messages);
    }
}

final class RetractionBus implements MessageBusInterface
{
    /**
     * @var list<object>
     */
    public array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }
}
