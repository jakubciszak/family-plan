# CORS Configuration Guide

## Overview

This project uses the [Nelmio CORS Bundle](https://github.com/nelmio/NelmioCorsBundle) to handle Cross-Origin Resource Sharing (CORS) between the frontend and API.

## Configuration

### Environment Variables

Set the allowed origin in your `.env` file:

```bash
# For development (single origin) - NO REGEX
CORS_ALLOWED_ORIGIN=http://localhost:3000

# For production (single origin) - NO REGEX
CORS_ALLOWED_ORIGIN=https://your-frontend-domain.com

# For multiple origins (using regex pattern)
# IMPORTANT: Set origin_regex: true in config/packages/nelmio_cors.yaml
# Match localhost:3000 OR app.example.com (both http and https)
CORS_ALLOWED_ORIGIN=^https?://(localhost:3000|app\.example\.com)$

# For development and staging (regex)
# IMPORTANT: Set origin_regex: true in config/packages/nelmio_cors.yaml
CORS_ALLOWED_ORIGIN=^https?://(localhost:3000|staging\.example\.com|app\.example\.com)$
```

**Note**: When using regex patterns:
1. Set `origin_regex: true` in `config/packages/nelmio_cors.yaml` (both in `defaults` and `paths` sections)
2. Escape special regex characters like `.` with `\.`
3. Start with `^` and end with `$` for exact matching
4. Use `|` to separate multiple domains
5. Use `https?` to match both http and https

### Nelmio CORS Bundle Configuration

The CORS configuration is located in `config/packages/nelmio_cors.yaml`:

```yaml
nelmio_cors:
    defaults:
        # Set to false for simple exact-match origins (default)
        # Set to true if using regex patterns in CORS_ALLOWED_ORIGIN
        origin_regex: false
        allow_origin: ['%env(CORS_ALLOWED_ORIGIN)%']
        allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
        allow_headers: ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept']
        expose_headers: ['Content-Length', 'Content-Type']
        max_age: 3600
        allow_credentials: true
    paths:
        '^/api':
            # Set to false for simple exact-match origins (default)
            # Set to true if using regex patterns in CORS_ALLOWED_ORIGIN
            origin_regex: false
            allow_origin: ['%env(CORS_ALLOWED_ORIGIN)%']
            allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
            allow_headers: ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept']
            expose_headers: ['Content-Length', 'Content-Type']
            max_age: 3600
            allow_credentials: true
```

**Important**: If you use regex patterns in `CORS_ALLOWED_ORIGIN` (e.g., `^https?://(localhost:3000|app\.example\.com)$`), you **must** set `origin_regex: true` in both the `defaults` and `paths` sections above.

### Frontend Configuration

The frontend API client (`frontend/src/services/apiClient.js`) includes `credentials: 'include'` in all fetch requests to support session-based authentication:

```javascript
const response = await fetch(fullUrl, {
    credentials: 'include',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    // ... other options
});
```

## Key Features

1. **Credentials Support**: Both frontend and backend are configured to support credentials (cookies/sessions)
2. **Flexible Origins**: Supports single or multiple allowed origins via environment variable
3. **API-Specific**: CORS is applied specifically to `/api/` routes
4. **Preflight Caching**: OPTIONS requests are cached for 3600 seconds (1 hour)
5. **Comprehensive Headers**: Allows common headers like Content-Type, Authorization, etc.

## Testing

Run CORS-specific tests:

```bash
php vendor/bin/phpunit tests/Api/CorsTest.php
```

## Common Issues

### Issue: "No 'Access-Control-Allow-Origin' header is present"

**Solution**: Ensure `CORS_ALLOWED_ORIGIN` in `.env` matches the frontend URL exactly, including protocol (http/https) and port.

### Issue: Credentials not being sent

**Solution**: Verify that:
1. Frontend includes `credentials: 'include'` in fetch requests
2. Backend has `allow_credentials: true` in nelmio_cors.yaml
3. The Origin header matches the configured allowed origin

### Issue: Preflight OPTIONS requests failing

**Solution**: The Nelmio bundle automatically handles OPTIONS requests. Check that:
1. The route path matches the configured pattern (`^/api/`)
2. The requested method is in the `allow_methods` list

## Development vs Production

### Development Setup
```bash
# .env or .env.local
CORS_ALLOWED_ORIGIN=http://localhost:3000
```

### Production Setup
```bash
# .env.prod or .env.prod.local
CORS_ALLOWED_ORIGIN=https://app.yourdomain.com
```

### Docker Setup

When using Docker Compose, the frontend container accesses the API via the internal Docker network. The frontend runs on the host at `http://localhost:3000`, so this should be the configured origin.

## Security Considerations

1. **Never use `*` wildcard** for `allow_origin` when `allow_credentials` is true
2. **Always use HTTPS** in production
3. **Restrict origins** to only trusted domains
4. **Validate environment variables** to ensure they're properly configured before deployment

## Additional Resources

- [Nelmio CORS Bundle Documentation](https://github.com/nelmio/NelmioCorsBundle/blob/master/README.md)
- [MDN CORS Guide](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)
- [Symfony Security Best Practices](https://symfony.com/doc/current/security.html)
