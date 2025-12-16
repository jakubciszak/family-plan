# Frontend and Backend Separation - Architecture Guide

## Overview

Family Plan has been completely separated into two independent applications:

1. **Frontend**: React Single Page Application (SPA)
2. **Backend**: Symfony REST API

This separation allows:
- Independent deployment and scaling
- Technology stack flexibility
- Clear separation of concerns
- Better development workflow
- Easier maintenance and testing

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         Client Browser                          │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             │ HTTP/HTTPS
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Frontend Container (Nginx)                    │
│                  React SPA (Port 3000/80)                       │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  - Static HTML/CSS/JS files                               │  │
│  │  - Webpack bundled React app                              │  │
│  │  - Nginx serves static files                              │  │
│  └───────────────────────────────────────────────────────────┘  │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             │ API Calls (CORS enabled)
                             │ /api/*
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│               Backend Container (PHP-FPM + Nginx)               │
│                  Symfony API (Port 8080)                        │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  REST API Endpoints:                                      │  │
│  │  - POST /api/auth/login                                   │  │
│  │  - GET  /api/auth/me                                      │  │
│  │  - GET  /api/tasks                                        │  │
│  │  - POST /api/tasks                                        │  │
│  │  - PUT  /api/tasks/{id}                                   │  │
│  │  - DELETE /api/tasks/{id}                                 │  │
│  └───────────────────────────────────────────────────────────┘  │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             │ Database Queries
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Database Container                            │
│                   PostgreSQL 16                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Directory Structure

```
family-plan/
├── frontend/                    # Frontend Application (STANDALONE)
│   ├── src/                     # React source code
│   │   ├── App.jsx              # Main app component
│   │   ├── index.jsx            # Entry point
│   │   ├── pages/               # Page components
│   │   ├── services/            # API client
│   │   └── styles/              # CSS styles
│   ├── public/                  # Static assets
│   │   └── index.html           # HTML template
│   ├── dist/                    # Build output (not committed)
│   ├── Dockerfile               # Development container
│   ├── Dockerfile.prod          # Production container
│   ├── nginx.conf               # Nginx configuration
│   ├── webpack.config.js        # Build configuration
│   ├── package.json             # Dependencies
│   └── README.md                # Frontend documentation
│
├── src/                         # Backend Application
│   ├── Shared/                  # Shared kernel
│   │   └── Infrastructure/
│   │       └── EventSubscriber/
│   │           └── CorsSubscriber.php  # CORS support
│   ├── UserManagement/          # User bounded context
│   ├── TaskManagement/          # Task bounded context
│   └── Presentation/
│       └── Api/                 # REST API controllers
│           ├── AuthApiController.php
│           ├── TaskApiController.php
│           └── UserApiController.php
│
├── docker/                      # Docker configurations
│   ├── php/                     # Backend container config
│   └── nginx/                   # Backend nginx config
│
├── compose.yaml                 # Development compose
├── compose.prod.yaml            # Production compose
├── docker-compose.hostinger.yml # Deployment compose
└── README.md                    # Main documentation
```

## Communication Flow

### 1. User Access
1. User navigates to frontend URL (e.g., http://localhost:3000)
2. Nginx serves the React SPA (index.html + bundled JS)
3. React app loads in the browser

### 2. Authentication
1. User enters credentials in React login form
2. Frontend sends POST request to `http://backend:8080/api/auth/login`
3. Backend validates credentials and returns JWT/session
4. Frontend stores authentication token
5. Frontend redirects to main application

### 3. API Communication
1. Frontend makes authenticated API requests
2. CORS headers allow cross-origin requests
3. Backend processes requests and returns JSON responses
4. Frontend updates UI based on response

### 4. Session Management
- Backend handles session/JWT validation
- Frontend includes auth token in all requests
- CORS enables cross-origin cookie sharing

## CORS Configuration

The backend includes CORS support via `CorsSubscriber`:

**Backend Configuration** (`src/Shared/Infrastructure/EventSubscriber/CorsSubscriber.php`):
- Handles preflight OPTIONS requests
- Adds CORS headers to all API responses
- Configurable allowed origin via environment variable

**Environment Variables** (`.env`):
```bash
CORS_ALLOWED_ORIGIN=http://localhost:3000
```

For production, update to your frontend domain:
```bash
CORS_ALLOWED_ORIGIN=https://your-frontend-domain.com
```

## Development Workflow

### Starting Services

**Option 1: All services together**
```bash
docker compose up -d
# Frontend: http://localhost:3000
# Backend API: http://localhost:8080/api
```

**Option 2: Services independently**

Backend only:
```bash
docker compose up -d database php nginx
# API: http://localhost:8080/api
```

Frontend only:
```bash
cd frontend
npm install
npm start
# App: http://localhost:3000
```

### Development Tips

1. **Frontend Development**
   - Hot reload enabled on port 3000
   - Proxies API calls to backend
   - No need to rebuild for code changes

2. **Backend Development**
   - PHP changes reflected immediately
   - Runs on port 8080
   - Use Symfony profiler for debugging

3. **Database Changes**
   - Run migrations in backend container
   - Both frontend and backend access same database

## Production Deployment

### Deployment Options

**Option 1: Docker Compose (Recommended)**
```bash
docker compose -f docker-compose.hostinger.yml up -d --build
```

This starts:
- Frontend container on port 3000
- Backend container on port 8080
- Database container
- All connected via bridge network

**Option 2: Separate Deployments**

Frontend:
```bash
cd frontend
docker build -t frontend:prod -f Dockerfile.prod .
docker run -p 3000:80 -e REACT_APP_API_URL=https://api.yourdomain.com frontend:prod
```

Backend:
```bash
docker build -t backend:prod -f docker/php/Dockerfile.prod .
docker run -p 8080:80 backend:prod
```

### Environment Configuration

**Frontend** (`frontend/.env`):
```bash
REACT_APP_API_URL=https://api.yourdomain.com
```

**Backend** (`.env.prod`):
```bash
APP_ENV=prod
APP_SECRET=your-secret-key
DATABASE_URL=postgresql://user:pass@db:5432/dbname
CORS_ALLOWED_ORIGIN=https://your-frontend-domain.com
```

## Security Considerations

1. **CORS Configuration**
   - Always set specific origin in production
   - Never use wildcard (*) in production
   - Configure CORS_ALLOWED_ORIGIN environment variable

2. **API Authentication**
   - All API endpoints require authentication
   - Frontend includes auth token in requests
   - Backend validates tokens on each request

3. **HTTPS in Production**
   - Always use HTTPS for both frontend and backend
   - Configure SSL certificates
   - Update CORS_ALLOWED_ORIGIN to use https://

4. **Environment Variables**
   - Never commit sensitive values
   - Use .env.local for local overrides
   - Configure secrets in production environment

## Troubleshooting

### Frontend Can't Connect to Backend

1. Check CORS configuration:
   ```bash
   # In backend .env
   CORS_ALLOWED_ORIGIN=http://localhost:3000
   ```

2. Check API URL in frontend:
   ```bash
   # In frontend/.env
   REACT_APP_API_URL=http://localhost:8080
   ```

3. Verify backend is running:
   ```bash
   curl http://localhost:8080/api/health
   ```

### CORS Errors

Common solutions:
1. Restart backend after CORS configuration changes
2. Check browser console for specific CORS error
3. Verify CORS_ALLOWED_ORIGIN matches frontend URL exactly
4. Ensure preflight OPTIONS requests are handled

### Build Failures

Frontend:
```bash
cd frontend
rm -rf node_modules dist
npm install
npm run build
```

Backend:
```bash
composer install
php bin/console cache:clear
```

## Migration from Monolithic Setup

The previous setup had React embedded in the Symfony application:
- React in `assets/react/`
- Served via Symfony route `/app`
- Built with Webpack Encore
- Mixed deployment

New setup:
- ✅ Frontend completely independent
- ✅ Served from separate container
- ✅ Own build process and configuration
- ✅ Can be deployed separately
- ✅ Clear API boundaries
- ✅ Better scalability

### Removed Components

- `src/Presentation/Controller/ReactAppController.php` - No longer needed
- `templates/react/index.html.twig` - Replaced by `frontend/public/index.html`
- React Webpack Encore configuration in main app - Moved to `frontend/webpack.config.js`

### Added Components

- `frontend/` - Complete standalone React application
- `src/Shared/Infrastructure/EventSubscriber/CorsSubscriber.php` - CORS support
- `frontend/Dockerfile` and `frontend/Dockerfile.prod` - Frontend containers
- `BACKEND.md` - Backend documentation
- `frontend/README.md` - Frontend documentation

## Benefits of Separation

1. **Independent Deployment**
   - Deploy frontend and backend separately
   - Scale independently based on load
   - Different release cycles

2. **Technology Flexibility**
   - Update frontend framework without affecting backend
   - Use different backend languages/frameworks
   - Add mobile apps using same API

3. **Development Experience**
   - Frontend and backend teams can work independently
   - Faster hot reload in development
   - Clear API contracts

4. **Performance**
   - Frontend served as static files (fast CDN delivery)
   - Backend optimized for API responses
   - Better caching strategies

5. **Security**
   - Clear security boundaries
   - API-first design
   - Easier to audit and secure

## Next Steps

1. **Testing**: Add integration tests for API endpoints
2. **CI/CD**: Set up automated deployment pipelines
3. **Monitoring**: Add monitoring for both services
4. **Documentation**: Keep API documentation up to date
5. **Mobile**: Consider mobile app using same backend API
