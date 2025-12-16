# OpenAPI/Swagger Documentation

The Family Plan API is documented using OpenAPI 3.0 specification with Swagger UI for interactive documentation.

## Accessing the Documentation

### Swagger UI (Interactive) - Recommended
Visit the dynamic interactive API documentation in your browser:

```
http://localhost:8080/api/doc
```

This is the recommended way to access the API documentation as it's dynamically served through Symfony routing.

**Alternative Static Access:**
```
http://localhost:8080/api-docs.html
```

Both provide:
- Complete API endpoint listing
- Request/response examples
- Interactive "Try it out" functionality
- Schema definitions
- Authentication information

### OpenAPI Specification (YAML)
The raw OpenAPI specification is available at:

**Dynamic endpoint (Recommended):**
```
http://localhost:8080/api/openapi.yaml
```

**Static file:**
```
http://localhost:8080/openapi.yaml
```

You can use this file with various OpenAPI tools:
- Import into Postman
- Generate client libraries
- API testing tools
- Documentation generators

## Features

### Interactive Testing
The Swagger UI allows you to:
1. Browse all available endpoints
2. View request/response schemas
3. Try API calls directly from the browser
4. See example requests and responses
5. Test authentication flows

### Authentication
The API uses session-based authentication:
- Login via `/api/auth/login`
- Session cookie (PHPSESSID) is automatically handled
- Logout via `/api/auth/logout`

### Endpoints Documented

**Authentication:**
- `POST /api/auth/login` - User login
- `GET /api/auth/me` - Get current user
- `POST /api/auth/logout` - User logout

**Tasks:**
- `GET /api/tasks` - List all tasks
- `POST /api/tasks` - Create new task
- `GET /api/tasks/{id}` - Get task by ID
- `POST /api/tasks/{id}/complete` - Mark task as completed
- `POST /api/tasks/{id}/approve` - Approve completed task

**Users:**
- `GET /api/users` - List all users
- `POST /api/users` - Create new user
- `GET /api/users/{id}` - Get user by ID

## Usage Examples

### Testing with Swagger UI

1. Open http://localhost:8080/api/doc (or http://localhost:8080/api-docs.html for static version)
2. Click on an endpoint to expand it
3. Click "Try it out"
4. Fill in required parameters
5. Click "Execute"
6. View the response

### Example: Login Flow

1. Expand `POST /api/auth/login`
2. Click "Try it out"
3. Enter credentials:
   ```json
   {
     "email": "admin@familyplan.local",
     "password": "admin123"
   }
   ```
4. Click "Execute"
5. Session cookie is automatically saved
6. Try other authenticated endpoints

### Example: Create Task

1. Login first (see above)
2. Expand `POST /api/tasks`
3. Click "Try it out"
4. Enter task data:
   ```json
   {
     "name": "Clean the kitchen",
     "description": "Wash dishes and mop floor",
     "points": 50,
     "frequency": "daily"
   }
   ```
5. Click "Execute"
6. View the created task in response

## Importing into Other Tools

### Postman
1. Open Postman
2. Click "Import"
3. Enter URL: `http://localhost:8080/openapi.yaml`
4. Or upload the file from `public/openapi.yaml`
5. Collection will be created with all endpoints

### Insomnia
1. Open Insomnia
2. Click "Create" → "Import From"
3. Select "URL"
4. Enter: `http://localhost:8080/openapi.yaml`
5. Import as collection

### Client Code Generation

Use OpenAPI Generator to create client libraries:

```bash
# JavaScript/TypeScript client
npx @openapitools/openapi-generator-cli generate \
  -i http://localhost:8080/openapi.yaml \
  -g typescript-axios \
  -o ./generated/client

# PHP client
npx @openapitools/openapi-generator-cli generate \
  -i http://localhost:8080/openapi.yaml \
  -g php \
  -o ./generated/php-client

# Python client
npx @openapitools/openapi-generator-cli generate \
  -i http://localhost:8080/openapi.yaml \
  -g python \
  -o ./generated/python-client
```

## Customization

### Update OpenAPI Spec
Edit `public/openapi.yaml` to:
- Add new endpoints
- Update schemas
- Modify descriptions
- Change examples
- Update authentication schemes

### Customize Swagger UI
Edit `public/api-docs.html` to:
- Change theme/colors
- Modify layout
- Add custom plugins
- Update configuration options

Example customization in `api-docs.html`:
```javascript
const ui = SwaggerUIBundle({
    url: "/openapi.yaml",
    dom_id: '#swagger-ui',
    deepLinking: true,
    // Add your customizations here
    theme: {
        primaryColor: '#3b82f6'
    }
});
```

## Schema Definitions

### User Schema
```yaml
User:
  type: object
  properties:
    id: string (uuid)
    name: string
    email: string (email format)
    role: string (ROLE_USER | ROLE_ADMIN)
```

### Task Schema
```yaml
Task:
  type: object
  properties:
    id: string (uuid)
    name: string
    description: string
    points: integer (0-1000)
    frequency: string (once|daily|weekly|monthly)
    status: string (pending|completed|approved)
    createdAt: string (date-time)
```

### Error Schema
```yaml
Error:
  type: object
  properties:
    error: string (error message)
```

## API Versioning

The current API version is `1.0.0`. Future versions may:
- Use URL versioning: `/api/v2/tasks`
- Use header versioning: `Accept: application/vnd.familyplan.v2+json`
- Maintain backward compatibility

## Best Practices

1. **Always login first** before testing authenticated endpoints
2. **Check response codes** - 200/201 for success, 4xx for client errors
3. **Review schemas** - Understand request/response structures
4. **Use examples** - Copy/paste example JSON for quick testing
5. **Try error cases** - Test with invalid data to see error responses

## Troubleshooting

### CORS Issues
If testing from a different domain:
- Swagger UI handles CORS automatically
- For external clients, ensure CORS is configured in Symfony

### Authentication Not Working
- Ensure you login first via `/api/auth/login`
- Check that cookies are enabled in your browser
- Verify session cookie is sent with requests

### Endpoints Not Found
- Verify the API server is running on port 8080
- Check that URLs in OpenAPI spec match actual routes
- Clear browser cache and reload

## Integration with CI/CD

### Validate OpenAPI Spec
```bash
# Install validator
npm install -g @apidevtools/swagger-cli

# Validate spec
swagger-cli validate public/openapi.yaml
```

### Generate Documentation
```bash
# Generate static HTML documentation
npx redoc-cli bundle public/openapi.yaml -o docs/api.html

# Or use other generators
npx @redocly/cli build-docs public/openapi.yaml
```

### API Testing
Use OpenAPI spec for automated testing:
```bash
# Install Dredd
npm install -g dredd

# Run API tests
dredd public/openapi.yaml http://localhost:8080
```

## Resources

- [OpenAPI Specification](https://spec.openapis.org/oas/v3.0.0)
- [Swagger UI Documentation](https://swagger.io/tools/swagger-ui/)
- [OpenAPI Generator](https://openapi-generator.tech/)
- [Redoc Documentation](https://redocly.com/redoc/)

## Support

For issues or questions:
1. Check the API specification at `/openapi.yaml`
2. Review endpoint documentation at `/api-docs.html`
3. See `API_SPECIFICATION.md` for detailed API reference
4. Check logs in `var/log/` for errors
