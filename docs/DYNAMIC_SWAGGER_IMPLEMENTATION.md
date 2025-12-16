# Dynamic Swagger API Documentation Implementation

## Overview
This document describes the implementation of dynamic Swagger API documentation at `/api/doc`.

## Implementation Details

### Components Added

1. **ApiDocController** (`src/Presentation/Api/ApiDocController.php`)
   - Main controller serving the API documentation
   - Two endpoints:
     - `GET /api/doc` - Serves the Swagger UI interface
     - `GET /api/openapi.yaml` - Serves the OpenAPI specification dynamically

2. **Swagger UI Template** (`templates/api/doc.html.twig`)
   - Twig template rendering the Swagger UI
   - Uses Swagger UI 5.10.5 from unpkg CDN
   - Configured to use session-based authentication (cookies)
   - Dynamically loads OpenAPI spec from `/api/openapi.yaml`

3. **Tests** (`tests/Api/ApiDocTest.php`)
   - Tests for both API documentation endpoints
   - Verifies Swagger UI is rendered correctly
   - Verifies OpenAPI spec is served with correct content type

## Usage

### Accessing the Documentation
Navigate to: `http://localhost:8080/api/doc`

### Features
- Interactive API documentation
- "Try it out" functionality for all endpoints
- Session-based authentication support
- All endpoints from the OpenAPI specification are documented
- Deep linking support

### OpenAPI Specification
The OpenAPI specification is served dynamically from `/api/openapi.yaml` endpoint, which reads the `public/openapi.yaml` file.

## Architecture Benefits

1. **Dynamic Routing**: Uses Symfony's routing system instead of static files
2. **Template Flexibility**: Easy to customize the Swagger UI appearance via Twig
3. **Integration**: Fully integrated with Symfony's controller and routing infrastructure
4. **Testability**: Endpoints are easily testable with Symfony's testing framework
5. **Maintainability**: Separated concerns - controller logic, template, and OpenAPI spec

## Future Enhancements

Potential improvements for the future:
- Add automatic OpenAPI spec generation from controller attributes (using nelmio/api-doc-bundle)
- Add authentication requirement for the documentation endpoint
- Cache the OpenAPI spec for better performance
- Add multiple API versions support
- Implement automatic API spec validation

## Technical Notes

- The implementation follows Symfony best practices
- Uses PHP 8.3+ features (attributes for routing)
- Compatible with Symfony 8.0
- No additional dependencies required beyond Symfony's core components
