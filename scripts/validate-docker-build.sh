#!/bin/bash
# Skrypt walidacyjny dla konfiguracji Docker
# Sprawdza czy wszystkie wymagane pliki i katalogi istnieją przed budowaniem obrazów

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0

echo "=========================================="
echo "  Walidacja konfiguracji Docker"
echo "=========================================="
echo ""

check_file() {
    local file=$1
    local description=$2
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $description: $file"
        return 0
    else
        echo -e "${RED}✗${NC} $description BRAKUJE: $file"
        ((ERRORS++))
        return 1
    fi
}

check_dir() {
    local dir=$1
    local description=$2
    if [ -d "$dir" ]; then
        echo -e "${GREEN}✓${NC} $description: $dir"
        return 0
    else
        echo -e "${RED}✗${NC} $description BRAKUJE: $dir"
        ((ERRORS++))
        return 1
    fi
}

check_executable() {
    local file=$1
    local description=$2
    if [ -f "$file" ]; then
        if [ -x "$file" ]; then
            echo -e "${GREEN}✓${NC} $description (wykonywalny): $file"
            return 0
        else
            echo -e "${YELLOW}⚠${NC} $description NIE jest wykonywalny: $file"
            echo "  Uruchom: chmod +x $file"
            ((WARNINGS++))
            return 1
        fi
    else
        echo -e "${RED}✗${NC} $description BRAKUJE: $file"
        ((ERRORS++))
        return 1
    fi
}

echo "=== Backend Dockerfile (docker/php/Dockerfile.prod) ==="
check_file "docker/php/Dockerfile.prod" "Backend Dockerfile"
check_file "composer.json" "Composer config"
check_file "composer.lock" "Composer lock"
check_file "symfony.lock" "Symfony lock"
check_file "bin/console" "Symfony console"
check_file "docker/php/docker-entrypoint.sh" "Entrypoint script"
check_executable "docker/php/docker-entrypoint.sh" "Entrypoint script"

echo ""
echo "=== Node Builder Stage (Backend) ==="
check_file "package.json" "NPM package config"
check_file "package-lock.json" "NPM lock file"
check_file "webpack.config.js" "Webpack config"
check_file "babel.config.js" "Babel config"
check_dir "assets" "Assets katalog"
check_dir "public" "Public katalog"

echo ""
echo "=== Frontend Dockerfile (frontend/Dockerfile.prod) ==="
check_file "frontend/Dockerfile.prod" "Frontend Dockerfile"
check_file "frontend/package.json" "Frontend package.json"
check_file "frontend/webpack.config.js" "Frontend webpack config"
check_file "frontend/nginx.conf" "Nginx config"
check_dir "frontend/public" "Frontend public katalog"
check_dir "frontend/src" "Frontend src katalog"

echo ""
echo "=== Docker Compose ==="
check_file "docker-compose.hostinger.yml" "Production docker-compose"
check_file "docker/nginx/default.conf" "Nginx default config"

echo ""
echo "=== GitHub Action Workflow ==="
check_file ".github/workflows/docker-publish.yml" "Docker publish workflow"

echo ""
echo "=========================================="
if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✓ Wszystko OK!${NC} Obrazy Docker mogą być budowane."
    echo ""
    echo "Aby zbudować obrazy lokalnie:"
    echo "  Backend:  docker build -f docker/php/Dockerfile.prod -t family-plan-backend:latest ."
    echo "  Frontend: docker build -f frontend/Dockerfile.prod -t family-plan-frontend:latest ./frontend"
    echo ""
    echo "Aby opublikować obrazy do GitHub Container Registry:"
    echo "  git tag v1.0.0"
    echo "  git push origin v1.0.0"
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠ Znaleziono $WARNINGS ostrzeżeń${NC}"
    echo "Obrazy mogą być budowane, ale zaleca się naprawienie ostrzeżeń."
    exit 0
else
    echo -e "${RED}✗ Znaleziono $ERRORS błędów i $WARNINGS ostrzeżeń${NC}"
    echo "Napraw błędy przed budowaniem obrazów Docker."
    exit 1
fi
