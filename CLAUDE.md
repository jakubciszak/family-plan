# CLAUDE.md - Family Plan Project Guide

## Project Overview

Full-stack monorepo aplikacji do zarządzania zadaniami rodzinnymi z systemem punktów i nagród.

**Architektura:** Separowana (Frontend + Backend + Mobile)
- **Backend:** Symfony 7.4, PHP 8.3+, PostgreSQL 16 - Hexagonal Architecture z DDD i CQRS
- **Frontend:** React 18.2, Webpack 5
- **Mobile:** React Native 0.76, TypeScript

## Quick Commands

```bash
# Development
make setup              # Pełna inicjalizacja (uruchom + zainstaluj + migruj + admin)
make up                 # Uruchom wszystkie serwisy
make down               # Zatrzymaj serwisy

# Testy
make backend-test       # PHPUnit + Behat
make phpunit            # Tylko PHPUnit
make behat              # Tylko Behat
make frontend-test      # Playwright E2E

# Baza danych
make db-migrate         # Uruchom migracje
make db-reset           # Reset DB (drop + create + migrate)
make db-diff            # Wygeneruj nową migrację

# Shell
make shell-php          # Dostęp do kontenera PHP
make shell-db           # Dostęp do psql

# Logi
docker compose logs -f php       # Backend logs
docker compose logs -f frontend  # Frontend logs
```

## Project Structure

```
/
├── src/                           # Backend Symfony (Hexagonal Architecture)
│   ├── UserManagement/            # Bounded Context: użytkownicy, auth
│   ├── TaskManagement/            # Bounded Context: zadania
│   ├── PointsManagement/          # Bounded Context: punkty i nagrody
│   ├── TeamManagement/            # Bounded Context: zespoły
│   ├── UserSettings/              # Bounded Context: ustawienia
│   ├── Shared/                    # Shared kernel
│   └── Presentation/Api/          # REST API Controllers
├── frontend/                      # React SPA
│   ├── src/pages/                 # Komponenty stron
│   ├── src/components/            # Reusable components
│   ├── src/services/              # API client
│   ├── src/i18n/                  # Tłumaczenia (pl, en)
│   └── tests/e2e/                 # Playwright testy
├── mobile/                        # React Native app
├── config/                        # Symfony config
├── migrations/                    # Doctrine migrations
├── tests/                         # Backend tests (Unit, Integration, Api, Acceptance)
└── translations/                  # Backend i18n (pl, en)
```

## Architecture Patterns

### Backend - Hexagonal/DDD/CQRS

Każdy Bounded Context ma strukturę:
```
UserManagement/
├── Domain/              # Entities, ValueObjects, Events, Repository Interfaces
├── Application/         # Commands, Queries, Handlers
└── Infrastructure/      # Doctrine Repositories, External Services
```

**Przykład tworzenia nowej funkcjonalności:**
1. Zdefiniuj Entity/ValueObject w `Domain/`
2. Utwórz Command/Query w `Application/Command/` lub `Application/Query/`
3. Zaimplementuj Handler
4. Dodaj Repository Interface w `Domain/Repository/`
5. Zaimplementuj Repository w `Infrastructure/Doctrine/`
6. Dodaj Controller w `Presentation/Api/`

### Frontend - Service Pattern

```javascript
// API calls przez apiClient
import apiClient from './services/apiClient';

apiClient.get('/api/tasks')
    .then(data => { ... })
    .catch(error => { ... });
```

## Code Conventions

### PHP (Backend)

```php
declare(strict_types=1);

namespace App\TaskManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tasks')]
final class Task
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'string', length: 255)]
        private string $name
    ) {}

    public static function create(Uuid $id, string $name): self
    {
        return new self($id, $name);
    }
}
```

- Zawsze `declare(strict_types=1)`
- Constructor promotion (PHP 8.1+)
- Named constructors (static factory methods)
- Doctrine ORM attributes
- Final classes gdzie to możliwe

### JavaScript/React (Frontend)

```javascript
import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

function TaskList() {
    const { t } = useTranslation();
    const [tasks, setTasks] = useState([]);

    return (
        <div>
            <h1>{t('tasks.title')}</h1>
            {/* ... */}
        </div>
    );
}

export default TaskList;
```

- Komponenty: PascalCase (`TaskList.jsx`)
- Hooks: `useTranslation`, `useState`, `useEffect`
- Tłumaczenia: `t('key.subkey')`

## Testing

### Backend

```bash
# PHPUnit
docker compose exec php vendor/bin/phpunit
docker compose exec php vendor/bin/phpunit --filter=UserTest

# Behat BDD
docker compose exec php vendor/bin/behat
docker compose exec php vendor/bin/behat --suite=task_management
```

Lokalizacja testów:
- `tests/Unit/` - Unit tests
- `tests/Integration/` - Integration tests
- `tests/Api/` - API tests
- `tests/Acceptance/` - Behat contexts
- `features/` - Behat feature files

### Frontend

```bash
cd frontend
npm run test              # Wszystkie E2E testy
npm run test:headed       # Z widoczną przeglądarką
npm run test:ui           # Playwright UI mode
```

Lokalizacja: `frontend/tests/e2e/*.spec.js`

## Key Configuration Files

| Plik | Opis |
|------|------|
| `config/services.yaml` | Dependency injection, repository bindings |
| `config/packages/security.yaml` | Authentication, roles (ROLE_USER, ROLE_ADMIN) |
| `config/packages/messenger.yaml` | Command/Query bus |
| `frontend/webpack.config.js` | Webpack config, API proxy |
| `compose.yaml` | Docker services (dev) |
| `Makefile` | Project commands |

## Environment Variables

Backend (`.env`):
```env
DATABASE_URL="postgresql://app:!ChangeMe!@database:5432/app"
SUPER_ADMIN_EMAIL=admin@example.com
SUPER_ADMIN_PASSWORD=admin123
MAILER_DSN=smtp://mailpit:1025
```

Frontend (`frontend/.env`):
```env
REACT_APP_API_URL=http://localhost:8080
```

## API Documentation

Swagger UI dostępny pod: `http://localhost:8080/api/doc`

Główne endpointy:
- `POST /api/auth/login` - Logowanie
- `GET /api/auth/me` - Aktualny użytkownik
- `GET /api/tasks` - Lista zadań
- `POST /api/tasks` - Utwórz zadanie
- `GET /api/teams` - Lista zespołów

## Docker Services

| Service | Port | Opis |
|---------|------|------|
| frontend | 3000 | React dev server |
| nginx | 8080 | Backend API gateway |
| php | - | PHP-FPM |
| database | 5432 | PostgreSQL |
| mailpit | 8025 | Email testing UI |

## Internationalization (i18n)

**Backend:** `translations/messages.{pl,en}.yaml`
**Frontend:** `frontend/src/i18n/locales/{en,pl}.json`

Obsługiwane języki: Polski (pl), Angielski (en)

## Common Tasks

### Dodanie nowej migracji
```bash
make db-diff                    # Wygeneruj migrację z entity
make db-migrate                 # Zastosuj migracje
```

### Utworzenie super admina
```bash
make create-admin               # Użyje danych z .env
```

### Czyszczenie cache
```bash
docker compose exec php php bin/console cache:clear
```

### Debugowanie
```bash
docker compose logs -f          # Wszystkie logi
make shell-php                  # Shell PHP
make shell-db                   # Shell PostgreSQL
```

## Quality Assurance - Swiss Cheese Model

Projekt używa pluginu [swiss-cheese](https://github.com/jakubciszak/code-quality-confidence) (Claude Code) - warstwowa strategia jakości: lint, testy, hooki, multi-agent review i human review nakładają się tak, aby defekt musiał przejść przez "dziury" we wszystkich warstwach naraz.

Plugin jest skonfigurowany w `.claude/settings.json`. Przy pierwszym uruchomieniu zainstaluj go:

```
/plugin install swiss-cheese@code-quality-confidence
```

Dostępne komendy:

| Komenda | Opis |
|---------|------|
| `/swiss-cheese:init` | Analiza repo i wygenerowanie konfiguracji warstw ochronnych |
| `/swiss-cheese:review` | Multi-agentowe code review na wspólnym diffie |
| `/swiss-cheese:loop <task>` | Autonomiczna pętla implementacji z bramkami jakości |
| `/swiss-cheese:layer` | Dodanie/dostosowanie warstwy ochronnej |
| `/swiss-cheese:audit` | Audyt warstw wiedzy (docs, ADR, security) |
| `/swiss-cheese:status` | Aktualny stos warstw ochronnych i luki |

## Important Notes

- Backend API zawsze zwraca JSON
- Frontend komunikuje się z backendem przez proxy Webpack (dev) lub nginx (prod)
- Wszystkie ID są typu UUID
- State Pattern używany dla statusów zadań (zobacz `docs/STATE_PATTERN.md`)
- CORS skonfigurowany dla localhost:3000 w dev
