# Deployment Testing Checklist

This document provides a comprehensive checklist for testing the Family Plan deployment on Hostinger.

## Pre-Deployment Tests

- [ ] All required files are present
  - [ ] `docker-compose.hostinger.yml`
  - [ ] `docker/php/Dockerfile.prod`
  - [ ] `docker/react/Dockerfile.prod`
  - [ ] `.env.prod`
  - [ ] `docker/php/docker-entrypoint.sh`
  - [ ] `HOSTINGER_DEPLOYMENT.md`

- [ ] Docker Compose configuration is valid
  ```bash
  docker compose -f docker-compose.hostinger.yml config
  ```

- [ ] Environment configuration is set
  - [ ] `.env.prod.local` created from `.env.prod`
  - [ ] `APP_SECRET` updated with random secret
  - [ ] `POSTGRES_PASSWORD` set to strong password
  - [ ] `SUPER_ADMIN_PASSWORD` set to strong password
  - [ ] `SUPER_ADMIN_EMAIL` configured
  - [ ] Port numbers configured if needed

- [ ] Deployment verification script passes
  ```bash
  ./verify-deployment.sh
  ```

## Build Tests

- [ ] PHP Docker image builds successfully
  ```bash
  docker compose -f docker-compose.hostinger.yml build php
  ```

- [ ] React frontend Docker image builds successfully
  ```bash
  docker compose -f docker-compose.hostinger.yml build frontend
  ```

- [ ] All services can be started
  ```bash
  docker compose -f docker-compose.hostinger.yml up -d
  ```

- [ ] Database container is healthy
  ```bash
  docker compose -f docker-compose.hostinger.yml ps
  # Check that database shows as "healthy"
  ```

## Service Health Tests

- [ ] Database service
  - [ ] Container is running
  - [ ] Health check passes
  - [ ] Can connect to PostgreSQL
    ```bash
    docker compose -f docker-compose.hostinger.yml exec database psql -U app -d app -c "SELECT 1"
    ```

- [ ] PHP service
  - [ ] Container is running
  - [ ] PHP-FPM is listening on port 9000
  - [ ] Application code is present
    ```bash
    docker compose -f docker-compose.hostinger.yml exec php ls -la /app
    ```
  - [ ] Migrations ran successfully
    ```bash
    docker compose -f docker-compose.hostinger.yml logs php | grep "migration"
    ```
  - [ ] Super admin user created
    ```bash
    docker compose -f docker-compose.hostinger.yml logs php | grep "super admin"
    ```

- [ ] Nginx service
  - [ ] Container is running
  - [ ] Web server responds on configured port
    ```bash
    curl -I http://localhost:8080
    ```
  - [ ] Can serve static files
  - [ ] Can communicate with PHP-FPM

- [ ] React Frontend service (optional)
  - [ ] Container is running
  - [ ] Nginx serves built assets
  - [ ] Responds on configured port
    ```bash
    curl -I http://localhost:3001
    ```

## Application Tests

- [ ] Main application accessible
  - [ ] Home page loads at `http://server-ip:8080`
  - [ ] No 500 errors in logs
  - [ ] Assets (CSS/JS) load correctly

- [ ] Authentication works
  - [ ] Login page accessible at `/login`
  - [ ] Can log in with super admin credentials
  - [ ] User session persists
  - [ ] Logout works correctly

- [ ] API endpoints work
  - [ ] `/api/` endpoints are accessible
  - [ ] Authentication required for protected endpoints
  - [ ] Proper JSON responses

- [ ] Database operations
  - [ ] Can create new tasks
  - [ ] Can create new users
  - [ ] Data persists after container restart
  - [ ] Database backup works
    ```bash
    docker compose -f docker-compose.hostinger.yml exec database pg_dump -U app app > backup.sql
    ```

- [ ] React SPA (if applicable)
  - [ ] React app loads at `/app`
  - [ ] Frontend can communicate with backend API
  - [ ] Client-side routing works

## Performance Tests

- [ ] Page load times are acceptable
- [ ] Database queries are optimized
- [ ] Static assets are cached properly
- [ ] Gzip compression is enabled
- [ ] OPcache is working
  ```bash
  docker compose -f docker-compose.hostinger.yml exec php php -i | grep opcache
  ```

## Security Tests

- [ ] Environment variables are not exposed
- [ ] Database credentials are secure
- [ ] Admin password is strong
- [ ] HTTPS is configured (if domain is set up)
- [ ] Security headers are present
  ```bash
  curl -I http://localhost:8080 | grep -E "X-Frame-Options|X-Content-Type-Options|X-XSS-Protection"
  ```
- [ ] Hidden files are not accessible
  ```bash
  curl http://localhost:8080/.env
  # Should return 403 or 404
  ```

## Network Tests

- [ ] Services can communicate internally
  - [ ] PHP can connect to database
  - [ ] Nginx can proxy to PHP-FPM
  - [ ] Frontend can reach backend API

- [ ] External access works
  - [ ] Application accessible from outside the server
  - [ ] Firewall rules are configured correctly
  - [ ] Port forwarding works if needed

## Persistence Tests

- [ ] Data survives container restart
  ```bash
  # Create some test data
  # Restart containers
  docker compose -f docker-compose.hostinger.yml restart
  # Verify data still exists
  ```

- [ ] Data survives full stack restart
  ```bash
  docker compose -f docker-compose.hostinger.yml down
  docker compose -f docker-compose.hostinger.yml up -d
  # Verify data still exists
  ```

- [ ] Volume backups work
  ```bash
  docker run --rm -v family-plan_database_data:/data -v $(pwd):/backup alpine tar czf /backup/database-backup.tar.gz /data
  ```

## Maintenance Tests

- [ ] Logs are accessible
  ```bash
  docker compose -f docker-compose.hostinger.yml logs
  ```

- [ ] Can run console commands
  ```bash
  docker compose -f docker-compose.hostinger.yml exec php php bin/console list
  ```

- [ ] Can run migrations manually
  ```bash
  docker compose -f docker-compose.hostinger.yml exec php php bin/console doctrine:migrations:migrate
  ```

- [ ] Can access database for debugging
  ```bash
  docker compose -f docker-compose.hostinger.yml exec database psql -U app -d app
  ```

- [ ] Can update application
  ```bash
  git pull
  docker compose -f docker-compose.hostinger.yml up -d --build
  ```

## Monitoring Tests

- [ ] Container resource usage is reasonable
  ```bash
  docker stats
  ```

- [ ] Disk space is sufficient
  ```bash
  df -h
  ```

- [ ] Memory usage is acceptable
- [ ] CPU usage is reasonable
- [ ] No memory leaks detected

## Documentation Tests

- [ ] README is up to date
- [ ] Deployment guide is accurate
- [ ] Environment variable documentation is complete
- [ ] Troubleshooting section is helpful
- [ ] All commands in documentation work

## Rollback Tests

- [ ] Can stop all services
  ```bash
  docker compose -f docker-compose.hostinger.yml down
  ```

- [ ] Can restore from backup
- [ ] Can revert to previous version
- [ ] Data is not lost during rollback

## Production Readiness Checklist

- [ ] SSL/TLS certificates configured
- [ ] Domain name configured
- [ ] Regular backup schedule set up
- [ ] Monitoring/alerting configured
- [ ] Log rotation configured
- [ ] Resource limits set appropriately
- [ ] Firewall rules configured
- [ ] SSH access secured
- [ ] Server hardened
- [ ] Documentation updated

## Post-Deployment Verification

After deployment to production, verify:

- [ ] Application is accessible via domain name
- [ ] HTTPS works correctly
- [ ] All features work as expected
- [ ] Performance is acceptable
- [ ] No errors in production logs
- [ ] Database backups are running
- [ ] Monitoring is active
- [ ] Team has access credentials
- [ ] Support documentation is available

## Notes

- Tests should be run in a staging environment before production
- Keep track of any issues found during testing
- Document any workarounds or special configurations
- Update this checklist as needed

## Test Results

Date: _________________
Tester: _________________
Environment: _________________

Summary:
- Tests Passed: _____ / _____
- Tests Failed: _____ 
- Blockers: _____

Notes:
_____________________________________________
_____________________________________________
_____________________________________________
