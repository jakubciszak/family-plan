# Bonus Points Rules - Frontend UI Documentation

## Overview

The frontend now includes a complete admin interface for managing bonus points rules. This document describes the UI components and features.

## Screenshots and UI Mockups

### Main Navigation (Admin View)

```
┌─────────────────────────────────────────────────────────────────────┐
│ Family Plan    [Tasks] [Bonus Rules]     Welcome, Admin  150 points │
└─────────────────────────────────────────────────────────────────────┘
```

- Navigation tabs appear in the header
- "Bonus Rules" tab is **only visible to administrators**
- Active tab is highlighted with blue border

### Bonus Rules Management Page

```
┌───────────────────────────────────────────────────────────────────┐
│  Bonus Points Rules                          [Create New Rule]    │
├───────────────────────────────────────────────────────────────────┤
│                                                                    │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Dishwasher Streak Bonus          [Active] [Consecutive Days]│  │
│  │                                                              │  │
│  │ Earn 20 bonus points for emptying dishwasher 5 consecutive  │  │
│  │ days                                                         │  │
│  │                                                              │  │
│  │ Complete task 5 consecutive days                            │  │
│  │                                                              │  │
│  │ Bonus Points: 20                                            │  │
│  │                                                              │  │
│  │ [Edit] [Deactivate]                                         │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                    │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Monthly Champion            [Inactive] [Monthly Task Count] │  │
│  │                                                              │  │
│  │ Earn 30 bonus points for completing 20 tasks in a month     │  │
│  │                                                              │  │
│  │ Complete 20 tasks in a month                                │  │
│  │                                                              │  │
│  │ Bonus Points: 30                                            │  │
│  │                                                              │  │
│  │ [Edit] [Activate]                                           │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                    │
└───────────────────────────────────────────────────────────────────┘
```

### Create/Edit Rule Form

```
┌───────────────────────────────────────────────────────────────────┐
│  Create New Bonus Rule                                            │
├───────────────────────────────────────────────────────────────────┤
│                                                                    │
│  Rule Name:                                                       │
│  ┌──────────────────────────────────────────────────────────────┐│
│  │ e.g., Dishwasher Streak Bonus                               ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                    │
│  Description:                                                     │
│  ┌──────────────────────────────────────────────────────────────┐│
│  │ Describe what users need to do to earn this bonus           ││
│  │                                                              ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                    │
│  Bonus Points:                                                    │
│  ┌──────────────────────────────────────────────────────────────┐│
│  │ 20                                                           ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                    │
│  Rule Type:                                                       │
│  ┌──────────────────────────────────────────────────────────────┐│
│  │ ▼ Consecutive Days                                           ││
│  └──────────────────────────────────────────────────────────────┘│
│     Options: Consecutive Days, Monthly Task Count                │
│                                                                    │
│  Required Consecutive Days:                                       │
│  ┌──────────────────────────────────────────────────────────────┐│
│  │ 5                                                            ││
│  └──────────────────────────────────────────────────────────────┘│
│  Number of consecutive days the task must be completed            │
│                                                                    │
│  [Create Rule] [Cancel]                                           │
│                                                                    │
└───────────────────────────────────────────────────────────────────┘
```

## UI Features

### Color Coding

**Active Rules:**
- Green left border (#10b981)
- Green status badge
- "Active" label

**Inactive Rules:**
- Red left border (#ef4444)
- Red status badge
- "Inactive" label

**Rule Types:**
- Blue badges for rule type
- "Consecutive Days" badge
- "Monthly Task Count" badge

### Responsive Design

- Grid layout adapts to screen size
- Minimum card width: 350px
- Maximum container width: 1200px
- Mobile-friendly forms

### User Feedback

**Success States:**
- Rules load successfully
- CRUD operations complete
- Clear visual confirmations

**Error States:**
- Error messages in red banner
- Clear error descriptions
- Persistent until resolved

**Loading States:**
- "Loading bonus rules..." message
- Disabled buttons during operations
- Smooth transitions

## Access Control

### Admin Users
✅ See "Bonus Rules" navigation tab
✅ Can view all bonus rules
✅ Can create new rules
✅ Can edit existing rules
✅ Can activate/deactivate rules

### Regular Users
❌ Do not see "Bonus Rules" tab
❌ Access denied if URL accessed directly
❌ Clear message: "Only administrators can manage bonus rules"

## Form Validation

### Create Rule Form
- **Rule Name:** Required, 1-255 characters
- **Description:** Required, 1-1000 characters
- **Bonus Points:** Required, 1-1000 (integer)
- **Rule Type:** Required (dropdown selection)
- **Required Days:** 2-365 (for consecutive days rules)
- **Required Count:** 1-1000 (for monthly task count rules)

### Edit Rule Form
- Same validation as create
- Rule type cannot be changed (immutable)
- Only name, description, and bonus points editable

## Integration with Backend

All operations use the bonus rules REST API:

```javascript
// List all rules
GET /api/bonus-rules

// Create new rule
POST /api/bonus-rules
Body: { name, description, bonusPoints, ruleType, ruleConfig }

// Update rule
PUT /api/bonus-rules/{id}
Body: { name, description, bonusPoints }

// Activate rule
POST /api/bonus-rules/{id}/activate

// Deactivate rule
POST /api/bonus-rules/{id}/deactivate
```

## User Workflow

### Creating a New Rule

1. Admin navigates to "Bonus Rules" tab
2. Clicks "Create New Rule" button
3. Form appears with fields
4. Admin fills in:
   - Rule name
   - Description
   - Bonus points amount
   - Rule type (dropdown)
   - Type-specific config (days or count)
5. Clicks "Create Rule"
6. Rule is saved and appears in list
7. Form closes automatically

### Editing a Rule

1. Admin clicks "Edit" on a rule card
2. Rule card transforms to inline edit form
3. Admin modifies:
   - Name
   - Description
   - Bonus points
4. Clicks "Update Rule" or "Cancel"
5. Changes saved or discarded
6. Card returns to display mode

### Activating/Deactivating

1. Admin clicks "Activate" or "Deactivate" button
2. API call made immediately
3. Rule status updates
4. Visual indicators change (color, badge)
5. Button toggles to opposite action

## Technical Implementation

### Components

**BonusRulesManagement** (Main Container)
- Manages state for all rules
- Handles loading and errors
- Coordinates CRUD operations
- Enforces admin access control

**BonusRuleCard** (Display Component)
- Shows rule details
- Displays status visually
- Provides action buttons
- Switches to edit mode

**BonusRuleForm** (Form Component)
- Reusable for create/edit
- Conditional fields by rule type
- Form validation
- Cancel support

### State Management

```javascript
const [rules, setRules] = useState([]);
const [loading, setLoading] = useState(true);
const [showCreateForm, setShowCreateForm] = useState(false);
const [error, setError] = useState(null);
```

### API Client

Uses the existing `apiClient` service with:
- GET, POST, PUT methods
- Automatic JSON handling
- Error handling
- Credentials included

## Styling Details

### CSS Classes

- `.bonus-rules-container` - Main container
- `.bonus-rules-header` - Header with title and button
- `.bonus-rules-list` - Grid of rule cards
- `.bonus-rule-card` - Individual rule card
- `.rule-header` - Card header with title and badges
- `.rule-body` - Card content
- `.rule-actions` - Action buttons
- `.bonus-rule-form` - Create/edit form
- `.status-badge` - Status indicator
- `.rule-type-badge` - Rule type indicator

### Color Palette

- **Primary Blue:** #3b82f6 (buttons, active nav)
- **Success Green:** #10b981 (active rules, success buttons)
- **Warning Yellow:** #fbbf24 (points display)
- **Error Red:** #ef4444 (inactive rules, errors)
- **Gray:** #6b7280 (secondary text, borders)

## Future Enhancements

Potential improvements:
- Rule deletion functionality
- Bulk operations (activate/deactivate multiple)
- Rule preview/simulation
- User notification when they earn bonuses
- Progress tracking toward bonus goals
- Analytics dashboard for rule effectiveness
- Rule templates for quick creation
- Export/import rules functionality
