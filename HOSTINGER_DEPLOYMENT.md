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
        ┌───────────────┴───────────────┐
        │                               │
   /api/*                          wszystko inne
        │                               │
        ▼                               ▼
   family-plan-nginx  ──fastcgi──▶ family-plan-php
        │                               │
        │  sieć family-plan-network     │
        │                               ▼
        └──────────────────────▶ family-plan-db (PostgreSQL 16)
                                        │
   family-plan-frontend ◀────────────────┘
   (React SPA, serwowany przez nginx)
```

Caddy jest jedynym kontenerem publikującym porty na host. Kontenery `family-plan-*` nie mają
mapowań portów — są osiągalne wyłącznie przez `proxy-net`. Baza nie jest wystawiona na zewnątrz.

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

2. **`build-and-push`** buduje trzy obrazy z commita wydania i pushuje je do `ghcr.io` z tagami
   `latest`, `<tag wydania>` i `<sha>`:
   - `ghcr.io/jakubciszak/family-plan-php` — Symfony + PHP-FPM
   - `ghcr.io/jakubciszak/family-plan-nginx` — nginx z plikami z `public/` obrazu PHP
   - `ghcr.io/jakubciszak/family-plan-frontend` — zbudowany React SPA

3. **`deploy`** woła Hostinger API:
   ```
   POST https://developers.hostinger.com/api/vps/v1/virtual-machines/{VPS_ID}/docker/family-plan-project/update
   ```
   Endpoint pobiera nowe obrazy i odtwarza kontenery, zachowując wolumeny danych.

4. Workflow odpytuje produkcję aż `/` zwróci 200, a `/api/auth/me` zwróci 401 (maks. 5 minut).
   Brak zdrowej odpowiedzi w tym czasie oznacza czerwony build.

Migracje bazy i utworzenie super admina uruchamia `docker/php/docker-entrypoint.sh` przy starcie
kontenera PHP — nie ma osobnego kroku migracyjnego w CI.

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

Obrazy na `ghcr.io` muszą być **publiczne**, ponieważ VPS pobiera je anonimowo. Ustawia się to raz,
osobno dla każdego z trzech pakietów: GitHub → Packages → pakiet → Package settings → Change
visibility → Public. Prywatny pakiet oznacza `denied` przy pullu i nieudany deploy.

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
