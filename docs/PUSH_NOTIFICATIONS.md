# Powiadomienia push

Powiadomienie dociera trzema drogami: jako dymek i wpis na liście w otwartej aplikacji, jako
systemowe powiadomienie przeglądarki (Web Push z VAPID) i jako powiadomienie na telefonie z aplikacją
mobilną, także przy zamkniętej aplikacji (Firebase Cloud Messaging). Web Push to otwarty standard, ten
sam w Chrome, Firefoksie i Safari; FCM jest potrzebny tylko dla aplikacji na Androida.

## Jak to płynie

```
zdarzenie w aplikacji (np. zadanie zatwierdzone)
        │
        ▼
NotificationOrchestrator ──► polityka zdarzenia (admin) ──► ustawienia użytkownika
        │
        ▼  kanał push przeszedł przez obie bramki
NotificationFacade::sendPush(userId)
        │
        ▼
PushNotificationAdapter ──► kolejka ──► DeliverPushHandler (worker)
        │
        ├──► MinishlinkPushSender ──► przeglądarki z push_subscriptions ──► sw.js: showNotification()
        │
        └──► FcmPushSender ──► telefony z native_push_devices ──► tray Androida
```

Push jest zwykłym kanałem obok `email`, `sms` i `in_app`, więc podlega tym samym dwóm bramkom:
polityce zdarzenia, którą ustawia admin, i przełącznikowi kanału w ustawieniach użytkownika.
Trzecią bramką, której pozostałe kanały nie mają, jest zgoda przeglądarki na konkretnym urządzeniu.

## Tylko aktualne powiadomienia

Powiadomienie wie, czego dotyczy, i przestaje wisieć jako nieprzeczytane, gdy nie ma już nic do
powiedzenia:

| Mechanizm | Co robi |
|-----------|---------|
| Temat (`topic`, z parametru `tag`) | `task-<id>`, `payout-<id>`, `streak-at-risk`, `calendar-<hmac>`. Nowsze powiadomienie na ten sam temat zamyka starsze u tego samego odbiorcy: „zatwierdzone” zastępuje „przypisane”. |
| Załatwienie (`resolved_at`) | Zatwierdzenie, cofnięcie, usunięcie albo oddanie zadania do puli zamyka prośby o akceptację u **wszystkich** adminów. Potwierdzenie albo anulowanie wypłaty zamyka „Kieszonkowe czeka”. Zamknięte liczy się jako przeczytane. |
| Wygaśnięcie (`expires_at`) | Ostrzeżenie o serii wygasa o północy, informacja z kalendarza po dwóch dniach. Wygasłe nie wraca jako nieprzeczytane. |
| Czas życia pushu (TTL) | Serwis push trzyma wiadomość dla urządzenia bez sieci godziny, nie domyślne 4 tygodnie: 6–48 h zależnie od zdarzenia (`pushTtl` w `NotificationEvent`), nigdy dłużej niż do `expires_at`. Temat (`Topic` w Web Push, `collapse_key` w FCM) sprawia, że czeka tylko najnowsza wiadomość na ten temat. |
| Kolejka | Worker nie wyśle pushu, jeśli powiadomienie zostało w międzyczasie załatwione, przeczytane albo wygasło, ani jeśli czekało w kolejce dłużej niż TTL. |
| Własne działania | Nikt nie dostaje powiadomienia o tym, co sam zrobił, np. dziecko o zadaniu, które samo wzięło z puli. |

`GET /api/notifications?unread=1` zwraca tylko aktywne powiadomienia, od najnowszych. Każde
powiadomienie ma pola `event`, `topic`, `expiresAt`, `resolvedAt` i `active`.

W otwartej aplikacji (web i mobilka) dymek wyskakuje tylko dla nowości, które przyszły w trakcie
korzystania, i sam znika po 8 sekundach. To, co zebrało się pod nieobecność (dłużej niż 2 minuty w tle),
przychodzi jednym dymkiem zbiorczym, a pojedyncza zaległość pokazuje się normalnie. Dymek, który już
wisi na ekranie, znika albo zbiorczy maleje, gdy jego powiadomienie przestaje być aktualne: ktoś inny
załatwił sprawę, odbiorca przeczytał je na innym urządzeniu albo wygasło. Porównanie odbywa się
na znacznikach czasu serwera, więc zegar urządzenia nie ma znaczenia. Dymek zamyka X, Escape albo
przesunięcie w bok palcem lub myszą. Dzwonek w pasku aplikacji pokazuje liczbę nieprzeczytanych
i otwiera listę, na której załatwione i nieaktualne są oznaczone. Otwarta i widoczna aplikacja zabiera
pushowi rolę dymka, a systemowe powiadomienia, które przestały być aktualne, same znikają z traya
przy następnym odświeżeniu listy.

## Które powiadomienia chcę dostawać

Każdy użytkownik wyłącza w ustawieniach rodzaje, które go nie interesują
(`GET/PUT /api/notifications/preferences`, typ preferencji `notification_events`). Wyłączony rodzaj nie
przychodzi żadnym kanałem. To trzecia bramka po polityce administratora i przełącznikach kanałów.
Wszystko jest domyślnie włączone. Prośby o akceptację widzą w ustawieniach tylko admini zespołów.
E-maile przy zgłoszeniu i zatwierdzeniu zadania są w polityce domyślnie włączone; administrator
aplikacji wyłącza je w „Powiadomieniach” (macierz zdarzeń i kanałów).

## Co wysyła powiadomienie

| Zdarzenie | Kto dostaje | Kiedy | Domyślne kanały |
|-----------|-------------|-------|-----------------|
| `task_completed` | admini **tej** rodziny | dziecko zgłosiło zadanie do akceptacji | e-mail |
| `payout_offered` | dziecko | rodzic zaproponował wypłatę kieszonkowego | w aplikacji, push |
| `streak_at_risk` | każdy, kogo seria się kończy | codziennie o 18:00, gdy seria trwa, a dziś nie ma jeszcze punktów | push |
| `task_approved` | wykonawca zadania | admin zatwierdził zadanie | e-mail |

Kanały każdego z tych zdarzeń admin zmienia w ustawieniach powiadomień — push jest tam zwykłą
pozycją obok e-maila. Domyślne kanały opisuje katalog w `NotificationEvent`.

Powiadomienie o zadaniu do akceptacji trafia do adminów rodziny, do której należy **typ zadania**.
Gdy typ nie jest przypisany do żadnej rodziny, powiadamiani są admini wszystkich rodzin wykonawcy.

### Seria zagrożona

`StreakAtRisk::days()` odpowiada na jedno pytanie: ile dni przepadnie, jeśli dziś nic się nie wydarzy.
Warunek jest celowo wąski — seria musiała trwać **do wczoraj włącznie**, a dziś nie osiągnąć progu
`pointsPerDay` z reguły bonusowej rodziny. Kto serii nie ma, nie dostaje nic; kto dziś już zdobył
punkty, też nie.

Sprawdzenie odpala `app:warn-about-streaks-at-risk`. Komenda przyjmuje `--dry-run`, który wypisuje
kogo by ostrzegła, niczego nie wysyłając — to najszybszy sposób, żeby zobaczyć, czy próg jest dobrze
ustawiony.

## Powiadomienie napisane ręcznie

Poza zdarzeniami aplikacji admin zespołu może napisać własną wiadomość i wysłać ją sam — do całego
zespołu albo do jednej osoby. Służy do tego zakładka „Wyślij powiadomienie", widoczna dla każdego,
kto administruje jakimkolwiek zespołem.

Krąg odbiorców to członkowie zespołów, które nadawca administruje, **bez niego samego**: wysyłka do
wszystkich nie wraca na własny telefon, a do siebie służy próbne powiadomienie w ustawieniach.
Super admin instancji sięga wszystkich użytkowników. Próba napisania do kogoś spoza tego kręgu
kończy się 404 — tak samo jak do konta, którego nie ma.

Taka wiadomość omija obie bramki polityk: nie jest zdarzeniem aplikacji, więc nie ma polityki
kanałów ani przełącznika kanału w ustawieniach użytkownika. Zostaje jedyna bramka, której obejść
się nie da — zgoda przeglądarki na urządzeniu. Kto nie ma zapisanego żadnego urządzenia, nie
dostanie nic, a formularz mówi wprost, ilu domowników da się w ogóle dosięgnąć.

Bez wpisanego tytułu powiadomienie pokazuje „Family Plan". Wysyłka idzie tą samą drogą co reszta —
przez kolejkę i `DeliverPushHandler`. Każda wiadomość ma własny tag, więc druga nie zastępuje w trayu
pierwszej.

## Kolejka i harmonogram

Dotarcie do serwisu push to osobne żądanie HTTP na każde urządzenie, więc wysyłka nie dzieje się
w trakcie obsługi requestu. `PushNotificationAdapter` oddaje `DeliverPushCommand` na magistralę,
a `DeliverPushHandler` — już w workerze — wybiera urządzenia, wysyła i sprząta martwe subskrypcje.

Na produkcji odpowiadają za to dwa dodatkowe kontenery, oba z tego samego obrazu co aplikacja:

| Serwis | Polecenie | Rola |
|--------|-----------|------|
| `worker` | `messenger:consume async` | wysyła to, co czeka w kolejce |
| `cron` | `streak-warnings.sh` | codziennie o `STREAK_WARNING_HOUR` uruchamia ostrzeżenia o seriach |

Kolejka leży w Postgresie (transport Doctrine), więc nie ma tu żadnej nowej infrastruktury do
utrzymania. Tabelę `messenger_messages` transport zakłada sam przy pierwszym uruchomieniu.

Migracje należą wyłącznie do kontenera `app`: worker i cron startują z `RUN_MIGRATIONS=false`, żeby
trzy kontenery nie migrowały bazy jednocześnie.

W środowisku testowym transport jest synchroniczny (`sync://`), więc testy przechodzą całą drogę
aż do sendera — łącznie z krokami, które w kolejce byłyby niewidoczne.

## Klucze VAPID

Serwer podpisuje każde powiadomienie parą kluczy. Bez nich `/api/push/key` zwraca `available: false`,
a UI mówi, że push nie jest skonfigurowany — aplikacja działa normalnie, po prostu nic nie wysyła.

```bash
docker compose exec app php bin/console app:generate-vapid-keys
```

Wynik trafia do zmiennych środowiskowych:

| Zmienna | Uwagi |
|---------|-------|
| `VAPID_PUBLIC_KEY` | Trafia też do przeglądarki przy subskrypcji |
| `VAPID_PRIVATE_KEY` | Zostaje na serwerze |
| `VAPID_SUBJECT` | `mailto:` albo URL — kontakt dla operatora serwisu push |

Zmiana klucza publicznego unieważnia wszystkie subskrypcje, które przeglądarki już trzymają:
urządzenia trzeba wtedy zapisać od nowa.

## Czego wymaga przeglądarka

Push działa tylko po HTTPS (wyjątkiem jest `localhost`). Zgoda musi paść w reakcji na gest
użytkownika, dlatego bierze ją przełącznik w ustawieniach, a nie kod przy starcie aplikacji.

**Na iPhonie i iPadzie aplikacja musi być dodana do ekranu początkowego.** Safari otwarte w
przeglądarce nie dostanie powiadomień — tak działa iOS od 16.4 i nie da się tego obejść. UI wykrywa
ten przypadek i zamiast martwego przełącznika pokazuje, co trzeba zrobić.

## Endpointy

| Metoda | Ścieżka | Do czego |
|--------|---------|----------|
| `GET` | `/api/push/key` | Klucz publiczny do subskrypcji; `available: false`, gdy serwer nie ma kluczy |
| `GET` | `/api/push/subscriptions` | Urządzenia, na które trafiają powiadomienia |
| `POST` | `/api/push/subscriptions` | Zapisanie urządzenia; ponowne zapisanie tego samego odświeża je |
| `DELETE` | `/api/push/subscriptions?endpoint=…` | Wypisanie urządzenia |
| `POST` | `/api/push/test` | Próbne powiadomienie do siebie; 409, gdy nie ma żadnego urządzenia |
| `GET` | `/api/push/audience` | Kogo nadawca może dosięgnąć, z liczbą urządzeń; 403, gdy nie administruje żadnym zespołem |
| `POST` | `/api/push/announcements` | Wiadomość napisana przez admina zespołu; bez `userId` idzie do całego kręgu, 404 poza kręgiem, 409 gdy nikt nie ma urządzenia |
| `POST` | `/api/push/devices` | Telefon z aplikacją: token FCM, platforma `android`, etykieta; ponowne zapisanie przenosi telefon do zalogowanego |
| `DELETE` | `/api/push/devices?token=…` | Wypisanie telefonu, np. przy wylogowaniu |
| `GET` | `/api/notifications?unread=1&limit=…` | Aktywne nieprzeczytane, od najnowszych, z `unreadCount` |
| `POST` | `/api/notifications/read-all` | Oznacza wszystkie jako przeczytane albo tylko te z `{ "ids": [...] }` |
| `GET`/`PUT` | `/api/notifications/preferences` | Rodzaje powiadomień z grupą, stanem, kanałami i tym, czy w ogóle dotyczą użytkownika |

`GET /api/push/key` mówi też, czy serwer ma skonfigurowany FCM (`native: true`).

Endpoint jest unikalny w całej tabeli, nie na użytkownika. To celowe: jeden telefon to jedna
subskrypcja, a gdy zaloguje się na nim ktoś inny i włączy powiadomienia, urządzenie przechodzi do
niego zamiast dublować wpis.

## Martwe subskrypcje

Przeglądarki wygaszają subskrypcje same — po odinstalowaniu PWA, wyczyszczeniu danych albo długiej
nieaktywności. Serwis push odpowiada wtedy 404 albo 410. `PushNotificationAdapter` kasuje taki wpis
przy pierwszej nieudanej wysyłce, więc tabela nie puchnie i nikt nie próbuje w nieskończoność pisać
do nieistniejącego urządzenia. Inne błędy (np. chwilowa niedostępność serwisu) subskrypcji nie ruszają.

## Testowanie na telefonie

Do subskrypcji potrzeba HTTPS, więc `localhost:3000` z telefonu nie wystarczy — najprościej
sprawdzić to na środowisku `dev` (patrz `HOSTINGER_DEPLOYMENT.md`). Po włączeniu przełącznika
w ustawieniach przycisk „Wyślij próbne powiadomienie" wysyła powiadomienie do siebie i jest
najszybszą drogą, żeby zobaczyć, czy cała ścieżka działa.

## Telefony z aplikacją (FCM)

Aplikacja mobilna po zalogowaniu i zgodzie na powiadomienia oddaje serwerowi token Firebase telefonu
(`POST /api/push/devices`), a przy wylogowaniu go wypisuje. Worker wysyła do niego to samo co do
przeglądarek przez FCM HTTP v1: wiadomość typu `notification` na kanale Androida `family-plan`
(wysoki priorytet, więc pojawia się od razu), z danymi `url`, `tag`, `notificationId` i `event`.
Stuknięcie w powiadomienie oznacza je jako przeczytane i otwiera właściwy ekran.

Serwer potrzebuje konta serwisowego projektu Firebase w zmiennej `FCM_SERVICE_ACCOUNT` (JSON albo jego
base64) w kontenerach `app` i `worker`; bez niej telefony po prostu nie dostają pushu. Aplikacja
potrzebuje `google-services.json` z tego samego projektu przy budowaniu APK (sekret `GOOGLE_SERVICES_JSON`).
Kroki są w [mobile/README.md](../mobile/README.md#powiadomienia-przy-zamkniętej-aplikacji).
