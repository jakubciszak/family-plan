# Family Plan - Docker Deployment Summary

## Overview

This repository now includes complete Docker-based deployment configuration for hosting the Family Plan application on Hostinger or any Docker-capable hosting platform.

## What's Included

### Docker Configuration Files

1. **`docker-compose.hostinger.yml`** - Production orchestration file
   - PostgreSQL database service
   - PHP-FPM backend service
   - Nginx web server
   - React frontend service (optional)

2. **`docker/php/Dockerfile.prod`** - Production PHP image
   - Multi-stage build for optimization
   - PHP 8.5 with required extensions
   - Composer dependencies
   - Compiled frontend assets
   - OPcache configuration

3. **`docker/react/Dockerfile.prod`** - Production React image
   - Node.js build stage
   - Nginx serving optimized static files
   - Gzip compression
   - Security headers

4. **`docker/php/docker-entrypoint.sh`** - Initialization script
   - Database health check
   - Automatic migrations
   - Super admin user creation

### Configuration Files

1. **`.env.prod`** - Production environment template
   - Database configuration
   - Application secrets
   - Admin credentials
   - Port settings

2. **`.dockerignore`** - Build optimization
   - Excludes unnecessary files from Docker context
   - Reduces image size
   - Speeds up builds

3. **`babel.config.js`** - Frontend build configuration
   - React preset
   - ES6+ transpilation
   - Polyfills configuration

### Documentation

1. **`HOSTINGER_DEPLOYMENT.md`** - Complete deployment guide
   - Step-by-step instructions
   - Architecture overview
   - Troubleshooting section
   - Security considerations

2. **`DEPLOYMENT_TESTING.md`** - Testing checklist
   - Pre-deployment tests
   - Service health checks
   - Application validation
   - Production readiness

3. **`QUICK_REFERENCE.md`** - Command reference
   - Common operations
   - Database commands
   - Troubleshooting commands
   - Maintenance tasks

### Tools

1. **`verify-deployment.sh`** - Validation script
   - Checks required files
   - Validates configurations
   - Provides deployment guidance

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Hostinger Server                      │
│                                                          │
│  ┌──────────┐         ┌──────────┐       ┌──────────┐  │
│  │  Nginx   │◄────────│ Internet │──────►│  React   │  │
│  │  :8080   │         │  Users   │       │  :3001   │  │
│  └────┬─────┘         └──────────┘       └──────────┘  │
│       │                                                  │
│       │ FastCGI                                          │
│       ▼                                                  │
│  ┌──────────┐                                           │
│  │ PHP-FPM  │                                           │
│  │ Backend  │                                           │
│  └────┬─────┘                                           │
│       │                                                  │
│       │ PDO                                              │
│       ▼                                                  │
│  ┌──────────┐                                           │
│  │PostgreSQL│                                           │
│  │ Database │                                           │
│  └──────────┘                                           │
│                                                          │
│  Docker Network: family-plan-network                    │
└─────────────────────────────────────────────────────────┘
```

## Deployment Process

### 1. Pre-Deployment

```bash
# Verify setup
./verify-deployment.sh

# Configure environment
cp .env.prod .env.prod.local
nano .env.prod.local  # Update with production values
```

### 2. Build and Deploy

```bash
# Build images
docker compose -f docker-compose.hostinger.yml build

# Start services
docker compose -f docker-compose.hostinger.yml up -d
```

### 3. Verification

```bash
# Check status
docker compose -f docker-compose.hostinger.yml ps

# View logs
docker compose -f docker-compose.hostinger.yml logs

# Test application
curl http://localhost:8080
```

## Key Features

### Automatic Setup
- Database migrations run automatically on startup
- Super admin user created from environment variables
- Health checks ensure services start in correct order

### Production Optimized
- Multi-stage Docker builds for smaller images
- OPcache enabled and tuned for performance
- Asset bundling and minification
- Gzip compression enabled

### Secure by Default
- Environment variables for sensitive data
- Security headers configured
- Hidden files protected
- Database password required

### Easy Maintenance
- Simple update process with git pull
- Database backup/restore commands
- Log access for troubleshooting
- Container health monitoring

## Service Ports

- **Main Application (Nginx + PHP)**: 8080 (configurable via `APP_PORT`)
- **React Frontend** (optional): 3001 (configurable via `REACT_PORT`)
- **Database**: Internal only (not exposed to host)

## Environment Variables

Critical variables to configure in `.env.prod.local`:

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_SECRET` | Symfony secret key | Random 32+ chars |
| `POSTGRES_PASSWORD` | Database password | Strong password |
| `SUPER_ADMIN_EMAIL` | Admin email | admin@example.com |
| `SUPER_ADMIN_PASSWORD` | Admin password | Strong password |
| `APP_PORT` | Application port | 8080 |

## Volume Persistence

The following volumes ensure data persistence:

- **`database_data`**: PostgreSQL data directory
  - Contains all application data
  - Survives container restarts
  - Should be backed up regularly

## Network Configuration

Services communicate via the `family-plan-network` bridge network:

- PHP can connect to database via hostname `database`
- Nginx proxies to PHP via hostname `php:9000`
- Services are isolated from external access except through mapped ports

## Resource Requirements

### Minimum Requirements
- CPU: 2 cores
- RAM: 2 GB
- Disk: 10 GB
- Docker: 20.10+
- Docker Compose: 2.0+

### Recommended for Production
- CPU: 4+ cores
- RAM: 4+ GB
- Disk: 20+ GB SSD
- Regular backups
- Monitoring enabled

## Backup Strategy

### Database Backup
```bash
docker compose -f docker-compose.hostinger.yml exec database \
  pg_dump -U app app > backup.sql
```

### Volume Backup
```bash
docker run --rm \
  -v family-plan_database_data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/database-backup.tar.gz /data
```

## Monitoring

### Health Checks
- Database: PostgreSQL readiness check
- Application: Nginx and PHP-FPM process monitoring
- Logs: Centralized logging via Docker

### Performance Metrics
```bash
# Resource usage
docker stats

# Container health
docker compose -f docker-compose.hostinger.yml ps
```

## Troubleshooting

Common issues and solutions:

1. **Container won't start**: Check logs with `docker compose logs`
2. **Database connection failed**: Verify credentials in `.env.prod.local`
3. **Permission errors**: Run `chown` on `/app/var` directory
4. **Port already in use**: Change `APP_PORT` in environment file

See `HOSTINGER_DEPLOYMENT.md` for detailed troubleshooting.

## Support Resources

- **[Deployment Guide](HOSTINGER_DEPLOYMENT.md)** - Complete setup instructions
- **[Testing Checklist](DEPLOYMENT_TESTING.md)** - Validation procedures
- **[Quick Reference](QUICK_REFERENCE.md)** - Common commands
- **[Main README](README.md)** - Application documentation

## Next Steps

1. ✅ Review deployment documentation
2. ✅ Configure environment variables
3. ✅ Run verification script
4. ⏳ Deploy to Hostinger
5. ⏳ Configure domain and SSL
6. ⏳ Set up backups
7. ⏳ Configure monitoring

## Updates

To update the application:

```bash
git pull origin main
docker compose -f docker-compose.hostinger.yml up -d --build
```

## License

Same as main application license.

## Contributing

Improvements to deployment configuration are welcome. Please test thoroughly before submitting pull requests.

---

**Ready for deployment?** Start with [HOSTINGER_DEPLOYMENT.md](HOSTINGER_DEPLOYMENT.md)
