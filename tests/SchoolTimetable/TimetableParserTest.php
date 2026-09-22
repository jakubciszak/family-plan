<?php

declare(strict_types=1);

namespace App\Tests\SchoolTimetable;

use App\SchoolTimetable\Domain\ValueObject\TimetableLesson;
use App\SchoolTimetable\Infrastructure\Mobidziennik\TimetableParser;
use PHPUnit\Framework\TestCase;

final class TimetableParserTest extends TestCase
{
    public function testEveryStudentOnTheAccountIsFound(): void
    {
        self::assertSame([['id' => '7', 'name' => 'Nowak Ola'], ['id' => '11', 'name' => 'Nowak Jaś']], (new TimetableParser())->students($this->html()));
    }

    public function testALessonCarriesItsStudentDayHoursAndDetails(): void
    {
        $lesson = $this->lessons()[0];

        self::assertSame('7', $lesson->studentId);
        self::assertSame('Nowak Ola', $lesson->studentName);
        self::assertSame('2026-09-21', $lesson->date);
        self::assertSame('08:00', $lesson->startTime);
        self::assertSame('08:45', $lesson->endTime);
        self::assertSame('Matematyka', $lesson->subject);
        self::assertSame('Kowalska Maria', $lesson->teacher);
        self::assertSame('4A Matematyka grupa pierwsza', $lesson->group);
        self::assertSame('Sala 12', $lesson->classroom);
        self::assertSame(45, $lesson->durationMinutes());
    }

    public function testTheColumnOfACellDecidesItsDay(): void
    {
        self::assertSame(['2026-09-21', '2026-09-22', '2026-09-23', '2026-09-25'], array_map(static fn (TimetableLesson $lesson): string => $lesson->date, $this->lessons()));
    }

    public function testSubstitutionsAndCancellationsKeepTheirType(): void
    {
        self::assertSame([TimetableLesson::TYPE_NORMAL, TimetableLesson::TYPE_SUBSTITUTION, TimetableLesson::TYPE_CANCELLED, TimetableLesson::TYPE_NORMAL], array_map(static fn (TimetableLesson $lesson): string => $lesson->type, $this->lessons()));
    }

    public function testTheSecondStudentKeepsTheirOwnLessons(): void
    {
        $lesson = $this->lessons()[3];

        self::assertSame('11', $lesson->studentId);
        self::assertSame('zajęcia rewalidacyjne', $lesson->subject);
        self::assertSame('Wójcik Anna', $lesson->teacher);
        self::assertSame(60, $lesson->durationMinutes());
    }

    public function testAnExtraPlanMarksItsLessons(): void
    {
        self::assertTrue((new TimetableParser())->lessons($this->html(), true)[0]->isExtra);
    }

    public function testTheBasicPlanSqueezesClassTeacherAndRoomIntoOneLine(): void
    {
        $html = str_replace(
            'title="08:00 - 08:45&lt;br /&gt;Matematyka&lt;br /&gt;4A Matematyka grupa pierwsza&lt;br /&gt;Kowalska Maria&lt;br /&gt;"',
            'title="08:00 - 08:45&lt;br /&gt;Matematyka&lt;br /&gt;4 A - Kowalska Maria (Sala 12)"',
            $this->html(),
        );

        $lesson = (new TimetableParser())->lessons($html, false)[0];

        self::assertSame('Matematyka', $lesson->subject);
        self::assertSame('Kowalska Maria', $lesson->teacher);
        self::assertSame('4 A', $lesson->group);
        self::assertSame('Sala 12', $lesson->classroom);
    }

    public function testAGroupNameHoldingADashStaysWhole(): void
    {
        self::assertSame('4,5,6,7 Zajęcia rewalidacyjne grupowe - klasowe', (new TimetableParser())->lessons($this->extraHtml(), true)[0]->group);
    }

    public function testAPageWithoutAPlanYieldsNothing(): void
    {
        self::assertSame([], (new TimetableParser())->lessons('<html><body><h1>Plan lekcji</h1></body></html>', false));
    }

    /**
     * @return TimetableLesson[]
     */
    private function lessons(): array
    {
        return (new TimetableParser())->lessons($this->html(), false);
    }

    private function extraHtml(): string
    {
        return str_replace(
            'title="08:00 - 08:45&lt;br /&gt;Matematyka&lt;br /&gt;4A Matematyka grupa pierwsza&lt;br /&gt;Kowalska Maria&lt;br /&gt;"',
            'title="08:00 - 08:45&lt;br /&gt;zajęcia rewalidacyjne&lt;br /&gt;4,5,6,7 Zajęcia rewalidacyjne grupowe - klasowe&lt;br /&gt;Zarzecka Aleksandra&lt;br /&gt;"',
            $this->html(),
        );
    }

    private function html(): string
    {
        return (string) file_get_contents(__DIR__.'/fixtures/planlekcji.html');
    }
}
