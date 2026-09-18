# Co zostało do dokończenia

Stan na 2026-09-18. Poniższe zmiany są gotowe lokalnie; nie zostały jeszcze
zacommitowane ani wdrożone.

## Zrobione

### Edycja zatwierdzonych wykonań przez admina

W karcie dnia admin może usunąć zatwierdzone wykonanie albo zmienić jego datę.
Zmiana daty działa tylko w tym samym tygodniu poniedziałek–niedziela i nie pozwala
wybrać przyszłości. Zamknięty tydzień kieszonkowego blokuje obie operacje w API,
a interfejs ukrywa przyciski korekty. Uprawnienia sprawdzane są dla zespołu
właściciela szablonu zadania.

Usunięcie wykonania i korekta salda punktów są zapisywane w jednej transakcji.
Księga zachowuje przyznanie punktów i przeciwny wpis korekty. Zmiana daty wpływa
na kalendarz oraz obliczenia oparte na dacie wykonania, bez ponownego przyznania
punktów. Bonusy już wypłacone pozostają bez zmian; admin może je cofnąć osobnym
koszem w karcie dnia.

Endpointy: `PUT /api/task-executions/{id}` z `doneOn` oraz
`DELETE /api/task-executions/{id}`.

### Martwy `PointsStreak::longest`

Usunięta metoda i testy dotyczące wyłącznie tej metody. `current` i `aliveOn`
pozostają wraz z testami.

### Testy kalendarza z prawdziwym API

Naprawione W1, W2, W4 i W5 w
`frontend/tests/e2e/real-api/week-calendar-real.spec.js`. Scenariusze wybierają
jawnie rodzinny zespół członka, zamiast jego domyślnej prywatnej rodziny.
W1 sprawdza kalendarz członka; rodzic na swoim ekranie widzi kalendarze dzieci.

Dodany W8: zmiana daty, usunięcie wykonania i sprawdzenie salda.
Wszystkie 8 scenariuszy przechodzi lokalnie. Zestaw jest dodany do osobnego
zadania w `.github/workflows/playwright.yml`; sam przebieg GitHub Actions
nie został jeszcze uruchomiony.

### Wybór rodziny w kieszonkowym

`Households::sharedWithAdmin` używa teraz tego samego stabilnego porządku co
`teamOf`: najpierw członkostwo bez roli admina, potem najnowsze dołączenie,
na końcu identyfikator zespołu. Test obejmuje różną kolejność członkostw
zwróconych przez repozytorium.

## Przyjęte decyzje

- Legacy `/api/tasks` zostaje dla kompatybilności. Web i mobilka nie wywołują
  tego endpointu. Nie dołączono mu mechanizmu bonusów opartego na wykonaniach;
  usunięcie całego starszego API powinno być osobną zmianą.
- Reguła „15 pkt w tygodniu” nadal obejmuje poniedziałek–niedzielę, spójnie
  z zamykaniem kieszonkowego. Ograniczenie do pn–pt wymaga osobnego wymagania.
- Historyczne bonusy i kwoty zamkniętych tygodni nie są przeliczane.

## Weryfikacja

- 147 testów domeny/aplikacji TaskManagement i Allowance: OK.
- 44 testy TaskExecutionApiTest: OK (42 w pełnym przebiegu oraz 2 dopisane
  testy blokad w osobnym przebiegu).
- 31 testów PointsCalendarApiTest i AllowanceApiTest: OK.
- 8 testów Playwright kalendarza z prawdziwym API: OK.

Testy API uruchomiono na oddzielnej lokalnej bazie PostgreSQL. Playwright
korzystał z oddzielnej bazy SQLite i lokalnego serwera API.
