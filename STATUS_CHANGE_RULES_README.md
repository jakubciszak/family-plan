# Status Change Rules - Quick Reference

## Co zostało zaimplementowane?

System reguł zmian statusów zadań umożliwiający administratorom definiowanie warunków, które muszą być spełnione przed przypisaniem zadania użytkownikowi.

## Typy reguł

### 1. Inne zadanie wykonane dzisiaj
```json
{
  "conditionType": "other_task_completed_today",
  "conditionConfig": {
    "requiredTaskTemplateId": "uuid-wymaganego-zadania"
  }
}
```

### 2. Przerwa od ostatniego wykonania
```json
{
  "conditionType": "last_execution_cooldown",
  "conditionConfig": {
    "cooldownDays": 7
  }
}
```

## Szybki start

### 1. Uruchom migrację
```bash
php bin/console doctrine:migrations:migrate
```

### 2. Zarządzaj regułami przez UI
- Zaloguj się jako administrator
- Przejdź do "Reguły Zmian Statusów" w menu
- Utwórz nową regułę wybierając szablon zadania i typ warunku

### 3. Lub użyj API
```bash
# Utwórz regułę
curl -X POST /api/status-change-rules \
  -H "Content-Type: application/json" \
  -d '{
    "taskTemplateId": "...",
    "name": "Cooldown 2 dni",
    "description": "...",
    "conditionType": "last_execution_cooldown",
    "conditionConfig": {"cooldownDays": 2}
  }'

# Lista reguł
curl /api/status-change-rules

# Lista reguł dla konkretnego szablonu
curl /api/status-change-rules?taskTemplateId=...
```

## Pliki kluczowe

### Backend
- `src/TaskManagement/Domain/Entity/StatusChangeRule.php`
- `src/TaskManagement/Domain/Service/StatusChangeRuleEvaluator.php` - używa rule-engine
- `src/Presentation/Api/StatusChangeRuleApiController.php`
- `migrations/Version20260103220000.php`

### Frontend
- `frontend/src/pages/StatusChangeRulesManagement.jsx`
- `frontend/src/i18n/locales/{pl,en}.json` - tłumaczenia

### Testy
- `tests/TaskManagement/Application/StatusChangeRuleManagementTest.php`

## Dokumentacja

- 📖 [Pełna dokumentacja (PL)](REGULY_ZMIAN_STATUSOW.md)
- 📖 [Full documentation (EN)](STATUS_CHANGE_RULES_IMPLEMENTATION.md)

## Integracja z rule-engine

Zgodnie z wymaganiami, system używa biblioteki `jakubciszak/rule-engine`:

```php
use JakubCiszak\RuleEngine\Api\NestedRuleApi;

// Budowanie kontekstu
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
$result = NestedRuleApi::evaluate($ruleDefinition, $context); // true
```

## Architektura

```
┌─────────────────────────────────────────────────────┐
│              Presentation Layer                      │
│  ┌──────────────────┐  ┌─────────────────────────┐ │
│  │ React UI         │  │ REST API Controller     │ │
│  │ (Admin tylko)    │  │ (ROLE_ADMIN tylko)      │ │
│  └──────────────────┘  └─────────────────────────┘ │
└─────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────┐
│              Application Layer                       │
│  ┌──────────────┐  ┌────────────┐  ┌─────────────┐ │
│  │ Commands     │  │ Queries    │  │ Handlers    │ │
│  └──────────────┘  └────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────┐
│              Domain Layer                            │
│  ┌─────────────────────────────────────────────┐   │
│  │ StatusChangeRule (Entity)                   │   │
│  │ - id, taskTemplateId, name, description     │   │
│  │ - conditionType, config, isActive           │   │
│  └─────────────────────────────────────────────┘   │
│                                                      │
│  ┌─────────────────────────────────────────────┐   │
│  │ StatusChangeRuleEvaluator (Service)         │   │
│  │ - używa jakubciszak/rule-engine             │   │
│  │ - NestedRuleApi::evaluate()                 │   │
│  └─────────────────────────────────────────────┘   │
│                                                      │
│  ┌─────────────────────────────────────────────┐   │
│  │ TaskAssignmentValidator (Service)           │   │
│  │ - waliduje przed przypisaniem               │   │
│  └─────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────┐
│              Infrastructure Layer                    │
│  ┌────────────────────────────────────────────┐    │
│  │ DoctrineStatusChangeRuleRepository         │    │
│  │ (status_change_rules table)                │    │
│  └────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────┘
```

## Status: ✅ GOTOWE

Wszystkie główne funkcjonalności zostały zaimplementowane zgodnie z wymaganiami z issue.
