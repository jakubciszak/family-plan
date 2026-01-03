# Reguły Zmian Statusów - Dokumentacja

## Przegląd

Reguły Zmian Statusów to funkcjonalność pozwalająca administratorom definiować reguły kontrolujące, kiedy zadania mogą być przypisane użytkownikom. System wykorzystuje bibliotekę `jakubciszak/rule-engine` (wersja 0.1.1) do ewaluacji reguł, zgodnie z tym samym wzorcem co Reguły Punktów Bonusowych.

## Typy Reguł

### 1. Inne Zadanie Wykonane Dzisiaj
**Typ**: `other_task_completed_today`

Uniemożliwia przypisanie zadania, chyba że inne określone zadanie zostało wykonane dzisiaj.

**Przykład**: 
- "Nie można przypisać sprzątania wieczornego, jeżeli poranne sprzątanie nie zostało dzisiaj zakończone"

**Konfiguracja**:
```json
{
  "requiredTaskTemplateId": "uuid-wymaganego-zadania"
}
```

### 2. Przerwa Od Ostatniego Wykonania
**Typ**: `last_execution_cooldown`

Uniemożliwia przypisanie zadania w ciągu X dni od ostatniego wykonania.

**Przykład**:
- "Nie można przypisać gruntownego sprzątania w ciągu 7 dni od ostatniego wykonania"

**Konfiguracja**:
```json
{
  "cooldownDays": 7
}
```

## Użycie przez API

### Tworzenie Reguły Przerwy

```bash
curl -X POST https://api.example.com/api/status-change-rules \
  -H "Content-Type: application/json" \
  -d '{
    "taskTemplateId": "uuid-szablonu-zadania",
    "name": "Cotygodniowe Gruntowne Sprzątanie",
    "description": "Zapobiega przypisaniu gruntownego sprzątania w ciągu 7 dni od ostatniego wykonania",
    "conditionType": "last_execution_cooldown",
    "conditionConfig": {
      "cooldownDays": 7
    }
  }'
```

### Tworzenie Reguły Wymagania

```bash
curl -X POST https://api.example.com/api/status-change-rules \
  -H "Content-Type: application/json" \
  -d '{
    "taskTemplateId": "uuid-zadania-wieczornego",
    "name": "Wymagana Poranna Gimnastyka",
    "description": "Wieczorny trening można przypisać tylko jeśli wykonano poranną gimnastykę",
    "conditionType": "other_task_completed_today",
    "conditionConfig": {
      "requiredTaskTemplateId": "uuid-porannej-gimnastyki"
    }
  }'
```

## Interfejs Webowy

Administratorzy mogą zarządzać regułami poprzez interfejs webowy:

1. Przejdź do **Reguły Zmian Statusów** w menu administratora
2. Kliknij **Utwórz Regułę** aby dodać nową regułę
3. Wybierz szablon zadania i typ warunku
4. Skonfiguruj parametry warunku
5. Zapisz i aktywuj regułę

### Funkcje UI
- Tworzenie nowych reguł
- Edycja nazwy i opisu istniejących reguł
- Aktywacja/dezaktywacja reguł
- Filtrowanie reguł według szablonu zadania
- Wyświetlanie wszystkich reguł lub tylko aktywnych

## Integracja z Rule Engine

System używa `NestedRuleApi` z biblioteki `jakubciszak/rule-engine`. Ewaluacja reguł odbywa się przez:

1. Zbudowanie kontekstu danych specyficznego dla warunku reguły
2. Skonstruowanie definicji reguły jako zagnieżdżonych tablic
3. Ewaluację używając `NestedRuleApi::evaluate()`

### Przykład dla Przerwy Od Ostatniego Wykonania

```php
// Kontekst
$context = [
    'daysSinceLastExecution' => 5,
    'requiredCooldownDays' => 2
];

// Definicja reguły
$ruleDefinition = [
    '>=' => [
        ['var' => 'daysSinceLastExecution'],
        ['var' => 'requiredCooldownDays']
    ]
];

// Ewaluacja
$result = NestedRuleApi::evaluate($ruleDefinition, $context); // true (5 >= 2)
```

## Architektura

### Warstwa Domenowa
- `StatusChangeRule` - Encja reprezentująca regułę
- `StatusChangeConditionType` - Enum typów warunków
- `StatusChangeConditionConfig` - Konfiguracja warunków
- `StatusChangeRuleEvaluator` - Ewaluator reguł używający rule-engine
- `TaskAssignmentValidator` - Walidator przypisań zadań

### Warstwa Aplikacji
- Komendy: Create, Update, Activate, Deactivate
- Zapytania: GetAll, FindById
- Handlery dla każdej komendy i zapytania

### Warstwa Prezentacji
- `StatusChangeRuleApiController` - Kontroler API REST
- `StatusChangeRulesManagement.jsx` - Komponent React

## Migracja Bazy Danych

```bash
php bin/console doctrine:migrations:migrate
```

## Testowanie

Testy integracyjne znajdują się w:
```
tests/TaskManagement/Application/StatusChangeRuleManagementTest.php
```

Uruchom testy:
```bash
./vendor/bin/phpunit tests/TaskManagement/Application/StatusChangeRuleManagementTest.php
```

## Przyszłe Rozszerzenia

Możliwe usprawnienia:
1. Dodanie więcej typów warunków (np. ograniczenia czasowe, reguły specyficzne dla użytkownika)
2. Integracja walidacji w rzeczywisty przepływ przypisywania zadań
3. Interfejs do symulacji/testowania reguł
4. Wsparcie dla złożonych kombinacji reguł (logika AND/OR)
5. Logowanie audytu dla ewaluacji reguł

## Powiązana Dokumentacja

- Szczegóły techniczne (EN): `STATUS_CHANGE_RULES_IMPLEMENTATION.md`
- Reguły Punktów Bonusowych: `FRONTEND_BONUS_RULES_UI.md`
- Biblioteka Rule Engine: https://github.com/jakubciszak/rule-engine
