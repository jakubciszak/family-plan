# Deployment na Hostinger

## Architektura

Aplikacja stoi na VPS `srv1201847` (Hostinger KVM 1, Ubuntu 24.04 z Dockerem) jako dwa niezależne projekty Docker Compose.

```
                    internet
                        │
                        ▼
        ┌───────────────────────────────┐
        │  projekt: registry            │
        │  Caddy :80 :443 (TLS)         │
        └───────────────────────────────┘
                        │  sieć proxy-net
                        ▼
        ┌───────────────────────────────┐
        │  family-plan-app              │
        │  FrankenPHP (Caddy + PHP 8.4) │
        │                               │
        │  /api/*, /_components/*       │
        │      → Symfony w worker mode  │
        │  wszystko inne                │
        │      → public/ (React SPA)    │
        └───────────────────────────────┘
                        │  sieć family-plan-network
                        ▼
              family-plan-db (PostgreSQL 16)
```

Zewnętrzny Caddy jest jedynym kontenerem publikującym porty na host. `family-plan-app` nie ma
mapowania portów — jest osiągalny wyłącznie przez `proxy-net`. Baza nie jest wystawiona na zewnątrz.

### Dlaczego jeden kontener

FrankenPHP to Caddy z wbudowanym PHP, więc ten sam proces serwuje API i statyczne pliki SPA.
Zniknęły przez to dwa kontenery nginx (`family-plan-nginx` i `family-plan-frontend`) oraz komunikacja
przez FastCGI. SPA i API leżą pod jednym originem, więc przeglądarka nie robi już żadnego
zapytania cross-origin do API.

Symfony działa w **worker mode**: kernel bootuje raz przy starcie workera i zostaje w pamięci,
zamiast być budowany od zera przy każdym requeście. Między requestami Symfony resetuje usługi
oznaczone `kernel.reset` (m.in. połączenie Doctrine), co robi `Kernel::terminate()` wspólnie
z `services_resetter`. Liczbę workerów ustawia `FRANKENPHP_NUM_WORKERS` (domyślnie 4).

Projekt `registry` pełni podwójną rolę: terminuje TLS dla aplikacji oraz udostępnia prywatny
rejestr obrazów pod `registry.srv1201847.hstgr.cloud` (basicauth). Rejestr jest pozostałością po
wcześniejszym wariancie deployu — aplikacja pobiera obrazy z `ghcr.io`.

**URL produkcyjny:** https://family-plan.srv1201847.hstgr.cloud

## Jak działa deployment

Deployment jest sterowany przez `.github/workflows/deploy-hostinger.yml` i rusza **wyłącznie po
opublikowaniu wydania** (GitHub release, zdarzenie `release: published`). Push na `main` niczego nie
wdraża. Pre-release nie idzie automatycznie na produkcję.

1. **`resolve-release`** ustala, co wdrażamy: tag wydania i commit, na który wskazuje. Szkic
   wydania albo tag bez wydania kończy się błędem — na produkcję trafia tylko opublikowany release.
   Ten sam job trzyma **bramkę testów**: zanim cokolwiek się zbuduje, sprawdza na commicie wydania
   workflowy `PHPUnit Tests`, `Playwright Tests` i `Mobile App Tests`. Czerwony albo brakujący run
   zatrzymuje deployment; run w trakcie jest odpytywany do 60 minut. Listę wymaganych workflowów i
   limit czasu trzyma `REQUIRED_CHECKS` i `CHECKS_TIMEOUT_MINUTES` na górze pliku workflow.

2. **`build-and-push`** buduje jeden obraz z commita wydania i pushuje go do `ghcr.io` z tagami
   `latest`, `<tag wydania>` i `<sha>`:
   - `ghcr.io/jakubciszak/family-plan-app` — FrankenPHP z Symfony w worker mode, zbudowanym
     React SPA w `public/` i assetami Encore w `public/build/`

3. **`deploy`** woła Hostinger API:
   ```
   POST https://developers.hostinger.com/api/vps/v1/virtual-machines/{VPS_ID}/docker/family-plan-project/update
   ```
   Endpoint pobiera nowe obrazy i odtwarza kontenery, zachowując wolumeny danych.

4. Workflow odpytuje produkcję aż `/` zwróci 200, a `/api/auth/me` zwróci 401 (maks. 5 minut).
   Brak zdrowej odpowiedzi w tym czasie oznacza czerwony build.

Migracje bazy i utworzenie super admina uruchamia `docker/frankenphp/docker-entrypoint.sh` przy
starcie kontenera aplikacji — nie ma osobnego kroku migracyjnego w CI.

### Jak wydać wersję

```bash
# z lokalnego repo, na aktualnym mainie
gh release create v1.2.3 --generate-notes
```

Albo w UI: GitHub → Releases → Draft a new release → tag `v1.2.3` → Publish release. Publikacja
uruchamia deployment; zapisanie szkicu nie.

Ręczne wdrożenie (`Actions → Deploy to Hostinger → Run workflow`) przyjmuje opcjonalny tag:

| Wejście `tag` | Co się wdroży |
|---------------|---------------|
| puste | ostatnie opublikowane wydanie |
| `v1.2.3` | to konkretne wydanie (także pre-release) |
| tag bez wydania / szkic | workflow kończy się błędem |

Bramka testów obowiązuje także przy ręcznym uruchomieniu. Jedyne obejście to zaznaczenie
`skip_checks` — potrzebne wyłącznie przy wycofywaniu starego wydania, któremu GitHub skasował już
runy (retencja logów Actions to 90 dni). Pominięcie bramki ląduje w podsumowaniu runu.

### Wymagane sekrety w GitHub Actions

| Sekret | Opis |
|--------|------|
| `HOSTINGER_API_TOKEN` | Token API Hostingera z uprawnieniami do VPS |
| `HOSTINGER_VPS_ID` | Numeryczne ID maszyny (`1201847`) |

`GITHUB_TOKEN` jest wstrzykiwany automatycznie i wystarcza do pushu na `ghcr.io`.

### Widoczność pakietów

Obraz na `ghcr.io` musi być **publiczny**, ponieważ VPS pobiera go anonimowo: GitHub → Packages →
`family-plan-app` → Package settings → Change visibility → Public. Prywatny pakiet oznacza `denied`
przy pullu i nieudany deploy. Stare pakiety `family-plan-php`, `family-plan-nginx` i
`family-plan-frontend` nie są już budowane i można je usunąć.

## Konfiguracja na serwerze

Plik compose i zmienne środowiskowe projektu `family-plan-project` są trzymane po stronie
Hostingera (Docker Manager), a ich odpowiednik w repo to `docker-compose.hostinger.yml`. Przy
każdej zmianie compose'a w repo trzeba zsynchronizować wersję na serwerze.

Zmienne środowiskowe projektu:

| Zmienna | Uwagi |
|---------|-------|
| `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` | Hasło jest zapisane w wolumenie przy inicjalizacji bazy — zmiana samej zmiennej nie zmienia hasła w PostgreSQL |
| `APP_SECRET` | Klucz podpisujący sesje i CSRF; zmiana wylogowuje wszystkich |
| `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_NAME`, `SUPER_ADMIN_PASSWORD` | Konto zakładane/aktualizowane przy każdym starcie kontenera PHP |
| `REQUIRE_EMAIL_ACTIVATION` | `false` aktywuje konto od razu przy rejestracji; `true` wymaga kliknięcia w link z maila |
| `MAILER_DSN` | `sendgrid://KEY@default`; `null://null` wycisza wysyłkę bez błędu |
| `MAILER_FROM_EMAIL`, `MAILER_FROM_NAME` | Adres nadawcy musi być zweryfikowany w SendGridzie, inaczej wysyłka kończy się odrzuceniem |
| `APP_URL` | Baza linków w mailach, m.in. w zaproszeniach |
| `FRANKENPHP_NUM_WORKERS` | Liczba workerów PHP trzymających kernel Symfony w pamięci; domyślnie 4 |

## Migracja na FrankenPHP — kroki jednorazowe na serwerze

Przejście z `php-fpm + nginx` na FrankenPHP zmienia nazwy kontenerów, więc trzeba raz ruszyć
konfigurację po stronie VPS-a. Bez kroku 2 aplikacja po wdrożeniu zwróci 502 — zewnętrzny Caddy
będzie szukał kontenerów, których już nie ma.

1. **Compose projektu `family-plan-project`** (Docker Manager → projekt → edycja compose).
   Wklej treść `docker-compose.hostinger.yml` z repo. Serwisy `php`, `nginx` i `frontend` znikają,
   zostaje jeden `app`. Wolumen `database_data` nie jest ruszany, więc dane bazy zostają.

2. **Routing w zewnętrznym Caddym** (projekt `registry`). Dotychczas host aplikacji był rozdzielany
   na dwa upstreamy — `/api/*` szło do `family-plan-nginx`, reszta do `family-plan-frontend`.
   Teraz cały host idzie do jednego kontenera, bo FrankenPHP sam rozdziela API od SPA:

   ```
   family-plan.srv1201847.hstgr.cloud {
       reverse_proxy family-plan-app:80
   }
   ```

   Poprzednie bloki `handle /api/*` i `handle` z osobnymi upstreamami należy usunąć.

3. **Widoczność pakietu** `ghcr.io/jakubciszak/family-plan-app` na Public (patrz sekcja wyżej) —
   inaczej VPS nie pobierze obrazu.

4. **Wydanie**: `gh release create vX.Y.Z --generate-notes`. Po deployu smoke test jak niżej.

Stare kontenery (`family-plan-php`, `family-plan-nginx`, `family-plan-frontend`) znikną przy
`update` projektu. Gdyby zostały jako osierocone, usuwa je `docker rm -f <nazwa>` na maszynie.

## Operacje

Wszystko poniżej wykonuje się przez Hostinger API (albo przez panel Docker Manager).
`{VPS_ID}` to `1201847`, bazowy URL to `https://developers.hostinger.com/api/vps/v1`.

```bash
# Stan projektów i kontenerów
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/virtual-machines/$VPS_ID/docker"

# Logi (ostatnie 300 wpisów ze wszystkich serwisów)
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/virtual-machines/$VPS_ID/docker/family-plan-project/logs"

# Ręczny redeploy
curl -X POST -H "Authorization: Bearer $TOKEN" \
  "$BASE/virtual-machines/$VPS_ID/docker/family-plan-project/update"

# Restart bez pobierania obrazów
curl -X POST -H "Authorization: Bearer $TOKEN" \
  "$BASE/virtual-machines/$VPS_ID/docker/family-plan-project/restart"
```

### Smoke test po wdrożeniu

```bash
curl -o /dev/null -w '%{http_code}\n' https://family-plan.srv1201847.hstgr.cloud/
curl -o /dev/null -w '%{http_code}\n' https://family-plan.srv1201847.hstgr.cloud/api/auth/me   # 401
curl -sL -o /dev/null -w '%{http_code}\n' https://family-plan.srv1201847.hstgr.cloud/api/doc
```

## Dostęp do bazy produkcyjnej

Postgres jest opublikowany na VPS-ie **wyłącznie na pętli zwrotnej**
(`127.0.0.1:5432`), więc z internetu jest niewidoczny. Jedyna droga to tunel SSH:

```bash
ssh -f -N -L 15432:127.0.0.1:5432 root@72.62.50.155
```

Potem klient łączy się z `localhost:15432`, baza `familyplan`, użytkownik `familyplan`.
Hasło jest w zmiennych projektu w Docker Managerze.

Tunel zamyka się przez `pkill -f "15432:127.0.0.1:5432"`. Port po stronie VPS-a zmienia
zmienna `POSTGRES_HOST_PORT`.

Klienta warto ustawić w trybie **read-only** — to jest żywa baza, bez automatycznych
backupów. Jedyna kopia to ręczny snapshot maszyny, jeden na maszynę.

## Rollback

Obrazy są tagowane także tagiem wydania i sha commita, więc wycofanie sprowadza się do wskazania
poprzedniej wersji. W zmiennych projektu na serwerze ustaw `IMAGE_TAG` na tag działającego wydania
(np. `v1.2.2`) albo na jego sha i uruchom `update`. Compose domyślnie używa `latest`, gdy
`IMAGE_TAG` nie jest ustawiony.

Drugą drogą jest wydanie poprawki: `gh release create v1.2.4` na commicie, który działa — wtedy
`latest` znów wskazuje zdrową wersję i nie trzeba trzymać `IMAGE_TAG` na sztywno. Ta droga wymaga
zielonych testów na commicie poprawki; ustawienie `IMAGE_TAG` nie wymaga niczego, bo dzieje się
poza CI.

Pełny snapshot maszyny (`POST $BASE/virtual-machines/$VPS_ID/snapshot`) obejmuje także wolumen
bazy i jest najszybszą drogą powrotu po nieudanej migracji. Hostinger trzyma jeden snapshot na
maszynę — nowy nadpisuje poprzedni.

## Znane ograniczenia

- Brak automatycznych backupów bazy. Snapshot VPS-a jest ręczny i jest tylko jeden.
- Aplikacja działa na subdomenie `*.hstgr.cloud`; do konta Hostingera nie jest podpięta żadna
  własna domena.
- Rejestr `registry.srv1201847.hstgr.cloud` nie ma garbage collection.
