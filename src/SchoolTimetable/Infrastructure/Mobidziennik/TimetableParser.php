<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Infrastructure\Mobidziennik;

use App\SchoolTimetable\Domain\ValueObject\TimetableLesson;
use Symfony\Component\DomCrawler\Crawler;

final readonly class TimetableParser
{
    private const MONTHS = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function students(string $html): array
    {
        $students = [];
        foreach ($this->containers($html) as $container) {
            $students[] = ['id' => $this->studentId($container), 'name' => $this->studentName($container)];
        }

        return $students;
    }

    /**
     * @return TimetableLesson[]
     */
    public function lessons(string $html, bool $isExtra): array
    {
        $lessons = [];
        foreach ($this->containers($html) as $container) {
            $studentId = $this->studentId($container);
            $studentName = $this->studentName($container);

            $container->filter('.plansc')->each(function (Crawler $grid) use (&$lessons, $studentId, $studentName, $isExtra): void {
                $days = $this->days($grid);
                $grid->filter('.plansc_cnt_w')->each(function (Crawler $cell) use (&$lessons, $days, $studentId, $studentName, $isExtra): void {
                    $lesson = $this->lesson($cell, $days, $studentId, $studentName, $isExtra);
                    if ($lesson !== null) {
                        $lessons[] = $lesson;
                    }
                });
            });
        }

        return $lessons;
    }

    /**
     * @return Crawler[]
     */
    private function containers(string $html): array
    {
        $crawler = new Crawler($html);
        $containers = $crawler->filter('.plany-cnt');

        return $containers->count() === 0 ? [] : iterator_to_array($containers->each(static fn (Crawler $node): Crawler => $node));
    }

    private function studentId(Crawler $container): string
    {
        return str_replace('plan-uczen-', '', (string) $container->attr('id'));
    }

    private function studentName(Crawler $container): string
    {
        $heading = $container->filter('h1');
        if ($heading->count() === 0) {
            return '';
        }

        $first = $heading->getNode(0)?->firstChild;

        return trim((string) ($first?->textContent ?? $heading->text('')));
    }

    /**
     * @return array<int, array{from: float, to: float, date: string}>
     */
    private function days(Crawler $grid): array
    {
        $days = [];
        $offset = 0.0;

        $grid->filter('.plansc_top > div')->each(function (Crawler $column) use (&$days, &$offset): void {
            $width = $this->percent($this->style($column->attr('style'))['width'] ?? null);
            $date = $this->date((string) $column->attr('title'));
            if ($width === null || $date === null) {
                return;
            }

            $days[] = ['from' => $offset, 'to' => $offset + $width, 'date' => $date];
            $offset += $width;
        });

        return $days;
    }

    /**
     * @param array<int, array{from: float, to: float, date: string}> $days
     */
    private function lesson(Crawler $cell, array $days, string $studentId, string $studentName, bool $isExtra): ?TimetableLesson
    {
        $box = $cell->filter('[title]');
        if ($box->count() === 0 || $days === []) {
            return null;
        }

        $box = $box->eq(0);
        $style = $this->style($box->attr('style'));
        $left = $this->percent($style['left'] ?? null);
        $width = $this->percent($style['width'] ?? null);
        if ($left === null || $width === null) {
            return null;
        }

        $centre = $left + $width / 2;
        $date = null;
        foreach ($days as $day) {
            if ($centre >= $day['from'] && $centre <= $day['to']) {
                $date = $day['date'];
                break;
            }
        }

        if ($date === null) {
            return null;
        }

        $lines = $this->lines((string) $box->attr('title'));
        if ($lines === []) {
            return null;
        }

        $hours = array_shift($lines);
        if (!preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/D', $hours, $clock)) {
            return null;
        }

        $marks = array_map(mb_strtolower(...), $lines);
        $type = TimetableLesson::TYPE_NORMAL;
        foreach ($marks as $mark) {
            if (str_contains($mark, 'zastępstwo')) {
                $type = TimetableLesson::TYPE_SUBSTITUTION;
            }
            if (str_contains($mark, 'odwołan')) {
                $type = TimetableLesson::TYPE_CANCELLED;
            }
        }

        $description = array_values(array_filter($lines, static fn (string $line): bool => !in_array(mb_strtolower($line), ['zastępstwo', 'lekcja odwołana', 'odwołana'], true)));

        $classroom = $this->classroom($box);
        [$group, $teacher] = $this->people(array_slice($description, 1), $classroom);

        return new TimetableLesson(
            $studentId,
            $studentName,
            $date,
            $this->clock($clock[1]),
            $this->clock($clock[2]),
            $description[0] ?? null,
            $teacher,
            $classroom,
            $group,
            $type,
            $isExtra,
        );
    }

    /**
     * @param string[] $lines
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function people(array $lines, ?string $classroom): array
    {
        $lines = array_values(array_filter(array_map(function (string $line) use ($classroom): string {
            $line = $classroom === null ? $line : str_replace('('.$classroom.')', '', $line);

            return trim((string) preg_replace('/\([^()]*\)\s*$/u', '', $line));
        }, $lines), static fn (string $line): bool => $line !== ''));

        if ($lines === []) {
            return [null, null];
        }
        if (count($lines) === 1) {
            $parts = explode(' - ', $lines[0], 2);

            return count($parts) === 2 ? [trim($parts[0]), trim($parts[1])] : [null, $lines[0]];
        }

        return [$lines[0], $lines[count($lines) - 1]];
    }

    private function classroom(Crawler $box): ?string
    {
        $span = $box->filter('span');
        if ($span->count() === 0) {
            return null;
        }

        $found = null;
        foreach ($this->lines((string) $span->eq(0)->html()) as $line) {
            if (preg_match('/\(([^()]+)\)\s*$/u', $line, $parts)) {
                $found = trim($parts[1]);
            }
        }

        return $found;
    }

    /**
     * @return string[]
     */
    private function lines(string $markup): array
    {
        $parts = preg_split('/<br\s*\/?>/i', $markup) ?: [];
        $lines = [];
        foreach ($parts as $part) {
            $line = trim((string) preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function style(?string $style): array
    {
        $values = [];
        foreach (explode(';', (string) $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) === 2) {
                $values[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $values;
    }

    private function percent(?string $value): ?float
    {
        if ($value === null || !preg_match('/^-?[\d.]+/', trim($value), $parts)) {
            return null;
        }

        return (float) $parts[0];
    }

    private function date(string $title): ?string
    {
        $parts = preg_split('/\s+/u', trim($title)) ?: [];
        if (count($parts) < 3) {
            return null;
        }

        $month = array_search(mb_strtolower($parts[1]), self::MONTHS, true);
        if ($month === false || !ctype_digit($parts[0]) || !ctype_digit($parts[2])) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $parts[2], $month + 1, (int) $parts[0]);
    }

    private function clock(string $value): string
    {
        [$hours, $minutes] = explode(':', $value);

        return sprintf('%02d:%02d', (int) $hours, (int) $minutes);
    }
}
