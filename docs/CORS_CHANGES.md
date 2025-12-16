# CORS Configuration Changes

## Summary

This update fixes CORS issues between the frontend and API by:

1. **Replacing custom CORS implementation** with the industry-standard [Nelmio CORS Bundle](https://github.com/nelmio/NelmioCorsBundle)
2. **Adding credentials support** to the frontend API client for session-based authentication
3. **Providing comprehensive configuration** options for development and production environments

## What Changed

### Backend (API)

- ✅ Added `nelmio/cors-bundle` dependency to `composer.json`
- ✅ Created `config/packages/nelmio_cors.yaml` with CORS configuration
- ✅ Registered `NelmioCorsBundle` in `config/bundles.php`
- ✅ Removed custom `CorsSubscriber` class
- ✅ Updated `config/services.yaml` to remove custom CORS service

### Frontend

- ✅ Updated `frontend/src/services/apiClient.js` to include `credentials: 'include'` in all fetch requests
- ✅ This enables session cookie support for authentication

### Documentation

- ✅ Created `docs/CORS_CONFIGURATION.md` with detailed configuration guide
- ✅ Updated `.env` with better comments and examples
- ✅ Created `scripts/test-cors-config.sh` for quick validation

## Quick Start

### 1. Verify Configuration

```bash
./scripts/test-cors-config.sh
```

### 2. Configure Allowed Origins

Edit `.env` file:

```bash
# Single origin (development)
CORS_ALLOWED_ORIGIN=http://localhost:3000

# Multiple origins using regex (production)
CORS_ALLOWED_ORIGIN=^https?://(localhost:3000|app\.example\.com)$
```

### 3. Run Tests

```bash
# Run CORS-specific tests
php vendor/bin/phpunit tests/Api/CorsTest.php

# Run all API tests
php vendor/bin/phpunit tests/Api/
```

## Configuration Files

| File | Purpose |
|------|---------|
| `config/packages/nelmio_cors.yaml` | Main CORS configuration |
| `.env` | Environment-specific origin configuration |
| `frontend/src/services/apiClient.js` | Frontend HTTP client with credentials |
| `docs/CORS_CONFIGURATION.md` | Detailed documentation |
| `tests/Api/CorsTest.php` | CORS integration tests |

## Testing CORS

### Automated Tests

```bash
php vendor/bin/phpunit tests/Api/CorsTest.php
```

Tests verify:
- ✅ CORS headers present on GET requests
- ✅ CORS headers present on POST requests
- ✅ Preflight OPTIONS requests handled correctly
- ✅ All HTTP methods are allowed
- ✅ Required headers are allowed
- ✅ Credentials flag is properly set

### Manual Testing

1. Start the application:
   ```bash
   docker compose up -d
   ```

2. Access frontend at `http://localhost:3000`

3. Open browser DevTools (F12) → Network tab

4. Make an API request from the frontend

5. Verify response headers include:
   - `Access-Control-Allow-Origin: http://localhost:3000`
   - `Access-Control-Allow-Credentials: true`
   - `Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS`

## Common Issues & Solutions

### Issue: CORS errors in browser console

**Check:**
1. `CORS_ALLOWED_ORIGIN` matches the frontend URL exactly
2. Nelmio bundle is properly installed: `composer require nelmio/cors-bundle`
3. Bundle is registered in `config/bundles.php`

### Issue: Credentials not being sent

**Solution:**
- Frontend includes `credentials: 'include'` ✅
- Backend has `allow_credentials: true` ✅
- Origin must be explicitly set (not `*`) ✅

### Issue: Preflight requests failing

**Solution:**
The Nelmio bundle automatically handles OPTIONS requests. Verify:
- Routes match the pattern `^/api`
- Requested method is in `allow_methods` list

## Security Considerations

✅ **Credentials enabled**: Frontend can send cookies for session-based auth
✅ **Explicit origins**: No wildcard `*` allowed when using credentials
✅ **Regex support**: Flexible origin matching for multiple domains
✅ **Preflight caching**: OPTIONS requests cached for 1 hour (3600s)

## Migration from Custom CORS

The custom `CorsSubscriber` class has been removed and replaced with Nelmio CORS Bundle because:

1. **Industry Standard**: Nelmio is the de-facto CORS solution for Symfony
2. **Well-Tested**: Used by thousands of projects, extensively tested
3. **Feature-Rich**: Supports regex patterns, per-path configuration, and more
4. **Maintained**: Regular updates and security fixes
5. **Flexible**: Easy to configure for different environments

## Additional Resources

- [Nelmio CORS Bundle Documentation](https://github.com/nelmio/NelmioCorsBundle/blob/master/README.md)
- [MDN CORS Guide](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)
- [Symfony Best Practices](https://symfony.com/doc/current/best_practices.html)
- Internal: `docs/CORS_CONFIGURATION.md`
