<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Domain;

use App\Notifications\Domain\ValueObject\NotificationMessage;
use PHPUnit\Framework\TestCase;

class NotificationMessageTest extends TestCase
{
    public function testCanCreateMessageWithContentOnly(): void
    {
        $message = NotificationMessage::create('Hello, World!');
        
        $this->assertEquals('Hello, World!', $message->content());
        $this->assertNull($message->subject());
        $this->assertEquals([], $message->additionalParameters());
    }

    public function testCanCreateMessageWithSubject(): void
    {
        $message = NotificationMessage::create(
            'Message body',
            'Message subject'
        );
        
        $this->assertEquals('Message body', $message->content());
        $this->assertEquals('Message subject', $message->subject());
    }

    public function testCanCreateMessageWithAdditionalParameters(): void
    {
        $params = ['priority' => 'high', 'sender' => 'system'];
        $message = NotificationMessage::create(
            'Message content',
            null,
            $params
        );
        
        $this->assertEquals('Message content', $message->content());
        $this->assertEquals($params, $message->additionalParameters());
    }

    public function testThrowsExceptionForEmptyContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message content cannot be empty');
        
        NotificationMessage::create('');
    }

    public function testThrowsExceptionForEmptySubject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message subject cannot be empty');
        
        NotificationMessage::create('Content', '');
    }

    public function testCanGetSpecificAdditionalParameter(): void
    {
        $message = NotificationMessage::create(
            'Content',
            null,
            ['key1' => 'value1', 'key2' => 'value2']
        );
        
        $this->assertEquals('value1', $message->getParameter('key1'));
        $this->assertEquals('value2', $message->getParameter('key2'));
        $this->assertNull($message->getParameter('nonexistent'));
    }

    public function testHasParameterCheck(): void
    {
        $message = NotificationMessage::create(
            'Content',
            null,
            ['existing' => 'value']
        );
        
        $this->assertTrue($message->hasParameter('existing'));
        $this->assertFalse($message->hasParameter('nonexistent'));
    }
}
