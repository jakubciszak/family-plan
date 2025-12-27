# Composite Tasks (Bonus Points Rules) - Implementation Summary

## Overview

This feature allows administrators to define flexible bonus points rules that reward users for completing tasks in specific patterns. The implementation uses the `jakubciszak/rule-engine` library for maintainable and flexible rule definitions.

## Features Implemented

### Rule Types

1. **Consecutive Days Rule**
   - Awards bonus points when a user completes a specific task for N consecutive days
   - Example: "20 points for emptying dishwasher 5 consecutive days"
   - Configuration requires: `taskTemplateId` and `requiredDays` (minimum 2)

2. **Monthly Task Count Rule**
   - Awards bonus points when a user completes at least N tasks in the current month
   - Example: "30 points for completing 20 tasks this month"
   - Configuration requires: `requiredCount` (minimum 1)

### Architecture

The implementation follows **Hexagonal Architecture** with **Domain-Driven Design**:

```
Domain Layer
├── Entity: BonusPointsRule
├── Value Objects: RuleType, RuleConfig
├── Events: BonusPointsRuleCreated
├── Services: BonusPointsEvaluator
└── Repository Interface: BonusPointsRuleRepositoryInterface

Application Layer
├── Commands: Create, Update, Activate, Deactivate
├── Queries: GetAllRules, FindById
└── Handlers: Command and Query handlers

Infrastructure Layer
├── Persistence: DoctrineBonusPointsRuleRepository
├── Doctrine Types: RuleTypeType
└── Testing: InMemoryBonusPointsRuleRepository

Presentation Layer
└── API: BonusPointsRuleApiController (Admin-only)
```

### API Endpoints

All endpoints require `ROLE_ADMIN` authentication:

- `GET /api/bonus-rules` - List all rules (optional `?active=true` filter)
- `GET /api/bonus-rules/{id}` - Get specific rule details
- `POST /api/bonus-rules` - Create new rule
- `PUT /api/bonus-rules/{id}` - Update rule (name, description, points)
- `POST /api/bonus-rules/{id}/activate` - Activate a rule
- `POST /api/bonus-rules/{id}/deactivate` - Deactivate a rule

### Request/Response Examples

**Create Consecutive Days Rule:**
```json
POST /api/bonus-rules
{
  "name": "Dishwasher Streak Bonus",
  "description": "Earn 20 bonus points for emptying dishwasher 5 consecutive days",
  "bonusPoints": 20,
  "ruleType": "consecutive_days",
  "ruleConfig": {
    "taskTemplateId": "123e4567-e89b-12d3-a456-426614174000",
    "requiredDays": 5
  }
}
```

**Create Monthly Task Count Rule:**
```json
POST /api/bonus-rules
{
  "name": "Monthly Task Champion",
  "description": "Earn 30 bonus points for completing 20 tasks this month",
  "bonusPoints": 30,
  "ruleType": "monthly_task_count",
  "ruleConfig": {
    "requiredCount": 20
  }
}
```

**Response:**
```json
{
  "id": "uuid",
  "name": "Rule Name",
  "description": "Rule Description",
  "bonusPoints": 20,
  "type": "consecutive_days",
  "config": {
    "type": "consecutive_days",
    "ruleDefinition": { ... },
    "taskTemplateId": "uuid",
    "requiredDays": 5,
    "requiredCount": null
  },
  "isActive": true,
  "createdAt": "2024-01-01T00:00:00+00:00",
  "updatedAt": null
}
```

## Technical Implementation

### Rule Engine Integration

The implementation uses `jakubciszak/rule-engine` library's `NestedRuleApi` for flexible rule evaluation:

```php
// Consecutive days rule definition
$ruleDefinition = [
    'and' => [
        ['>=' => [['var' => 'consecutiveDays'], $requiredDays]],
        ['==' => [['var' => 'taskTemplateId'], $taskTemplateId->value()]]
    ]
];

// Monthly task count rule definition
$ruleDefinition = [
    '>=' => [['var' => 'monthlyTaskCount'], $requiredCount]
];

// Evaluation
$isMet = NestedRuleApi::evaluate($ruleDefinition, $context);
```

### Database Schema

```sql
CREATE TABLE bonus_points_rules (
    id UUID NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    bonus_points VARCHAR(255) NOT NULL,  -- Doctrine 'points' type
    type VARCHAR(255) NOT NULL,          -- Doctrine 'rule_type' type
    config JSON NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
);

CREATE INDEX idx_bonus_points_rules_is_active ON bonus_points_rules (is_active);
```

### Security

- **Authentication**: All endpoints require `ROLE_ADMIN`
- **Authorization**: Enforced via Symfony's `IsGranted` attribute
- **Input Validation**: All user input is validated before processing
- **Type Safety**: Strict type declarations throughout
- **Error Handling**: Proper HTTP status codes and error messages

### Testing

**Unit Tests (6 tests):**
- Rule creation with different types
- Rule activation/deactivation
- Rule updates
- Points validation

**Integration Tests (7 tests):**
- Complete CRUD workflow
- Active-only filtering
- Multiple rules management
- Command/Query separation

**Coverage:**
- 145 tests passing
- 483 assertions
- No security vulnerabilities detected

## Usage Example

### For Administrators

1. **Create a streak bonus rule:**
   ```bash
   curl -X POST /api/bonus-rules \
     -H "Authorization: Bearer <admin-token>" \
     -H "Content-Type: application/json" \
     -d '{
       "name": "Kitchen Cleanup Streak",
       "description": "Complete kitchen cleanup 7 days in a row",
       "bonusPoints": 50,
       "ruleType": "consecutive_days",
       "ruleConfig": {
         "taskTemplateId": "<task-template-uuid>",
         "requiredDays": 7
       }
     }'
   ```

2. **Create a monthly achievement rule:**
   ```bash
   curl -X POST /api/bonus-rules \
     -H "Authorization: Bearer <admin-token>" \
     -H "Content-Type: application/json" \
     -d '{
       "name": "Super Contributor",
       "description": "Complete 30 tasks this month",
       "bonusPoints": 100,
       "ruleType": "monthly_task_count",
       "ruleConfig": {
         "requiredCount": 30
       }
     }'
   ```

3. **List active rules:**
   ```bash
   curl /api/bonus-rules?active=true \
     -H "Authorization: Bearer <admin-token>"
   ```

### Rule Evaluation Flow

1. User completes and gets approval for a task execution
2. System evaluates all active bonus rules for that user
3. BonusPointsEvaluator:
   - Fetches relevant execution data from repository
   - Prepares context variables
   - Uses rule engine to evaluate each rule
   - If rule is met, awards bonus points to user's wallet

## Extension Points

The system is designed to be easily extensible:

### Adding New Rule Types

1. Add new enum value to `RuleType`
2. Add factory method to `RuleConfig`
3. Add context preparation in `BonusPointsEvaluator`
4. Add validation logic
5. Update API documentation

Example for a "Weekly Variety" rule (complete 5 different tasks in a week):

```php
// In RuleType enum
case WEEKLY_VARIETY = 'weekly_variety';

// In RuleConfig
public static function weeklyVariety(int $requiredDifferentTasks): self
{
    $ruleDefinition = [
        '>=' => [['var' => 'uniqueTasksThisWeek'], $requiredDifferentTasks]
    ];
    
    return new self(
        RuleType::WEEKLY_VARIETY,
        $ruleDefinition,
        null,
        null,
        null,
        $requiredDifferentTasks
    );
}
```

## Dependencies

- `jakubciszak/rule-engine`: ^0.1.1
- PHP 8.3+
- Symfony 7.4
- Doctrine ORM 3.x
- PostgreSQL (for production)

## Migration

To apply the database changes:

```bash
php bin/console doctrine:migrations:migrate
```

## Future Enhancements

Potential improvements for future iterations:

1. **Additional Rule Types:**
   - Weekly task completion patterns
   - Task variety bonuses
   - Streak multipliers
   - Seasonal challenges

2. **Rule Composition:**
   - Combine multiple conditions
   - AND/OR operators between rules
   - Nested rule groups

3. **Time-Based Rules:**
   - Limited-time bonus events
   - Seasonal multipliers
   - Weekend bonuses

4. **User Notifications:**
   - Notify when close to earning bonus
   - Celebrate when bonus is earned
   - Show progress towards goals

5. **Analytics:**
   - Track rule effectiveness
   - Monitor user engagement
   - Identify popular rules

## Frontend Implementation (TODO)

The frontend implementation should include:

1. **Admin Panel:**
   - Rule management dashboard
   - Create/Edit rule forms
   - Rule activation toggle
   - Rule preview and testing

2. **User Interface:**
   - Display active bonus opportunities
   - Show progress towards bonuses
   - Celebrate bonus achievements
   - History of earned bonuses

3. **Components:**
   - `BonusRuleList` - Display all rules
   - `BonusRuleForm` - Create/edit rules
   - `BonusProgressCard` - Show user progress
   - `BonusAchievement` - Celebration modal

## Conclusion

The composite tasks feature is fully implemented in the backend with:
- ✅ Flexible rule engine integration
- ✅ Clean DDD architecture
- ✅ Comprehensive testing
- ✅ Security best practices
- ✅ Extensible design
- ✅ Complete API documentation

The implementation is production-ready and awaits frontend integration.
