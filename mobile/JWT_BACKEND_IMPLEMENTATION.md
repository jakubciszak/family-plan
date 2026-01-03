# JWT Authentication Implementation Guide

## Overview

The mobile app requires JWT (JSON Web Token) authentication instead of the session-based authentication currently used by the web frontend. This document outlines the backend changes needed to support JWT authentication while maintaining backward compatibility with the web frontend.

## Current State

The backend currently uses Symfony Security with session-based authentication:
- Web frontend uses cookies for session management
- Authentication is handled via `/api/auth/login` endpoint
- User sessions are maintained server-side

## Required Changes

### 1. Install JWT Bundle

```bash
composer require lexik/jwt-authentication-bundle
```

### 2. Generate JWT Keys

```bash
php bin/console lexik:jwt:generate-keypair
```

This will create:
- `config/jwt/private.pem`
- `config/jwt/public.pem`

Add to `.gitignore`:
```
/config/jwt/*.pem
```

### 3. Configure JWT Bundle

Create `config/packages/lexik_jwt_authentication.yaml`:

```yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: 3600 # 1 hour
```

Add to `.env`:
```
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-passphrase-here
```

### 4. Update Security Configuration

Update `config/packages/security.yaml` to support both session and JWT authentication:

```yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: false # Allow both stateless (JWT) and stateful (session)
            entry_point: form_login
            
            # Session-based authentication (for web)
            form_login:
                login_path: /api/auth/login
                check_path: /api/auth/login
                success_handler: App\Security\AuthenticationSuccessHandler
                failure_handler: App\Security\AuthenticationFailureHandler
            
            # JWT authentication (for mobile)
            jwt: ~
            
            logout:
                path: /api/auth/logout
```

### 5. Create Authentication Success Handler

Create `src/Security/AuthenticationSuccessHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        
        // Check if request expects JWT (e.g., from mobile app)
        $wantsJson = $request->getContentTypeFormat() === 'json' || 
                     $request->headers->get('Accept') === 'application/json';
        
        $userData = [
            'id' => $user->getId()->value(),
            'name' => $user->getName(),
            'email' => $user->getEmail()->value(),
            'role' => $user->getRole()->value,
        ];
        
        $response = [
            'message' => 'Login successful',
            'user' => $userData,
        ];
        
        // For mobile/JWT requests, include JWT token
        if ($wantsJson) {
            $jwt = $this->jwtManager->create($user);
            $response['token'] = $jwt;
        }
        
        return new JsonResponse($response);
    }
}
```

### 6. Update AuthApiController

Modify `/api/auth/me` endpoint to work with both session and JWT:

```php
#[Route('/me', name: 'current_user', methods: ['GET'])]
public function currentUser(#[CurrentUser] ?User $user): JsonResponse
{
    if (!$user) {
        return $this->json([
            'error' => 'Not authenticated'
        ], Response::HTTP_UNAUTHORIZED);
    }

    return $this->json([
        'id' => $user->getId()->value(),
        'name' => $user->getName(),
        'email' => $user->getEmail()->value(),
        'role' => $user->getRole()->value,
    ]);
}
```

### 7. Add JWT Claims

Create event subscriber to add custom claims to JWT:

```php
<?php

declare(strict_types=1);

namespace App\Security\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JWTCreatedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_jwt_created' => 'onJWTCreated',
        ];
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $payload = $event->getData();

        // Add custom claims
        $payload['id'] = $user->getId()->value();
        $payload['name'] = $user->getName();
        $payload['role'] = $user->getRole()->value;

        $event->setData($payload);
    }
}
```

## Testing

### Test JWT Login (Mobile)

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@familyplan.local","password":"admin123"}'
```

Expected response:
```json
{
  "message": "Login successful",
  "user": {
    "id": "...",
    "name": "Super Admin",
    "email": "admin@familyplan.local",
    "role": "ROLE_ADMIN"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

### Test JWT Protected Endpoint

```bash
curl -X GET http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer YOUR_JWT_TOKEN_HERE"
```

### Test Session Login (Web)

The web frontend should continue working with session-based authentication as before.

## Security Considerations

1. **Token Expiration**: JWT tokens expire after 1 hour (configurable)
2. **Token Refresh**: Consider implementing refresh tokens for long-lived mobile sessions
3. **HTTPS**: Always use HTTPS in production to protect JWT tokens
4. **Key Security**: Keep JWT private key secure and never commit to repository
5. **Token Storage**: Mobile app stores tokens in AsyncStorage (encrypted on device)

## Backward Compatibility

The implementation maintains full backward compatibility:
- Web frontend continues using session-based authentication
- Mobile app uses JWT authentication
- Both can coexist and use the same backend endpoints
- Authentication method is detected based on request headers

## Migration Path

1. Install and configure JWT bundle
2. Create authentication handlers
3. Update security configuration
4. Test both session and JWT authentication
5. Deploy backend changes
6. Deploy mobile app

## Additional Resources

- [LexikJWTAuthenticationBundle Documentation](https://github.com/lexik/LexikJWTAuthenticationBundle/blob/2.x/Resources/doc/index.rst)
- [Symfony Security Documentation](https://symfony.com/doc/current/security.html)
- [JWT.io](https://jwt.io/) - JWT debugger and information
