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

Deployment jest w pełni automatyczny — sterowany przez `.github/workflows/deploy-hostinger.yml`,
uruchamiany przy każdym pushu na `main` oraz ręcznie przez `workflow_dispatch`.

1. **`build-and-push`** buduje trzy obrazy i pushuje je do `ghcr.io` z tagami `latest` i `<sha>`:
   - `ghcr.io/jakubciszak/family-plan-php` — Symfony + PHP-FPM
   - `ghcr.io/jakubciszak/family-plan-nginx` — nginx z plikami z `public/` obrazu PHP
   - `ghcr.io/jakubciszak/family-plan-frontend` — zbudowany React SPA

2. **`deploy`** woła Hostinger API:
   ```
   POST https://developers.hostinger.com/api/vps/v1/virtual-machines/{VPS_ID}/docker/family-plan-project/update
   ```
   Endpoint pobiera nowe obrazy i odtwarza kontenery, zachowując wolumeny danych.

3. Workflow odpytuje produkcję aż `/` zwróci 200, a `/api/auth/me` zwróci 401 (maks. 5 minut).
   Brak zdrowej odpowiedzi w tym czasie oznacza czerwony build.

Migracje bazy i utworzenie super admina uruchamia `docker/php/docker-entrypoint.sh` przy starcie
kontenera PHP — nie ma osobnego kroku migracyjnego w CI.

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

Obrazy są tagowane również sha commita, więc wycofanie sprowadza się do wskazania poprzedniego
taga. W zmiennych projektu na serwerze ustaw `IMAGE_TAG` na sha działającej wersji i uruchom
`update`. Compose domyślnie używa `latest`, gdy `IMAGE_TAG` nie jest ustawiony.

Pełny snapshot maszyny (`POST $BASE/virtual-machines/$VPS_ID/snapshot`) obejmuje także wolumen
bazy i jest najszybszą drogą powrotu po nieudanej migracji. Hostinger trzyma jeden snapshot na
maszynę — nowy nadpisuje poprzedni.

## Znane ograniczenia

- Brak automatycznych backupów bazy. Snapshot VPS-a jest ręczny i jest tylko jeden.
- Aplikacja działa na subdomenie `*.hstgr.cloud`; do konta Hostingera nie jest podpięta żadna
  własna domena.
- Rejestr `registry.srv1201847.hstgr.cloud` nie ma garbage collection.
