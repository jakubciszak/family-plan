# API Test Files

This directory contains HTTP request files for testing and documenting all API endpoints in the Family Plan application.

## Files

- **auth.http** - Authentication endpoints (login, logout, current user)
- **users.http** - User management endpoints (CRUD operations, points balance)
- **tasks.http** - Task management endpoints (CRUD, assign, complete, approve)
- **bonus-rules.http** - Bonus points rules management (Admin only)
- **user-settings.http** - User settings and preferences

## How to Use

### Prerequisites

These `.http` files can be used with:
- **VS Code** with the [REST Client extension](https://marketplace.visualstudio.com/items?itemName=humao.rest-client)
- **JetBrains IDEs** (PHPStorm, IntelliJ IDEA) with the built-in HTTP Client
- **Other HTTP clients** that support the `.http` file format

### Setup

1. Make sure your development server is running:
   ```bash
   symfony server:start
   # or
   docker-compose up
   ```

2. Update the `@baseUrl` variable in each file if your server runs on a different port or domain.

3. Create initial test data:
   - Run the authentication tests to create a user
   - Copy the returned user IDs and update the variables in other test files

### Running Tests

1. Open any `.http` file in your IDE
2. Click the "Send Request" link above each request or use the keyboard shortcut (typically Ctrl+Alt+R or Cmd+Alt+R)
3. View the response in the editor panel

### Variables

Each file uses variables to make testing easier:

- `@baseUrl` - The base URL of your API (default: http://localhost:8080)
- `@contentType` - Content type header (application/json)
- `@userId`, `@taskId`, `@ruleId` - Entity IDs to be filled with actual UUIDs from responses

### Testing Workflow

1. **Start with authentication** (auth.http):
   - Create admin and regular users using users.http
   - Login to get session/token
   - Verify with /api/auth/me

2. **Create test data**:
   - Create users (users.http)
   - Create tasks (tasks.http)
   - Create bonus rules if you're an admin (bonus-rules.http)

3. **Test workflows**:
   - Assign tasks to users
   - Complete tasks
   - Approve tasks (as admin)
   - Check user points

4. **Test settings**:
   - Update user preferences (user-settings.http)

### Tips

- Use the REST Client extension's ability to capture variables from responses:
  ```http
  # @name createUser
  POST {{baseUrl}}/api/users
  # ... request body ...
  
  # Then use in next request:
  @userId = {{createUser.response.body.id}}
  ```

- Check HTTP status codes:
  - 200/201 - Success
  - 400 - Bad request (validation error)
  - 401 - Unauthorized
  - 404 - Not found

- Each file includes both successful test cases and error cases (404, 400, etc.)

## API Documentation

For complete API documentation in OpenAPI format, see:
- **JSON format**: `/docs/openapi.json`
- **Interactive UI**: Visit `http://localhost:8080/api/doc` when the server is running

## Security Notes

- **Bonus rules endpoints** require ROLE_ADMIN authentication
- Update default passwords before deploying to production
- Never commit real credentials to version control
