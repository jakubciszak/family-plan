# Testowanie lokalne obrazów Docker

Ten dokument opisuje jak przetestować obrazy Docker lokalnie przed ich publikacją.

## Wymagania

- Docker Desktop lub Docker Engine zainstalowany lokalnie
- Minimum 4GB RAM dostępnego dla Docker
- Połączenie z Internetem (do pobierania zależności)

## Szybka walidacja

Przed budowaniem obrazów, uruchom skrypt walidacyjny:

```bash
./scripts/validate-docker-build.sh
```

Skrypt sprawdzi czy wszystkie wymagane pliki i katalogi istnieją.

## Budowanie obrazów lokalnie

### 1. Backend (PHP/Symfony)

```bash
# Zbuduj obraz backendu
docker build -f docker/php/Dockerfile.prod -t family-plan-backend:test .

# Sprawdź czy obraz został utworzony
docker images | grep family-plan-backend
```

**Oczekiwany czas budowania:** 5-10 minut (przy pierwszym budowaniu)

**Rozmiar obrazu:** ~200-300 MB

### 2. Frontend (React)

```bash
# Zbuduj obraz frontendu
docker build -f frontend/Dockerfile.prod -t family-plan-frontend:test ./frontend

# Sprawdź czy obraz został utworzony
docker images | grep family-plan-frontend
```

**Oczekiwany czas budowania:** 3-5 minut (przy pierwszym budowaniu)

**Rozmiar obrazu:** ~50-100 MB

## Uruchamianie kontenerów lokalnie

### Przygotowanie

Utwórz plik `.env.test` z wymaganymi zmiennymi:

```bash
# Skopiuj przykładowy plik
cp .env.prod .env.test

# Edytuj wartości według potrzeb
nano .env.test
```

### Uruchomienie pełnego stacku

```bash
# Uruchom wszystkie serwisy
docker-compose -f docker-compose.hostinger.yml --env-file .env.test up -d

# Sprawdź status kontenerów
docker-compose -f docker-compose.hostinger.yml ps

# Sprawdź logi
docker-compose -f docker-compose.hostinger.yml logs -f
```

### Testowanie endpointów

```bash
# Sprawdź czy backend odpowiada
curl http://localhost:8080/api/health || echo "Endpoint niedostępny"

# Sprawdź czy frontend odpowiada
curl http://localhost:3000 || echo "Frontend niedostępny"
```

## Zatrzymanie i czyszczenie

```bash
# Zatrzymaj kontenery
docker-compose -f docker-compose.hostinger.yml down

# Usuń kontenery i volumeny
docker-compose -f docker-compose.hostinger.yml down -v

# Usuń lokalne obrazy testowe
docker rmi family-plan-backend:test family-plan-frontend:test
```

## Testowanie multi-platform build

GitHub Action buduje obrazy dla platform `linux/amd64` i `linux/arm64`.

Aby przetestować to lokalnie:

```bash
# Włącz buildx (jeśli nie jest włączony)
docker buildx create --use

# Zbuduj dla wielu platform (tylko budowanie, bez push)
docker buildx build \
  --platform linux/amd64,linux/arm64 \
  -f docker/php/Dockerfile.prod \
  -t family-plan-backend:multiplatform \
  .

# Dla frontendu
docker buildx build \
  --platform linux/amd64,linux/arm64 \
  -f frontend/Dockerfile.prod \
  -t family-plan-frontend:multiplatform \
  ./frontend
```

**Uwaga:** Budowanie multi-platform może być znacznie wolniejsze z powodu emulacji.

## Troubleshooting

### Problem: Build kończy się błędem "composer install failed"

**Rozwiązanie:** Sprawdź czy masz aktualne wersje `composer.lock` i `package-lock.json`:

```bash
# Zaktualizuj zależności PHP
composer update --lock

# Zaktualizuj zależności Node
npm install
```

### Problem: Brak połączenia z bazą danych

**Rozwiązanie:** Upewnij się, że kontener bazy danych jest w pełni uruchomiony:

```bash
# Sprawdź logi bazy danych
docker-compose -f docker-compose.hostinger.yml logs database

# Sprawdź czy baza jest gotowa
docker-compose -f docker-compose.hostinger.yml exec database pg_isready
```

### Problem: Frontend nie może połączyć się z backendem

**Rozwiązanie:** Sprawdź konfigurację CORS i zmienne środowiskowe:

```bash
# Sprawdź zmienne środowiskowe frontendu
docker-compose -f docker-compose.hostinger.yml exec frontend env | grep REACT_APP

# Sprawdź logi Nginx
docker-compose -f docker-compose.hostinger.yml logs nginx
```

## Automatyczne testowanie

Możesz zautomatyzować proces testowania tworząc skrypt:

```bash
#!/bin/bash
# test-docker-images.sh

set -e

echo "=== Walidacja konfiguracji ==="
./scripts/validate-docker-build.sh

echo ""
echo "=== Budowanie obrazu backendu ==="
docker build -f docker/php/Dockerfile.prod -t family-plan-backend:test .

echo ""
echo "=== Budowanie obrazu frontendu ==="
docker build -f frontend/Dockerfile.prod -t family-plan-frontend:test ./frontend

echo ""
echo "=== Uruchamianie stacku ==="
docker-compose -f docker-compose.hostinger.yml up -d

echo ""
echo "=== Oczekiwanie na uruchomienie serwisów ==="
sleep 30

echo ""
echo "=== Testowanie endpointów ==="
curl -f http://localhost:8080/api/health || exit 1
curl -f http://localhost:3000 || exit 1

echo ""
echo "=== Wszystkie testy przeszły pomyślnie! ==="
```

## Następne kroki

Po pomyślnym przetestowaniu lokalnie:

1. Commitnij wszystkie zmiany
2. Utwórz tag wersji: `git tag v1.0.0`
3. Wypchnij tag: `git push origin v1.0.0`
4. GitHub Action automatycznie zbuduje i opublikuje obrazy

## Monitorowanie procesu budowania w GitHub Actions

Po wypchnięciu taga, możesz monitorować proces budowania:

1. Przejdź do zakładki "Actions" w repozytorium GitHub
2. Znajdź workflow "Docker Build and Publish"
3. Kliknij na najnowszy run, aby zobaczyć szczegóły
4. Po zakończeniu, obrazy będą dostępne w GitHub Container Registry

## Weryfikacja opublikowanych obrazów

```bash
# Zaloguj się do GitHub Container Registry
echo $GITHUB_TOKEN | docker login ghcr.io -u USERNAME --password-stdin

# Pobierz opublikowany obraz
docker pull ghcr.io/jakubciszak/family-plan-backend:latest

# Uruchom pobrany obraz
docker run -it --rm ghcr.io/jakubciszak/family-plan-backend:latest php --version
```
