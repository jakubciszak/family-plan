# Instrukcje dla Claude - Family Plan

## Struktura projektu

Ten projekt składa się z trzech głównych części:
- **Backend**: Symfony (katalog `src/`, `tests/`)
- **Frontend**: React/JavaScript (katalog `frontend/`)
- **Mobile**: React Native (katalog `mobile/`)

## Zasady implementacji

### 1. Test Driven Development (TDD)

**ZAWSZE** implementuj funkcjonalności zgodnie z podejściem TDD:

1. **Napisz test NAJPIERW** - zanim napiszesz jakikolwiek kod produkcyjny
2. **Uruchom test** - upewnij się, że test pada (Red)
3. **Napisz minimalny kod** - który sprawi, że test przejdzie (Green)
4. **Refaktoryzuj** - popraw kod zachowując przechodzące testy (Refactor)

#### Typy testów do implementacji:

**Backend (PHP/Symfony):**
- **Unit Tests** - testy jednostkowe klas i metod (PHPUnit)
- **Integration Tests** - testy integracyjne serwisów i repozytoriów
- **E2E Tests** - testy end-to-end API (Behat)

**Frontend (React):**
- **Unit Tests** - testy komponentów i funkcji (Jest)
- **Integration Tests** - testy integracji komponentów
- **E2E Tests** - testy całych przepływów użytkownika

**Mobile (React Native):**
- **Unit Tests** - testy komponentów i logiki
- **Integration Tests** - testy nawigacji i integracji
- **E2E Tests** - testy całych scenariuszy użytkownika

### 2. Zasady SOLID

**ZAWSZE** przestrzegaj zasad SOLID przy implementacji:

- **S - Single Responsibility Principle**: Każda klasa powinna mieć tylko jeden powód do zmiany
- **O - Open/Closed Principle**: Klasy powinny być otwarte na rozszerzenia, zamknięte na modyfikacje
- **L - Liskov Substitution Principle**: Podklasy powinny być zamienne z klasami bazowymi
- **I - Interface Segregation Principle**: Wiele małych interfejsów zamiast jednego dużego
- **D - Dependency Inversion Principle**: Zależności od abstrakcji, nie od konkretnych implementacji

#### Praktyczne zastosowanie w projekcie:

- Używaj interfejsów dla repozytoriów i serwisów
- Stosuj Dependency Injection (Symfony Container)
- Dziel duże klasy na mniejsze, wyspecjalizowane
- Unikaj God Objects
- Preferuj kompozycję nad dziedziczenie

### 3. Quality Assurance - Narzędzia jakości kodu

**ZAWSZE** po implementacji funkcjonalności uruchom i popraw wszystkie błędy:

#### Backend (PHP):

```bash
# 1. PHP Code Sniffer - sprawdzanie standardów kodowania
vendor/bin/phpcs src/ tests/

# Automatyczna naprawa (gdy możliwe)
vendor/bin/phpcbf src/ tests/

# 2. PHPStan - statyczna analiza kodu (poziom 8)
vendor/bin/phpstan analyse src tests

# 3. Unit Tests - testy jednostkowe
vendor/bin/phpunit

# 4. Integration Tests - testy integracyjne
vendor/bin/phpunit --testsuite=integration

# 5. E2E Tests - testy end-to-end (Behat)
vendor/bin/behat
```

#### Frontend:

```bash
cd frontend

# 1. ESLint - sprawdzanie standardów kodowania
npm run lint

# 2. Unit & Integration Tests
npm test

# 3. E2E Tests
npm run test:e2e
```

#### Mobile:

```bash
cd mobile

# 1. ESLint - sprawdzanie standardów kodowania
npm run lint

# 2. Unit & Integration Tests
npm test

# 3. E2E Tests (jeśli dostępne)
npm run test:e2e
```

### 4. Sprzątanie po sobie

**ZAWSZE** sprzątaj niepotrzebne rzeczy:

- ❌ **Usuń** zakomentowany kod (nie commituj martwego kodu)
- ❌ **Usuń** nieużywane importy i zmienne
- ❌ **Usuń** pliki tymczasowe i debugowe (console.log, var_dump, dd())
- ❌ **Usuń** nieużywane klasy, metody i funkcje
- ✅ **Zachowaj** czysty, czytelny kod
- ✅ **Upewnij się** że `.gitignore` jest aktualne

#### Checklist przed commitem:

- [ ] Brak zakomentowanego kodu
- [ ] Brak console.log, var_dump, dd()
- [ ] Brak nieużywanych importów
- [ ] Brak nieużywanych zmiennych
- [ ] Wszystkie testy przechodzą
- [ ] PHP Code Sniffer OK
- [ ] PHPStan OK
- [ ] ESLint OK (frontend/mobile)

### 5. Workflow implementacji funkcjonalności

Przykładowy workflow dla nowej funkcjonalności:

```
1. Zrozum wymagania
2. Zaplanuj architekturę (SOLID)
3. Napisz testy (TDD - Red)
   - Unit tests
   - Integration tests
   - E2E tests
4. Implementuj kod (TDD - Green)
5. Refaktoryzuj (TDD - Refactor)
6. Uruchom wszystkie narzędzia QA:
   - PHP Code Sniffer
   - PHPStan
   - Unit tests
   - Integration tests
   - E2E tests
7. Popraw wszystkie błędy i ostrzeżenia
8. Sprzątaj kod (usuń niepotrzebne rzeczy)
9. Zrób commit
10. Push zmian
```

### 6. Standardy commitów

Używaj konwencji Conventional Commits:

```
feat: dodanie nowej funkcjonalności
fix: naprawa błędu
test: dodanie lub modyfikacja testów
refactor: refaktoryzacja kodu
docs: aktualizacja dokumentacji
style: formatowanie kodu (bez zmian logiki)
chore: aktualizacja zależności, konfiguracji
```

Przykłady:
```
feat: add task assignment to team members
fix: resolve null pointer in task repository
test: add integration tests for team service
refactor: extract task validation to separate class
```

## Struktura plików

### Backend (Symfony):
- `src/` - kod źródłowy
- `tests/Unit/` - testy jednostkowe
- `tests/Integration/` - testy integracyjne
- `features/` - testy E2E (Behat)

### Frontend:
- `frontend/src/` - kod źródłowy React
- `frontend/src/__tests__/` - testy

### Mobile:
- `mobile/src/` - kod źródłowy React Native
- `mobile/__tests__/` - testy

## Przydatne komendy

### Makefile shortcuts:
```bash
make test          # Uruchom wszystkie testy backend
make phpcs         # PHP Code Sniffer
make phpstan       # PHPStan
make qa            # Wszystkie narzędzia QA
```

## Pamiętaj!

> "Code without tests is legacy code" - zawsze pisz testy!

> "Clean code is not written by following a set of rules. You know you are working on clean code when each routine you read turns out to be pretty much what you expected." - Uncle Bob

> "Any fool can write code that a computer can understand. Good programmers write code that humans can understand." - Martin Fowler
