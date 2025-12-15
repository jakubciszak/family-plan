# REST API Specification

## Base URL
```
http://localhost:8080/api
```

## Authentication

The API uses session-based authentication via Symfony Security.

### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@familyplan.local",
  "password": "admin123"
}
```

**Response 200 OK:**
```json
{
  "message": "Login successful",
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Super Admin",
    "email": "admin@familyplan.local",
    "role": "ROLE_ADMIN"
  }
}
```

**Response 401 Unauthorized:**
```json
{
  "error": "Invalid credentials"
}
```

### Get Current User
```http
GET /api/auth/me
```

**Response 200 OK:**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "Super Admin",
  "email": "admin@familyplan.local",
  "role": "ROLE_ADMIN"
}
```

**Response 401 Unauthorized:**
```json
{
  "error": "Not authenticated"
}
```

### Logout
```http
POST /api/auth/logout
```

**Response 200 OK:**
```json
{
  "message": "Logout successful"
}
```

## Tasks

### List All Tasks
```http
GET /api/tasks
```

**Response 200 OK:**
```json
{
  "tasks": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Clean the kitchen",
      "description": "Wash dishes and mop floor",
      "points": 50,
      "frequency": "daily",
      "status": "pending",
      "createdAt": "2024-01-15T10:30:00+00:00"
    },
    {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "name": "Vacuum living room",
      "description": "Vacuum and dust",
      "points": 30,
      "frequency": "weekly",
      "status": "completed",
      "createdAt": "2024-01-15T11:00:00+00:00"
    }
  ]
}
```

### Create Task
```http
POST /api/tasks
Content-Type: application/json

{
  "name": "Clean the kitchen",
  "description": "Wash dishes and mop floor",
  "points": 50,
  "frequency": "daily"
}
```

**Request Fields:**
- `name` (string, required): Task name (1-255 characters)
- `description` (string, optional): Task description
- `points` (integer, required): Points awarded (0-1000)
- `frequency` (string, required): One of: `once`, `daily`, `weekly`, `monthly`

**Response 201 Created:**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "Clean the kitchen",
  "description": "Wash dishes and mop floor",
  "points": 50,
  "frequency": "daily",
  "status": "pending",
  "createdAt": "2024-01-15T10:30:00+00:00"
}
```

### Get Task by ID
```http
GET /api/tasks/{id}
```

**Response 200 OK:**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "Clean the kitchen",
  "description": "Wash dishes and mop floor",
  "points": 50,
  "frequency": "daily",
  "status": "pending",
  "createdAt": "2024-01-15T10:30:00+00:00"
}
```

**Response 404 Not Found:**
```json
{
  "error": "Task not found"
}
```

### Complete Task
```http
POST /api/tasks/{id}/complete
Content-Type: application/json

{
  "userId": "550e8400-e29b-41d4-a716-446655440002"
}
```

**Request Fields:**
- `userId` (string, required): UUID of the user completing the task

**Response 200 OK:**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "Clean the kitchen",
  "description": "Wash dishes and mop floor",
  "points": 50,
  "frequency": "daily",
  "status": "completed",
  "createdAt": "2024-01-15T10:30:00+00:00"
}
```

**State Transition:** `pending` → `completed`

### Approve Task
```http
POST /api/tasks/{id}/approve
Content-Type: application/json

{
  "adminId": "550e8400-e29b-41d4-a716-446655440003"
}
```

**Request Fields:**
- `adminId` (string, required): UUID of the admin approving the task

**Response 200 OK:**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "Clean the kitchen",
  "description": "Wash dishes and mop floor",
  "points": 50,
  "frequency": "daily",
  "status": "approved",
  "createdAt": "2024-01-15T10:30:00+00:00"
}
```

**State Transition:** `completed` → `approved`

**Authorization:** Requires ROLE_ADMIN

## Users

### List All Users
```http
GET /api/users
```

**Response 200 OK:**
```json
{
  "users": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Super Admin",
      "email": "admin@familyplan.local",
      "role": "ROLE_ADMIN"
    },
    {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "name": "John Doe",
      "email": "john@example.com",
      "role": "ROLE_USER"
    }
  ]
}
```

### Create User
```http
POST /api/users
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securePassword123",
  "role": "ROLE_USER"
}
```

**Request Fields:**
- `name` (string, required): User's full name
- `email` (string, required): Valid email address
- `password` (string, required): Password (min 8 characters)
- `role` (string, optional): One of: `ROLE_USER`, `ROLE_ADMIN` (default: `ROLE_USER`)

**Response 201 Created:**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440001",
  "name": "John Doe",
  "email": "john@example.com",
  "role": "ROLE_USER"
}
```

**Note:** Password is never returned in responses.

### Get User by ID
```http
GET /api/users/{id}
```

**Response 200 OK:**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440001",
  "name": "John Doe",
  "email": "john@example.com",
  "role": "ROLE_USER"
}
```

**Response 404 Not Found:**
```json
{
  "error": "User not found"
}
```

## Status Codes

| Code | Description |
|------|-------------|
| 200  | OK - Request successful |
| 201  | Created - Resource created successfully |
| 400  | Bad Request - Invalid input data |
| 401  | Unauthorized - Authentication required |
| 403  | Forbidden - Insufficient permissions |
| 404  | Not Found - Resource not found |
| 500  | Internal Server Error - Server error |

## Task Status Flow

```
pending → completed → approved
```

- `pending`: Task is created and waiting to be done
- `completed`: Task has been completed by a user
- `approved`: Task has been approved by an admin

## Frequency Types

- `once`: Task is done only once
- `daily`: Task repeats every day
- `weekly`: Task repeats every week
- `monthly`: Task repeats every month

## User Roles

- `ROLE_USER`: Regular user
  - Can view tasks
  - Can complete tasks
  - Can view own profile

- `ROLE_ADMIN`: Administrator
  - All ROLE_USER permissions
  - Can create tasks
  - Can approve completed tasks
  - Can manage users

## Error Handling

All errors return a consistent JSON structure:

```json
{
  "error": "Error message describing what went wrong"
}
```

### Common Errors

**Validation Error:**
```json
{
  "error": "Validation failed",
  "details": {
    "name": "This field is required",
    "points": "Points must be between 0 and 1000"
  }
}
```

**Domain Logic Error:**
```json
{
  "error": "Cannot approve task that is not completed"
}
```

## Rate Limiting

Not currently implemented. Consider adding for production:
- Limit: 100 requests per minute per IP
- Headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`

## CORS

For development, CORS is permissive. For production, configure in `config/packages/nelmio_cors.yaml`:

```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['https://yourdomain.com']
        allow_methods: ['GET', 'POST', 'PUT', 'DELETE']
        allow_headers: ['Content-Type', 'Authorization']
        max_age: 3600
    paths:
        '^/api/': ~
```

## Examples with cURL

### Login
```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@familyplan.local","password":"admin123"}' \
  -c cookies.txt

### List Tasks
```bash
curl http://localhost:8080/api/tasks \
  -b cookies.txt
```

### Create Task
```bash
curl -X POST http://localhost:8080/api/tasks \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "name": "Clean the kitchen",
    "description": "Wash dishes and mop floor",
    "points": 50,
    "frequency": "daily"
  }'
```

### Complete Task
```bash
curl -X POST http://localhost:8080/api/tasks/550e8400-e29b-41d4-a716-446655440000/complete \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"userId":"550e8400-e29b-41d4-a716-446655440002"}'
```

## API Client Libraries

### JavaScript/TypeScript
See `assets/react/services/apiClient.js` for a reference implementation.

### PHP
Use Symfony HttpClient:

```php
use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();
$response = $client->request('GET', 'http://localhost:8080/api/tasks');
$tasks = $response->toArray();
```

### Python
```python
import requests

# Login
session = requests.Session()
session.post('http://localhost:8080/api/auth/login', json={
    'email': 'admin@familyplan.local',
    'password': 'admin123'
})

# Get tasks
response = session.get('http://localhost:8080/api/tasks')
tasks = response.json()
```
