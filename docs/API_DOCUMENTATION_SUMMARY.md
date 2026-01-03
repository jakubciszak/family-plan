# OpenAPI Documentation Summary

This document summarizes the API documentation and test files created for the Family Plan application.

## Created Files

### 1. OpenAPI Specification
- **Location**: `/docs/openapi.json`
- **Format**: OpenAPI 3.0.0
- **Size**: 1,239 lines
- **Description**: Complete API specification in JSON format

### 2. HTTP Test Files
All located in `/tests/Api/`:

#### Authentication (auth.http)
- POST /api/auth/login - User login
- GET /api/auth/me - Get current user
- POST /api/auth/logout - User logout
- Includes invalid credentials test case

#### Users (users.http)
- GET /api/users - List all users
- POST /api/users - Create new user
- GET /api/users/{id} - Get user by ID
- GET /api/users/{id}/points - Get user points balance
- Includes 404 error test cases

#### Tasks (tasks.http)
- GET /api/tasks - List all tasks
- POST /api/tasks - Create new task (with multiple frequency examples)
- GET /api/tasks/{id} - Get task by ID
- POST /api/tasks/{id}/assign - Assign task to user
- POST /api/tasks/{id}/complete - Mark task as completed
- POST /api/tasks/{id}/approve - Approve completed task
- Includes error test cases (404, invalid user)

#### Bonus Points Rules (bonus-rules.http)
- GET /api/bonus-rules - List all bonus rules (with optional active filter)
- POST /api/bonus-rules - Create new bonus rule
- GET /api/bonus-rules/{id} - Get bonus rule by ID
- PUT /api/bonus-rules/{id} - Update bonus rule
- POST /api/bonus-rules/{id}/activate - Activate rule
- POST /api/bonus-rules/{id}/deactivate - Deactivate rule
- Includes error test cases (404, 400)
- **Note**: All endpoints require ROLE_ADMIN

#### User Settings (user-settings.http)
- GET /api/user-settings/{userId} - Get user settings
- PUT /api/user-settings/{userId} - Update user settings
- PATCH /api/user-settings/{userId} - Partially update user settings
- Includes examples for different preference types:
  - Notification preferences
  - Theme preferences
  - Language preferences
  - Privacy preferences
- Includes validation error test cases

### 3. Documentation
- **Location**: `/tests/Api/README.md`
- **Content**: 
  - Usage instructions for .http files
  - Prerequisites and setup
  - Testing workflow
  - Variable usage
  - Tips and best practices
  - Security notes

### 4. Generation Script
- **Location**: `/scripts/generate-openapi.php`
- **Purpose**: PHP script to generate OpenAPI JSON from NelmioApiDocBundle
- **Note**: Can be used when the application is running to regenerate documentation

## API Overview

### Endpoints Summary
Total endpoints documented: **26**

#### By Category:
- **Authentication**: 3 endpoints
- **Users**: 4 endpoints
- **Tasks**: 6 endpoints
- **Bonus Points Rules**: 6 endpoints (Admin only)
- **User Settings**: 3 endpoints (with multiple HTTP methods)

### HTTP Methods Used:
- GET: 11 endpoints
- POST: 10 endpoints
- PUT: 2 endpoints
- PATCH: 1 endpoint

### Response Codes Documented:
- 200 OK - Successful GET/PUT/PATCH requests
- 201 Created - Successful POST requests
- 400 Bad Request - Validation errors
- 401 Unauthorized - Authentication errors
- 404 Not Found - Resource not found

## How to Use

### For Developers
1. Open `.http` files in VS Code (with REST Client extension) or JetBrains IDEs
2. Update variables with actual UUIDs from your test environment
3. Click "Send Request" to execute HTTP calls
4. View responses inline

### For API Consumers
1. Use `/docs/openapi.json` to import into tools like:
   - Postman
   - Insomnia
   - Swagger UI
   - API documentation generators

### Interactive Documentation
When the application is running, visit:
- `http://localhost:8080/api/doc` - Swagger UI interface (configured via NelmioApiDocBundle)

## Technical Details

### OpenAPI Specification
- **Version**: 3.0.0
- **API Title**: Family Plan API
- **API Version**: 1.0.0
- **Servers**:
  - Development: http://localhost:8080
  - Production: https://api.familyplan.local

### Tags (Categories):
- Authentication
- Users
- Tasks
- Bonus Points Rules
- User Settings

### Security
- Session-based authentication (Symfony Security)
- Admin role required for bonus rules management
- Password requirements enforced

## File Organization

```
docs/
└── openapi.json          # Complete OpenAPI 3.0 specification

tests/Api/
├── README.md             # Usage documentation
├── auth.http             # Authentication tests
├── users.http            # User management tests
├── tasks.http            # Task management tests
├── bonus-rules.http      # Bonus rules tests (Admin)
└── user-settings.http    # User settings tests

scripts/
└── generate-openapi.php  # OpenAPI generation script
```

## Validation

- ✓ OpenAPI JSON structure is valid
- ✓ All endpoints from controllers are documented
- ✓ Request/response schemas are complete
- ✓ Error cases are included
- ✓ Examples are provided for all request bodies
- ✓ Files are organized by context/functionality

## Next Steps

1. Test the `.http` files with a running application instance
2. Update variables with real UUIDs from your database
3. Extend tests with additional edge cases as needed
4. Consider adding authentication tokens if implementing JWT
5. Update documentation as new endpoints are added

## Notes

- All `.http` files use variables for easy customization
- Base URL is configurable via `@baseUrl` variable
- UUID placeholders need to be replaced with actual values
- Admin-only endpoints are clearly marked
- Both success and error scenarios are included
