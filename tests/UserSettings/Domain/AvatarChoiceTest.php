<?php

declare(strict_types=1);

namespace App\Tests\UserSettings\Domain;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\ValueObject\AvatarChoice;
use DomainException;
use PHPUnit\Framework\TestCase;

class AvatarChoiceTest extends TestCase
{
    public function testADrawnAvatarIsAStyleAndASeed(): void
    {
        $avatar = AvatarChoice::drawn('pixel-art', 'rakieta');

        $this->assertFalse($avatar->isUploaded());
        $this->assertSame('pixel-art', $avatar->style());
        $this->assertSame('rakieta', $avatar->seed());
        $this->assertNull($avatar->imageId());
    }

    public function testAStyleWeDoNotDrawIsRefused(): void
    {
        $this->expectException(DomainException::class);

        AvatarChoice::drawn('malowany-recznie', 'x');
    }

    public function testASeedIsNeeded(): void
    {
        $this->expectException(DomainException::class);

        AvatarChoice::drawn('bottts', '   ');
    }

    public function testAnUploadedPictureReplacesTheDrawing(): void
    {
        $pictureId = Uuid::generate();

        $avatar = AvatarChoice::drawn('bottts', 'x');
        $worn = AvatarChoice::uploaded($pictureId);

        $this->assertFalse($avatar->isUploaded());
        $this->assertTrue($worn->isUploaded());
        $this->assertTrue($pictureId->equals($worn->imageId()));
    }

    public function testAChoiceSurvivesBeingWrittenDownAndReadBack(): void
    {
        $avatar = AvatarChoice::drawn('lorelei', 'jez');

        $again = AvatarChoice::fromArray($avatar->toArray());

        $this->assertSame($avatar->style(), $again->style());
        $this->assertSame($avatar->seed(), $again->seed());
    }
}
