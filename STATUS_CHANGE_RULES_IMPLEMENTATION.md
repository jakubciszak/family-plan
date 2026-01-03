# Status Change Rules - Implementation Documentation

## Overview

Status Change Rules (Reguły Zmian Statusów) is a feature that allows administrators to define rules controlling when tasks can be assigned to users. The system uses the `jakubciszak/rule-engine` library (version 0.1.1) for rule evaluation, following the same pattern as Bonus Points Rules.

## Features

### Supported Rule Conditions

1. **Other Task Completed Today** (`other_task_completed_today`)
   - Prevents task assignment unless another specific task was completed today
   - Example: "Cannot assign evening cleanup if morning cleanup wasn't done today"

2. **Last Execution Cooldown** (`last_execution_cooldown`)
   - Prevents task assignment within X days of last execution
   - Example: "Cannot assign heavy cleaning task within 7 days of last execution"

## Architecture

### Domain Layer

- **Entity**: `StatusChangeRule` - Main entity representing a rule
- **Value Objects**:
  - `StatusChangeConditionType` - Enum for rule types
  - `StatusChangeConditionConfig` - Configuration for rule conditions
- **Services**:
  - `StatusChangeRuleEvaluator` - Evaluates rules using rule-engine library
  - `TaskAssignmentValidator` - Validates assignments against active rules
- **Exception**: `StatusChangeRuleViolationException` - Thrown when rule is violated

### Application Layer

Commands:
- `CreateStatusChangeRuleCommand` / `CreateStatusChangeRuleHandler`
- `UpdateStatusChangeRuleCommand` / `UpdateStatusChangeRuleHandler`
- `ActivateStatusChangeRuleCommand` / `ActivateStatusChangeRuleHandler`
- `DeactivateStatusChangeRuleCommand` / `DeactivateStatusChangeRuleHandler`

Queries:
- `GetAllStatusChangeRulesQuery` / `GetAllStatusChangeRulesQueryHandler`
- `FindStatusChangeRuleByIdQuery` / `FindStatusChangeRuleByIdQueryHandler`

### Infrastructure Layer

- `DoctrineStatusChangeRuleRepository` - Doctrine ORM implementation
- `InMemoryStatusChangeRuleRepository` - In-memory implementation for testing

### Presentation Layer

- **API**: `StatusChangeRuleApiController` - RESTful API endpoints
- **Frontend**: `StatusChangeRulesManagement.jsx` - React component for rule management

## Database Schema

```sql
CREATE TABLE status_change_rules (
    id UUID NOT NULL,
    task_template_id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    condition_type VARCHAR(50) NOT NULL,
    config JSON NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    PRIMARY KEY(id)
);

CREATE INDEX IDX_status_change_rules_is_active ON status_change_rules (is_active);
CREATE INDEX IDX_status_change_rules_task_template_id ON status_change_rules (task_template_id);
```

## API Endpoints

### List Rules
```
GET /api/status-change-rules
Query Parameters:
  - active: boolean (optional) - filter by active status
  - taskTemplateId: UUID (optional) - filter by task template
```

### Get Rule by ID
```
GET /api/status-change-rules/{id}
```

### Create Rule
```
POST /api/status-change-rules
Body:
{
  "taskTemplateId": "uuid",
  "name": "string",
  "description": "string",
  "conditionType": "other_task_completed_today" | "last_execution_cooldown",
  "conditionConfig": {
    // For other_task_completed_today:
    "requiredTaskTemplateId": "uuid"
    
    // For last_execution_cooldown:
    "cooldownDays": integer
  }
}
```

### Update Rule
```
PUT /api/status-change-rules/{id}
Body:
{
  "name": "string",
  "description": "string"
}
```

### Activate/Deactivate Rule
```
POST /api/status-change-rules/{id}/activate
POST /api/status-change-rules/{id}/deactivate
```

## Rule Engine Integration

The system uses `NestedRuleApi` from `jakubciszak/rule-engine` library. Rules are evaluated by:

1. Building context data specific to the rule condition
2. Constructing rule definition as nested arrays
3. Evaluating using `NestedRuleApi::evaluate()`

Example rule definition for Last Execution Cooldown:
```php
$ruleDefinition = [
    '>=' => [
        ['var' => 'daysSinceLastExecution'],
        ['var' => 'requiredCooldownDays']
    ]
];

$context = [
    'daysSinceLastExecution' => 5,
    'requiredCooldownDays' => 2
];

$result = NestedRuleApi::evaluate($ruleDefinition, $context); // true
```

## Usage Example

### Creating a Cooldown Rule via API

```bash
curl -X POST https://api.example.com/api/status-change-rules \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "taskTemplateId": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Weekly Deep Clean Cooldown",
    "description": "Prevents assigning deep cleaning within 7 days of last execution",
    "conditionType": "last_execution_cooldown",
    "conditionConfig": {
      "cooldownDays": 7
    }
  }'
```

### Creating a Prerequisite Rule

```bash
curl -X POST https://api.example.com/api/status-change-rules \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "taskTemplateId": "550e8400-e29b-41d4-a716-446655440001",
    "name": "Morning Exercise Required",
    "description": "Evening workout can only be assigned if morning exercise was completed",
    "conditionType": "other_task_completed_today",
    "conditionConfig": {
      "requiredTaskTemplateId": "550e8400-e29b-41d4-a716-446655440002"
    }
  }'
```

## Frontend UI

Administrators can manage rules through the web interface:

1. Navigate to **Status Change Rules** in the admin menu
2. Click **Create Rule** to add a new rule
3. Select the task template and condition type
4. Configure the condition parameters
5. Save and activate the rule

Rules can be:
- Edited (name and description only)
- Activated/Deactivated
- Filtered by task template

## Testing

Integration tests are available in:
- `tests/TaskManagement/Application/StatusChangeRuleManagementTest.php`

Run tests:
```bash
./vendor/bin/phpunit tests/TaskManagement/Application/StatusChangeRuleManagementTest.php
```

## Migration

Apply the database migration:
```bash
php bin/console doctrine:migrations:migrate
```

## Future Enhancements

Potential improvements:
1. Add more condition types (e.g., time-of-day restrictions, user-specific rules)
2. Integrate validation into actual task assignment flows
3. Add rule simulation/testing interface
4. Support complex rule combinations (AND/OR logic)
5. Add audit logging for rule evaluations

## Related Documentation

- Bonus Points Rules: `FRONTEND_BONUS_RULES_UI.md`
- Rule Engine Library: https://github.com/jakubciszak/rule-engine
