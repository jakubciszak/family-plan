# Team Management Implementation

This document describes the team management functionality implemented in the Family Plan application.

## Overview

The team management feature allows users to:
- Create and manage teams for organizing household tasks
- Invite other users to join teams via email
- Assign different roles (admin/member) to team members
- Filter tasks by team
- Configure team-specific bonus rules and status change rules

## Architecture

The implementation follows the existing hexagonal architecture with DDD and CQRS patterns:

```
TeamManagement/
├── Domain/
│   ├── Entity/
│   │   ├── Team.php              # Team aggregate root
│   │   ├── TeamMember.php        # Team membership entity
│   │   └── TeamInvitation.php    # Invitation entity
│   ├── ValueObject/
│   │   ├── TeamName.php          # Team name value object
│   │   ├── TeamRole.php          # Role (admin/member)
│   │   └── InvitationStatus.php  # Invitation status
│   ├── Event/
│   │   ├── TeamCreated.php       # Domain events
│   │   ├── MemberInvited.php
│   │   └── MemberAdded.php
│   └── Repository/               # Repository interfaces
├── Application/
│   ├── Command/                  # Commands for write operations
│   ├── Query/                    # Queries for read operations
│   └── Handler/                  # Command and query handlers
└── Infrastructure/
    └── Persistence/              # Doctrine repositories and types
```

### Party Archetype Integration

The application uses the **Party archetype pattern** (from [Software Archetypes](https://www.softwarearchetypes.com/archetypes/party/)) alongside the legacy Team/User model. This provides a more flexible and extensible design for managing relationships between people and organizations.

#### Party Model Structure

```
Party/
├── Domain/
│   ├── Entity/
│   │   ├── Party.php                    # Abstract party (person or organization)
│   │   ├── Person.php                   # Individual (maps to User)
│   │   ├── Organization.php             # Group (maps to Team)
│   │   └── PartyRelationship.php        # Relationship between parties
│   ├── ValueObject/
│   │   ├── PartyType.php                # PERSON or ORGANIZATION
│   │   └── PartyRelationshipType.php    # ADMIN_OF or MEMBER_OF
│   └── Repository/
│       ├── PartyRepositoryInterface.php
│       └── PartyRelationshipRepositoryInterface.php
├── Application/
│   └── Service/
│       └── PartyAdapter.php             # Bridges legacy and Party models
├── Communication/
│   └── EventSubscriber/
│       ├── UserCreatedSubscriber.php    # Auto-creates Person from User
│       └── TeamCreatedSubscriber.php    # Auto-creates Organization from Team
└── Infrastructure/
    └── Persistence/
        ├── Doctrine/                    # Production repositories
        └── InMemory/                    # Test repositories
```

#### How It Works

1. **Automatic Synchronization**: When a User or Team is created, domain events trigger subscribers that automatically create corresponding Person or Organization entities.

2. **Relationship Management**: The `PartyRelationship` entity manages admin/member relationships:
   - `ADMIN_OF`: Person is admin of Organization (replaces TeamMember with admin role)
   - `MEMBER_OF`: Person is member of Organization (replaces TeamMember with member role)

3. **Task Creation Authorization**: The `CreateTaskHandler` uses `PartyRelationshipRepository` to check if a Person has `ADMIN_OF` relationship with an Organization, enforcing the "only team admins can create tasks" rule.

4. **Legacy Compatibility**: The legacy Team/User/TeamMember model continues to work through event-driven synchronization with the Party model.

#### Benefits

- **Flexibility**: Easier to model complex relationships (e.g., organizations within organizations, multiple relationship types)
- **Extensibility**: Easy to add new party types or relationship types
- **Consistency**: Single source of truth for relationships via PartyRelationship
- **Testability**: Clean separation with InMemory repositories for testing

## Database Schema

### Tables

#### `parties` (Party Archetype)
- `id` (UUID) - Primary key
- `party_type` (VARCHAR) - 'person' or 'organization' (Single Table Inheritance)
- `name` (VARCHAR) - Party name
- `email` (VARCHAR) - Email (for Person only)
- `description` (TEXT) - Description (for Organization only)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Indexes:**
- `party_type` - For filtering by type
- `email` - For finding Person by email

#### `party_relationships`
- `id` (UUID) - Primary key
- `from_party_id` (UUID) - Foreign key to parties (the subject)
- `to_party_id` (UUID) - Foreign key to parties (the object)
- `relationship_type` (VARCHAR) - 'admin_of' or 'member_of'
- `started_at` (TIMESTAMP) - When relationship started
- `ended_at` (TIMESTAMP) - When relationship ended (null if active)

**Indexes:**
- `from_party_id` - For querying relationships by subject
- `to_party_id` - For querying relationships by object
- `relationship_type` - For filtering by type

#### `teams` (Legacy)
- `id` (UUID) - Primary key
- `name` (VARCHAR) - Team name
- `description` (TEXT) - Optional team description
- `created_by` (UUID) - User who created the team
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

#### `team_members`
- `id` (UUID) - Primary key
- `team_id` (UUID) - Foreign key to teams
- `user_id` (UUID) - Foreign key to users
- `role` (VARCHAR) - 'admin' or 'member'
- `joined_at` (TIMESTAMP)

**Indexes:**
- `team_id` - For querying members by team
- `user_id` - For querying teams by user

#### `team_invitations`
- `id` (UUID) - Primary key
- `team_id` (UUID) - Foreign key to teams
- `email` (VARCHAR) - Email of invited user
- `role` (VARCHAR) - Role to assign
- `status` (VARCHAR) - 'pending', 'accepted', 'rejected', 'expired'
- `invited_by` (UUID) - User who sent invitation
- `token` (VARCHAR) - Unique invitation token
- `created_at` (TIMESTAMP)
- `expires_at` (TIMESTAMP)
- `responded_at` (TIMESTAMP)

**Indexes:**
- `team_id` - For querying invitations by team
- `email` - For querying invitations by email
- `status` - For filtering by status

## API Endpoints

### Teams

#### `GET /api/teams`
Get all teams for the current user.

**Response:**
```json
{
  "teams": [
    {
      "id": "uuid",
      "name": "Family Team",
      "description": "Our household team",
      "createdBy": "uuid",
      "createdAt": "2024-01-04T12:00:00+00:00",
      "updatedAt": null
    }
  ]
}
```

#### `POST /api/teams`
Create a new team.

**Request:**
```json
{
  "name": "Family Team",
  "description": "Our household team"
}
```

**Response:** `201 Created`
```json
{
  "id": "uuid",
  "name": "Family Team",
  "description": "Our household team"
}
```

#### `PUT /api/teams/{id}`
Update team details (admin only).

**Request:**
```json
{
  "name": "Updated Team Name",
  "description": "Updated description"
}
```

**Response:** `200 OK`

### Team Members

#### `GET /api/teams/{id}/members`
Get all members of a team.

**Response:**
```json
{
  "members": [
    {
      "id": "uuid",
      "userId": "uuid",
      "userName": "John Doe",
      "userEmail": "john@example.com",
      "role": "admin",
      "joinedAt": "2024-01-04T12:00:00+00:00"
    }
  ]
}
```

#### `POST /api/teams/{id}/invite`
Invite a user to the team (admin only).

**Request:**
```json
{
  "email": "user@example.com",
  "role": "member"
}
```

**Response:** `201 Created`
```json
{
  "message": "Invitation sent successfully",
  "invitationId": "uuid"
}
```

#### `DELETE /api/teams/{id}/members/{userId}`
Remove a member from the team (admin only).

**Response:** `200 OK`

### Invitations

#### `GET /api/teams/invitations`
Get pending invitations for the current user.

**Response:**
```json
{
  "invitations": [
    {
      "id": "uuid",
      "teamId": "uuid",
      "role": "member",
      "token": "invitation-token",
      "status": "pending",
      "createdAt": "2024-01-04T12:00:00+00:00",
      "expiresAt": "2024-01-11T12:00:00+00:00"
    }
  ]
}
```

#### `POST /api/teams/invitations/{token}/accept`
Accept a team invitation.

**Response:** `200 OK`

## Frontend Integration

### React Components

#### TeamManagement.jsx
Main page for managing teams. Features:
- List of user's teams
- Create new team form
- Team selection
- Display pending invitations

#### Navigation
Added "Teams" link to main navigation in `App.jsx`.

### Translations

Translation keys added to `en.json` and `pl.json`:
- `teams.title` - "Team Management"
- `teams.createTeam` - "Create Team"
- `teams.inviteMember` - "Invite Member"
- etc.

## Mobile Integration

### React Native Screen

#### TeamManagementScreen.tsx
Full-featured mobile screen with:
- Team list with cards
- Create team modal
- Team members modal
- Invite member modal
- Accept/reject invitations

### API Client

Extended `apiClient.ts` with team management methods:
- `getTeams()`
- `createTeam(data)`
- `inviteToTeam(teamId, data)`
- `acceptInvitation(token)`
- etc.

## Notification System

Team invitations trigger email notifications via the existing `Notifications` context:

### For Existing Users
- Subject: "Team Invitation: {teamName}"
- Message includes invitation link
- Notification visible in app

### For Non-Existing Users
- Subject: "Team Invitation: {teamName} - Registration Required"
- Message includes registration instructions and invitation link

## Authorization

### Team Creation
- Any authenticated user can create a team
- Creator is automatically added as admin

### Team Management
- Only admins can:
  - Update team details
  - Invite new members
  - Remove members
  - Change member roles

### Task Visibility
- Users can only see tasks from their teams
- Tasks can be filtered by team

## Usage Examples

### Creating a Team

```javascript
// Frontend
import teamService from '../services/teamService';

const createTeam = async () => {
  await teamService.createTeam({
    name: 'Family Team',
    description: 'Our household tasks'
  });
};
```

### Inviting a Member

```javascript
// Frontend
const inviteMember = async (teamId) => {
  await teamService.inviteToTeam(teamId, {
    email: 'member@example.com',
    role: 'member'
  });
};
```

### Mobile Usage

```typescript
// Mobile
import apiClient from '../services/apiClient';

const loadTeams = async () => {
  const response = await apiClient.getTeams();
  setTeams(response.teams);
};
```

## Testing

### Unit Tests
- `tests/TeamManagement/Domain/TeamTest.php`
- `tests/TeamManagement/Domain/TeamInvitationTest.php`

### Behat Scenarios
- `features/team_management/team_lifecycle.feature`

Run tests:
```bash
# Unit tests
php bin/phpunit tests/TeamManagement/

# Behat tests
vendor/bin/behat features/team_management/
```

## Future Enhancements

Potential improvements:
- [ ] Team avatars/logos
- [ ] Team statistics and analytics
- [ ] Team chat/messaging
- [ ] Team-specific task templates
- [ ] Team activity feed
- [ ] Bulk member management
- [ ] Team transfer ownership
- [ ] Team archiving

## Migration Guide

To enable team management on an existing installation:

1. Run database migrations:
```bash
php bin/console doctrine:migrations:migrate
```

2. Clear cache:
```bash
php bin/console cache:clear
```

3. Rebuild frontend:
```bash
cd frontend
npm run build
```

4. Rebuild mobile:
```bash
cd mobile
npm run build
```

## Troubleshooting

### Invitations Not Sending
- Check email configuration in `.env`
- Verify notification service is configured
- Check logs for email sending errors

### Authorization Errors
- Verify user is team admin
- Check team membership
- Ensure proper role assignment

### Database Issues
- Verify migrations have run
- Check foreign key constraints
- Ensure proper indexes are created
