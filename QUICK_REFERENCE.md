# Docker Deployment Quick Reference

Quick command reference for deploying and managing the Family Plan application on Hostinger.

## Initial Deployment

```bash
# 1. Clone and navigate to repository
git clone https://github.com/jakubciszak/family-plan.git
cd family-plan

# 2. Configure environment
cp .env.prod .env.prod.local
nano .env.prod.local  # Update with production values

# 3. Verify setup
./verify-deployment.sh

# 4. Build and start services
docker compose -f docker-compose.hostinger.yml up -d --build
```

## Daily Operations

### View Service Status
```bash
docker compose -f docker-compose.hostinger.yml ps
```

### View Logs
```bash
# All services
docker compose -f docker-compose.hostinger.yml logs -f

# Specific service
docker compose -f docker-compose.hostinger.yml logs -f php
docker compose -f docker-compose.hostinger.yml logs -f nginx
docker compose -f docker-compose.hostinger.yml logs -f database
```

### Restart Services
```bash
# All services
docker compose -f docker-compose.hostinger.yml restart

# Specific service
docker compose -f docker-compose.hostinger.yml restart php
docker compose -f docker-compose.hostinger.yml restart nginx
```

### Stop Services
```bash
docker compose -f docker-compose.hostinger.yml stop
```

### Start Services
```bash
docker compose -f docker-compose.hostinger.yml start
```

### Stop and Remove Containers
```bash
docker compose -f docker-compose.hostinger.yml down
```

## Database Operations

### Access Database CLI
```bash
docker compose -f docker-compose.hostinger.yml exec database psql -U app -d app
```

### Run Migrations
```bash
docker compose -f docker-compose.hostinger.yml exec php php bin/console doctrine:migrations:migrate
```

### Check Migration Status
```bash
docker compose -f docker-compose.hostinger.yml exec php php bin/console doctrine:migrations:status
```

### Backup Database
```bash
docker compose -f docker-compose.hostinger.yml exec database pg_dump -U app app > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Restore Database
```bash
cat backup.sql | docker compose -f docker-compose.hostinger.yml exec -T database psql -U app -d app
```

## Application Commands

### Create/Update Super Admin
```bash
docker compose -f docker-compose.hostinger.yml exec php php bin/console app:create-super-admin
```

### Clear Cache
```bash
docker compose -f docker-compose.hostinger.yml exec php php bin/console cache:clear --env=prod
```

### Run Console Command
```bash
docker compose -f docker-compose.hostinger.yml exec php php bin/console <command>
```

### Access PHP Container Shell
```bash
docker compose -f docker-compose.hostinger.yml exec php sh
```

## Updates and Maintenance

### Update Application
```bash
# 1. Pull latest code
git pull origin main

# 2. Rebuild and restart
docker compose -f docker-compose.hostinger.yml up -d --build

# 3. Run migrations if needed
docker compose -f docker-compose.hostinger.yml exec php php bin/console doctrine:migrations:migrate
```

### View Resource Usage
```bash
docker stats
```

### Clean Up Unused Resources
```bash
# Remove unused images
docker image prune -a

# Remove unused volumes
docker volume prune

# Remove all unused resources
docker system prune -a
```

## Troubleshooting

### Check Container Health
```bash
docker compose -f docker-compose.hostinger.yml ps
```

### Inspect Container
```bash
docker compose -f docker-compose.hostinger.yml exec <service> sh
```

### Check PHP Configuration
```bash
docker compose -f docker-compose.hostinger.yml exec php php -i
```

### Check Nginx Configuration
```bash
docker compose -f docker-compose.hostinger.yml exec nginx nginx -t
```

### View Container Details
```bash
docker compose -f docker-compose.hostinger.yml config
```

### Rebuild Specific Service
```bash
docker compose -f docker-compose.hostinger.yml up -d --build --no-deps <service>
```

## Security

### Check for Exposed Secrets
```bash
# Ensure .env files are not in the image
docker compose -f docker-compose.hostinger.yml exec php find /app -name ".env*"
```

### Update Passwords
1. Update in `.env.prod.local`
2. Restart containers
3. Update database password:
```bash
docker compose -f docker-compose.hostinger.yml exec database psql -U postgres -c "ALTER USER app PASSWORD 'new_password';"
```

## Backup Strategy

### Full Backup
```bash
#!/bin/bash
BACKUP_DIR="backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR

# Database backup
docker compose -f docker-compose.hostinger.yml exec database pg_dump -U app app > $BACKUP_DIR/database.sql

# Volume backup
docker run --rm -v family-plan_database_data:/data -v $(pwd)/$BACKUP_DIR:/backup alpine tar czf /backup/volumes.tar.gz /data

# Code backup (if modified)
tar czf $BACKUP_DIR/code.tar.gz .
```

## Monitoring

### Watch Logs in Real-Time
```bash
docker compose -f docker-compose.hostinger.yml logs -f --tail=100
```

### Check Disk Usage
```bash
docker system df
```

### Monitor Container Resources
```bash
docker stats --no-stream
```

## Environment Variables

Quick reference for important environment variables in `.env.prod.local`:

- `APP_ENV=prod` - Application environment
- `APP_SECRET=<random-secret>` - Symfony secret key
- `DATABASE_URL=<connection-string>` - Database connection
- `POSTGRES_PASSWORD=<password>` - Database password
- `SUPER_ADMIN_EMAIL=<email>` - Admin email
- `SUPER_ADMIN_PASSWORD=<password>` - Admin password
- `APP_PORT=8080` - Main application port
- `REACT_PORT=3001` - React frontend port

## Network Diagnostics

### Test Database Connection
```bash
docker compose -f docker-compose.hostinger.yml exec php php bin/console doctrine:query:sql "SELECT 1"
```

### Check Port Bindings
```bash
docker compose -f docker-compose.hostinger.yml ps
netstat -tulpn | grep -E "8080|3001"
```

### Test Service Connectivity
```bash
# From host to nginx
curl -I http://localhost:8080

# From PHP to database
docker compose -f docker-compose.hostinger.yml exec php ping database
```

## Performance

### Check OPcache Status
```bash
docker compose -f docker-compose.hostinger.yml exec php php -i | grep opcache
```

### Clear OPcache
```bash
docker compose -f docker-compose.hostinger.yml restart php
```

## Support

For detailed information, see:
- [Full Deployment Guide](HOSTINGER_DEPLOYMENT.md)
- [Testing Checklist](DEPLOYMENT_TESTING.md)
- [Main README](README.md)
