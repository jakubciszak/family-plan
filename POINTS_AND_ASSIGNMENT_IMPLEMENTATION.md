# User Points and Task Assignment - Implementation Summary

This document describes the new features implemented for managing user points and task assignments.

## New Features

### 1. User Points Display
- **Frontend**: User's current points balance is displayed in the header next to their name
- **Backend**: New endpoint `GET /api/users/{id}/points` returns the user's current points balance
- **Styling**: Points are displayed in a gold badge for visibility

### 2. Task Assignment
- **Frontend**: 
  - Tasks can be assigned to users via "Assign to Me" button
  - Assignment information (assignee name) is clearly displayed on assigned tasks
  - Assignment button is hidden once a task is assigned
- **Backend**: 
  - New endpoint `POST /api/tasks/{id}/assign` to assign tasks to users
  - Task serialization now includes `assignedUserId` and `assignedUserName` fields
  - Tasks can only be assigned to existing users (validation included)

### 3. Permission-Based Action Visibility
The frontend now properly handles business rules from the backend:

#### Complete Button
- Only visible for tasks that are:
  - In "pending" status
  - Assigned to the current user
- This prevents users from completing tasks assigned to others

#### Approve Button
- Only visible for:
  - Tasks in "completed" status
  - When the current user has admin role (ROLE_ADMIN)
- Regular users cannot see or approve tasks

#### Assign Button
- Only visible for:
  - Tasks in "pending" status
  - Tasks that are not yet assigned to anyone
- Once assigned, the button is hidden and assignment info is shown

## API Endpoints

### GET /api/users/{id}/points
Returns the current points balance for a user.

**Response:**
```json
{
  "userId": "550e8400-e29b-41d4-a716-446655440000",
  "balance": 150
}
```

### POST /api/tasks/{id}/assign
Assigns a task to a specific user.

**Request:**
```json
{
  "userId": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Response:**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440001",
  "name": "Clean the kitchen",
  "description": "Wash dishes and mop floor",
  "points": 50,
  "frequency": "daily",
  "status": "pending",
  "assignedUserId": "550e8400-e29b-41d4-a716-446655440000",
  "assignedUserName": "John Doe",
  "createdAt": "2024-01-15T10:30:00+00:00"
}
```

### Updated GET /api/tasks
Task list now includes assignment information:

**Response:**
```json
{
  "tasks": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "name": "Clean the kitchen",
      "description": "Wash dishes",
      "points": 50,
      "frequency": "daily",
      "status": "pending",
      "assignedUserId": "550e8400-e29b-41d4-a716-446655440000",
      "assignedUserName": "John Doe",
      "createdAt": "2024-01-15T10:30:00+00:00"
    }
  ]
}
```

## Testing

### Backend Tests
- `tests/Api/UserPointsApiTest.php` - Tests for user points endpoint
- `tests/Api/TaskAssignmentApiTest.php` - Tests for task assignment functionality

### Frontend E2E Tests
- `frontend/tests/e2e/user-points.spec.js` - Tests for points display and behavior
- `frontend/tests/e2e/task-assignment.spec.js` - Tests for assignment functionality and permission-based actions

## UI Changes

### Header
- Added points display badge next to user name
- Styled with gold/amber background for visibility

### Task Card
- Added assignment information section showing "Assigned to: [Name]"
- Added "Assign to Me" button for unassigned tasks
- Updated button visibility logic based on task status and assignment

### CSS Classes
- `.user-points` - Styling for points badge in header
- `.task-assignment` - Container for assignment information
- `.assignment-label` - Label text for "Assigned to:"
- `.assignment-user` - User name display (blue, bold)

## Business Rules Implemented

1. **Points Display**: Users can always see their current points balance
2. **Task Assignment**: 
   - Only unassigned pending tasks can be assigned
   - Tasks can only be assigned to valid users
   - Once assigned, the assignment cannot be changed (must complete or reject first)
3. **Task Completion**: 
   - Only the user assigned to a task can complete it
   - Unassigned tasks cannot be completed
4. **Task Approval**: 
   - Only administrators can approve completed tasks
   - Regular users have no access to approval functionality

## Files Modified

### Backend
- `src/Presentation/Api/UserApiController.php` - Added points endpoint
- `src/Presentation/Api/TaskApiController.php` - Added assignment endpoint and updated serialization

### Frontend
- `frontend/src/App.jsx` - Added points fetching and display
- `frontend/src/pages/TaskList.jsx` - Added assignment functionality and updated button logic
- `frontend/src/styles/app.css` - Added styling for points and assignment displays

### Tests
- `tests/Api/UserPointsApiTest.php` - New backend tests
- `tests/Api/TaskAssignmentApiTest.php` - New backend tests
- `frontend/tests/e2e/user-points.spec.js` - New frontend tests
- `frontend/tests/e2e/task-assignment.spec.js` - New frontend tests

## Future Enhancements

Potential improvements for the future:
1. Add ability to unassign tasks
2. Allow admins to assign tasks to specific users
3. Add points history/transaction log
4. Implement points-based rewards or leaderboard
5. Add notifications when tasks are assigned
6. Allow reassignment of rejected tasks
