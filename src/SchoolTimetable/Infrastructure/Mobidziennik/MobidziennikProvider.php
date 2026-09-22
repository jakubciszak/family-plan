<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Infrastructure\Mobidziennik;

use App\SchoolTimetable\Domain\Exception\TimetableException;
use App\SchoolTimetable\Domain\Service\TimetableProviderInterface;
use App\SchoolTimetable\Domain\ValueObject\SchoolCredentials;
use App\SchoolTimetable\Domain\ValueObject\TimetableWeek;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;

final readonly class MobidziennikProvider implements TimetableProviderInterface
{
    private const PLANS = ['podstawowy' => false, 'pozalekcyjny' => true];
    private const AGENT = 'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/115.0';

    public function __construct(private TimetableParser $parser)
    {
    }

    public function week(SchoolCredentials $credentials, string $weekStart): TimetableWeek
    {
        $browser = new HttpBrowser(HttpClient::create(['timeout' => 20, 'max_duration' => 40, 'headers' => ['User-Agent' => self::AGENT]]));

        try {
            $browser->request('POST', $credentials->url('/dziennik'), ['login' => $credentials->login, 'haslo' => $credentials->password]);

            $students = [];
            $lessons = [];
            foreach (self::PLANS as $plan => $isExtra) {
                $html = $this->read($browser, $credentials->url(sprintf('/dziennik/planlekcji?typ=%s&tydzien=%s', $plan, $weekStart)));
                foreach ($this->parser->students($html) as $student) {
                    $students[$student['id']] = $student;
                }
                $lessons = [...$lessons, ...$this->parser->lessons($html, $isExtra)];
            }
        } catch (HttpExceptionInterface|\RuntimeException $failure) {
            throw TimetableException::unreachable($failure->getMessage());
        }

        return new TimetableWeek(array_values($students), $lessons);
    }

    private function read(HttpBrowser $browser, string $url): string
    {
        $browser->request('GET', $url);
        $content = $browser->getResponse()->getContent();

        if (str_contains($content, 'Podano niepoprawny login i/lub hasło') || str_contains($content, 'przypomnij_haslo_email') || str_contains($content, 'Nie jestes zalogowany')) {
            throw TimetableException::signInRejected();
        }

        return $content;
    }
}
