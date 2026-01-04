# Docker Configuration for React Frontend

**⚠️ DEPRECATED - This directory is for legacy Webpack Encore React integration.**

**For the modern standalone React SPA, see [`/frontend`](../../frontend/README.md) directory instead.**

---

This directory contains Docker configurations for the legacy React frontend that was integrated with Symfony via Webpack Encore.

**Modern Architecture:**
- The frontend is now a standalone React SPA in the `/frontend` directory
- It has its own Dockerfile and runs independently
- It communicates with the backend via REST API
- See the main [README.md](../../README.md) for current setup instructions

## Legacy Files

- `Dockerfile` - Development Dockerfile for React app with hot reload
- `Dockerfile.prod` - Production-optimized Dockerfile with Nginx
- `nginx.conf` - Nginx configuration for serving React build files

## Development

The React development environment is configured in `compose.yaml`:

```bash
# Start all services (including React development server)
docker compose up

# Or start only React development service
docker compose up react-dev
```

The React dev service:
- Runs `npm run watch` for hot reload
- Watches for file changes with polling (works in Docker)
- Mounts local files for development
- Runs on port 3000 (configurable via REACT_DEV_PORT env var)

## Production

For production deployment, use the production compose file:

```bash
# Build and start production React service
docker compose -f compose.yaml -f compose.prod.yaml up react-prod --build

# Or build the image separately
docker build -f docker/react/Dockerfile.prod -t family-plan-react:latest .
docker run -p 3001:80 family-plan-react:latest
```

The production build:
- Uses multi-stage build for optimization
- Builds React app with webpack production mode
- Serves static files with Nginx
- Includes gzip compression
- Adds security headers
- Optimized caching

## Environment Variables

### Development
- `REACT_DEV_PORT` - Port for React dev server (default: 3000)
- `NODE_ENV` - Node environment (default: development)
- `CHOKIDAR_USEPOLLING` - Enable file watching in Docker (default: 1)
- `FAST_REFRESH` - Enable React Fast Refresh (default: true)

### Production
- `REACT_PORT` - Port for production React server (default: 3001)
- `NGINX_HOST` - Nginx server name (default: localhost)
- `NGINX_PORT` - Nginx internal port (default: 80)

## Docker Compose Services

### react-dev
Development service with hot reload:
```yaml
docker compose up react-dev
```
Access at: http://localhost:3000

### react-prod
Production-optimized service:
```yaml
docker compose -f compose.yaml -f compose.prod.yaml up react-prod
```
Access at: http://localhost:3001

## Building for Production

The production build creates an optimized bundle:

```bash
# Inside the container or locally
npm run build

# Output will be in public/build/
```

The Nginx server then serves these static files with:
- Long-term caching (1 year for immutable assets)
- Gzip compression
- Security headers (X-Frame-Options, X-Content-Type-Options, etc.)

## Integration with Main App

The React app integrates with the main Symfony application:

1. **Development**: Webpack Encore compiles assets, served by Symfony
2. **Production**: Can be served by Nginx directly or through Symfony

Access points:
- Main app: http://localhost:8080
- React SPA: http://localhost:8080/app
- API: http://localhost:8080/api/*

## Troubleshooting

### Hot reload not working
- Ensure `CHOKIDAR_USEPOLLING=1` is set
- Check that volumes are properly mounted
- Verify file permissions

### Build errors
- Clear node_modules: `docker compose run react-dev rm -rf node_modules`
- Rebuild: `docker compose build react-dev --no-cache`

### Permission errors
- Ensure the user has write access to mounted volumes
- Check Docker volume permissions

## Advanced Usage

### Custom Webpack Configuration
Edit `webpack.config.js` to customize the build process.

### Environment-specific Builds
```bash
# Development build
docker compose run react-dev npm run dev

# Production build
docker compose run react-dev npm run build

# Watch mode
docker compose run react-dev npm run watch
```
