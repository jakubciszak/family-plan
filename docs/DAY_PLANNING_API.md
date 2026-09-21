# Plan dnia: kontrakt implementacji

Wspólny kontrakt backendu, webu i mobile. Daty `start`/`end` w odpowiedziach są ISO 8601 z offsetem. Prawa i zajętość wylicza serwer.

## Wydarzenia

`POST /api/day-planning/events`, `PATCH /api/day-planning/events/{id}`:

```json
{
  "title": "Plan lekcji",
  "description": "",
  "location": "",
  "teamId": "uuid lub null",
  "visibility": "PRIVATE",
  "schedule": {
    "kind": "TIMED",
    "localStart": "2026-09-21T08:00",
    "durationMinutes": 60,
    "timeZone": "Europe/Warsaw"
  },
  "recurrence": {
    "frequency": "WEEKLY",
    "interval": 1,
    "byDay": [1, 2, 3, 4, 5],
    "until": "2026-12-31",
    "count": null
  },
  "participantIds": ["uuid"],
  "tagIds": ["uuid"],
  "blocksTime": true,
  "ownerParticipates": true
}
```

`recurrence: null` oznacza jednorazowe wydarzenie. `byDay` to ISO 1–7. `until` i `count` są opcjonalne, wzajemnie wykluczające. `frequency`: `DAILY`/`WEEKLY`. Dla całodniowego wpisu: `schedule: {kind: "ALL_DAY", startDate: "2026-09-21", endDate: "2026-09-22", timeZone: "Europe/Warsaw"}`; koniec wyłączny. Opcjonalne `schedule.utcOffset`, np. `"+01:00"`, wskazuje konkretną godzinę przy jesiennej zmianie czasu. Domyślnie wybierane jest pierwsze wystąpienie. Nieistniejąca godzina pojedynczego wpisu jest odrzucana; w cyklu taki dzień jest pomijany.

Autor zawsze pochodzi z sesji. Domyślnie uczestniczy w wydarzeniu, a `participantIds` uzupełniane jest o jego identyfikator. `ownerParticipates: false` wypisuje autora z listy uczestników: wydarzenie nie zajmuje mu czasu i nie pojawia się w jego kalendarzu, ale pozostaje jego własnością — tylko on je czyta, edytuje i odwołuje. Wymaga `teamId`, przynajmniej jednego uczestnika i roli administratora w tym zespole; inaczej 403 `access_denied`. Pole jest zapamiętywane, więc `PATCH` bez niego nie dopisuje autora z powrotem. `visibility`: `PRIVATE`/`TEAM`. `teamId` wymagane przy zapraszaniu innych lub `TEAM`.

Odpowiedź zapisu i `GET /events/{id}`: definicja wydarzenia z powyższymi polami oraz `id`, `ownerId`, `version`, `participants: [{personId,status}]`, gdzie status to `INCLUDED`/`DECLINED`. Definicja zawiera także `exceptions: {"<originalKey>": {changes: {...}} | {cancelled: true}}`, wyłącznie dla autora. GET definicji wyłącznie dla autora. Zmiany wymagają `If-Match: "<version>"`; 412 oznacza nowszą wersję, a 428 brak wymaganej wersji. POST przyjmuje `Idempotency-Key` (UUID), który klient zachowuje przy ponowieniu tej samej próby.

Kolizja zwraca 409 `{code:"planning_conflict", conflicts:[{personId,start,end}], confirmationToken:"..."}`. Klient pokazuje konflikt i przy ponowieniu identycznego zapisu dodaje `conflictConfirmation: "<token>"`. Zmiana harmonogramu serii z wyjątkami zwraca 409 `{code:"exceptions_reset_required"}`; po potwierdzeniu skutków klient dodaje `resetExceptions:true`.

`DELETE /events/{id}` z `If-Match` anuluje serię, 204.

`PUT /events/{id}/exceptions/{occurrenceKey}` z `If-Match` i `{cancelled:true}` anuluje pojedyncze wystąpienie, lub `{changes:{title,schedule,participantIds,tagIds,visibility,description,location,blocksTime}}` nadpisuje wybrane pola. Kolejna edycja scala zmiany z istniejącym wyjątkiem, zachowując wcześniejsze przesunięcie i pozostałe pola. Odpowiedź: aktualna definicja i wersja serii. Częstotliwość zmieniana tylko na poziomie serii. `DELETE` tego samego endpointu przywraca wystąpienie i zwraca aktualną definicję. Przy kolizji przyjmuje opcjonalne JSON body `{conflictConfirmation:"<token>"}`.

`PUT /events/{id}/participation/me` z `{status:"DECLINED",occurrenceKey:null}` dotyczy serii; konkretny klucz ogranicza do wystąpienia. Odpowiedź `{status,occurrenceKey}`. Rezygnacja nie usuwa zaproszenia ani dostępu do szczegółów. `INCLUDED` pozwala wrócić do udziału; powrót może wymagać tego samego potwierdzenia kolizji, przez `conflictConfirmation` w body.

## Kalendarz

`GET /api/day-planning/calendar?from=<ISO>&to=<ISO>&teamId=<uuid>&personIds[]=<uuid>&tagIds[]=<uuid>`.

Brak `teamId` i osób: mój plan. Inne osoby wymagają aktualnego wspólnego zespołu. Zakres maksymalnie 90 dni. Tagi filtrują tylko dostępne szczegóły.

```json
{
  "events": [{
    "id": "uuid", "occurrenceKey": "2026-09-21T08:00",
    "title": "Plan lekcji", "description": "", "location": "",
    "ownerId": "uuid", "teamId": "uuid", "visibility": "TEAM",
    "start": "2026-09-21T06:00:00+00:00", "end": "2026-09-21T07:00:00+00:00",
    "allDay": false, "timeZone": "Europe/Warsaw",
    "participantIds": ["uuid"], "participants": [{"personId":"uuid","status":"INCLUDED"}],
    "tags": [{"id":"uuid","name":"Plan lekcji","color":"#226a4c"}],
    "blocksTime": true, "recurring": true,
    "canEdit": false, "canChangeParticipation": true,
    "participation": "INCLUDED", "version": 1
  }],
  "busy": [{"kind":"busy","personId":"uuid","start":"2026-09-21T08:00:00+00:00","end":"2026-09-21T09:00:00+00:00"}],
  "coverage": {"from":"ISO","to":"ISO","complete":true}
}
```

Jedno wystąpienie jest raz w `events`, nawet gdy dotyczy kilku wybranych osób. `occurrenceKey: "single"` dla jednorazowego wpisu. Busy bez jakiegokolwiek identyfikatora wydarzenia czy tagu. `GET /events/{id}/occurrences/{occurrenceKey}` zwraca pojedynczy wpis w tym samym kształcie. Nieuprawniony odczyt: 404. Odpowiedzi kalendarza i szczegółów nie są publicznie cachowane.

## Wspólne terminy

`POST /api/day-planning/planning/suggestions`:

```json
{"teamId":"uuid","personIds":["uuid"],"from":"2026-09-21T00:00:00+02:00","to":"2026-09-28T00:00:00+02:00","durationMinutes":60,"windowStart":"08:00","windowEnd":"20:00","timeZone":"Europe/Warsaw"}
```

Odpowiedź `{slots:[{start,end}],personIds:[...],busy:[{kind,personId,start,end}],coverage:{from,to,complete:true}}`. Autor obowiązkowo dodany. Maksymalnie 20 propozycji, starty co 15 minut. Globalna zajętość obejmuje wszystkie zespoły i nie zależy od filtra tagów.

`POST /api/day-planning/availability/query`: ten sam zakres i osoby; zwraca `{busy,personIds,coverage}`.

## Tagi

`GET /api/day-planning/tags?teamId=<uuid>` zwraca `{tags:[{id,name,color,scope,teamId,canEdit}]}`: osobiste tagi odbiorcy oraz wybranego zespołu. Przy braku zespołu tylko osobiste.

`POST /tags` i `PATCH /tags/{id}` przyjmują `{name,color,scope:"PERSONAL"|"TEAM",teamId:null|uuid}` i zwracają tag. `DELETE /tags/{id}` archiwizuje (204). Zespołowymi zarządza admin zespołu, osobistymi właściciel.

Błędy walidacji: 422 `{code:"validation_failed",message:"..."}`. Brak dostępu do wybranego zespołu/osób: 403. Inne błędy mają opis `message` zgodnie z konwencją API aplikacji.


## Zakres sprawdzania kolizji serii

Kalendarz i planowanie zwracają pełny wynik dla żądanego zakresu do 90 dni. Niekompletny odczyt nigdy nie oznacza wolnego terminu.

Zapis cyklu sprawdza pierwsze 90 dni od początku serii, najbliższe 90 dni od chwili zapisu oraz daty wyjątków. Nieskończonego cyklu nie można sprawdzić w całości. Odpowiedź zapisu i błąd kolizji zawierają `conflictCoverage: {ranges:[{from,to}],complete:boolean}`; klient informuje o ograniczonym zakresie. Brak ostrzeżenia nie gwarantuje braku kolizji w całej przyszłości.

## Integracje i prywatność

Odejście lub usunięcie z zespołu atomowo odwołuje zaproszenia tej osoby. Ponowne dołączenie nie przywraca dawnych zaproszeń. Własne wydarzenia pozostają prywatne i osobiste. Wydarzenie, w którym autor nie uczestniczy, zostaje odwołane, gdy z zespołu wychodzi autor albo ostatni uczestnik — nie ma już dla kogo trwać. Istniejące etykiety zachowują historię; nie można dopisać nowych tagów obcego zespołu.

Powiadomienia są neutralne i kierują do `/day-planning`, bez tytułu, uczestników ani identyfikatora wydarzenia. Kolejka push ponownie sprawdza dostęp przed wysyłką. Klienci odświeżają kalendarz po powrocie do aplikacji i powiadomieniu o zmianie.

Migracje: `Version20260921120000` tworzy model kalendarza, tagi i zapisy idempotencji; `Version20260921140100` dodaje Plan dnia do istniejących ustawień nawigacji. Użytkownik może później ukryć tę pozycję.
