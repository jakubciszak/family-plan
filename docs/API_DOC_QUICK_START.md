# Quick Start: Accessing Dynamic API Documentation

## How to Access

Once your Symfony application is running, access the API documentation at:

```
http://localhost:8080/api/doc
```

## What You'll See

The endpoint serves an interactive Swagger UI interface with:

1. **Complete API Endpoint Listing**
   - All available endpoints organized by category (Authentication, Tasks, Users)
   - HTTP methods clearly displayed (GET, POST, etc.)
   
2. **Interactive Testing**
   - "Try it out" buttons on each endpoint
   - Fill in parameters and execute requests directly from the browser
   - Real-time response display
   
3. **Request/Response Documentation**
   - Request body schemas with examples
   - Response codes and their meanings
   - Example responses for each endpoint
   
4. **Authentication Support**
   - Session-based authentication (cookies)
   - Login first at `/api/auth/login` to access protected endpoints

## Example Usage Flow

### 1. Open the Documentation
Navigate to: `http://localhost:8080/api/doc`

### 2. Login to Test Protected Endpoints
1. Find the `POST /api/auth/login` endpoint
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

### 3. Test Other Endpoints
Now you can test other endpoints like:
- `GET /api/tasks` - List all tasks
- `POST /api/tasks` - Create a new task
- `GET /api/users` - List all users

## Technical Details

### Endpoint Structure
- **Main UI**: `/api/doc` - The Swagger UI interface
- **OpenAPI Spec**: `/api/openapi.yaml` - The raw OpenAPI 3.0 specification

### Features
- ✅ Dynamically served through Symfony routing
- ✅ Uses Swagger UI 5.10.5
- ✅ Session-based authentication support
- ✅ Deep linking enabled
- ✅ Security headers (crossorigin, SRI)
- ✅ Mobile responsive

### Backward Compatibility
The static files are still available:
- `/api-docs.html` - Static Swagger UI
- `/openapi.yaml` - Static OpenAPI spec

But the new dynamic endpoints at `/api/doc` and `/api/openapi.yaml` are recommended.

## Troubleshooting

### 404 Not Found
Make sure your Symfony application is running:
```bash
docker-compose up
# or
symfony server:start
```

### CORS Issues
If testing from external tools:
- The endpoint supports `credentials: 'include'` for session cookies
- CORS configuration may need adjustment in Symfony config

### Can't Login
- Verify you're using correct credentials
- Check that cookies are enabled in your browser
- Ensure you're testing on the same domain as the API

## Next Steps

After reviewing the documentation:
1. Test the API endpoints interactively
2. Review the request/response schemas
3. Download the OpenAPI spec for use in tools like Postman
4. Generate client libraries using the OpenAPI spec

For more details, see:
- `docs/DYNAMIC_SWAGGER_IMPLEMENTATION.md` - Implementation details
- `OPENAPI_DOCUMENTATION.md` - Comprehensive API documentation
- `API_SPECIFICATION.md` - API reference
