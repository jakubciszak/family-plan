# Obrazy Docker - Family Plan

## Opis

Projekt Family Plan publikuje gotowe do produkcji obrazy Docker w GitHub Container Registry (ghcr.io).

## Dostępne obrazy

### Backend (PHP/Symfony)
```
ghcr.io/jakubciszak/family-plan-backend:latest
ghcr.io/jakubciszak/family-plan-backend:v1.0.0
```

### Frontend (React)
```
ghcr.io/jakubciszak/family-plan-frontend:latest
ghcr.io/jakubciszak/family-plan-frontend:v1.0.0
```

## Tagowanie

Obrazy są automatycznie budowane i publikowane przy tworzeniu tagów w repozytorium:

- `latest` - najnowsza wersja z domyślnej gałęzi
- `v1.0.0` - konkretna wersja (semantic versioning)
- `v1.0` - minor version
- `v1` - major version

## Publikowanie nowej wersji

Aby opublikować nową wersję obrazów:

```bash
# Utwórz tag z wersją
git tag v1.0.0

# Wypchnij tag do repozytorium
git push origin v1.0.0
```

GitHub Action automatycznie zbuduje i opublikuje obrazy do GitHub Container Registry.

## Użycie w produkcji

### Podstawowe użycie z docker-compose

```yaml
services:
  backend:
    image: ghcr.io/jakubciszak/family-plan-backend:latest
    environment:
      APP_ENV: prod
      APP_SECRET: ${APP_SECRET}
      DATABASE_URL: ${DATABASE_URL}
    ports:
      - "9000:9000"

  frontend:
    image: ghcr.io/jakubciszak/family-plan-frontend:latest
    ports:
      - "3000:80"
```

### Pełna konfiguracja

Dla pełnej konfiguracji z bazą danych i Nginx, użyj `docker-compose.hostinger.yml` jako referencji, zastępując sekcje `build` odpowiednimi obrazami:

```yaml
services:
  php:
    image: ghcr.io/jakubciszak/family-plan-backend:v1.0.0
    # ... reszta konfiguracji

  frontend:
    image: ghcr.io/jakubciszak/family-plan-frontend:v1.0.0
    # ... reszta konfiguracji
```

## Zmienne środowiskowe

### Backend (PHP)

Wymagane:
- `APP_ENV` - środowisko aplikacji (prod)
- `APP_SECRET` - klucz tajny aplikacji
- `DATABASE_URL` - URL połączenia z bazą danych
- `SUPER_ADMIN_EMAIL` - email super admina
- `SUPER_ADMIN_PASSWORD` - hasło super admina

Opcjonalne:
- `SUPER_ADMIN_NAME` - nazwa super admina

### Frontend (React)

Opcjonalne:
- `NGINX_HOST` - host Nginx (domyślnie: localhost)
- `NGINX_PORT` - port Nginx (domyślnie: 80)
- `REACT_APP_API_URL` - URL API backendu

## Autoryzacja do pobrania obrazów

Jeśli repozytorium jest prywatne, wymagana jest autoryzacja:

```bash
# Wygeneruj Personal Access Token (PAT) z uprawnieniami read:packages
# Następnie zaloguj się do ghcr.io
echo $GITHUB_TOKEN | docker login ghcr.io -u USERNAME --password-stdin

# Pobierz obraz
docker pull ghcr.io/jakubciszak/family-plan-backend:latest
```

## Platformy

Obrazy są budowane dla następujących platform:
- `linux/amd64` - procesory x86_64 (Intel/AMD)
- `linux/arm64` - procesory ARM64 (Apple Silicon, AWS Graviton)

## Cache

Workflow wykorzystuje GitHub Actions cache do przyspieszenia budowania obrazów.

## Więcej informacji

- [GitHub Container Registry Documentation](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry)
- [Docker Build Push Action](https://github.com/docker/build-push-action)
