# Hostinger Deployment Guide for Family Plan

This guide provides step-by-step instructions for deploying the Family Plan application to Hostinger using Docker.

## Overview

The Family Plan application consists of three main services:
1. **PostgreSQL Database** - Data persistence layer
2. **PHP-FPM Backend** - Symfony application server
3. **Nginx** - Web server and reverse proxy
4. **React Frontend** (Optional) - Standalone frontend service

## Prerequisites

- Hostinger VPS or hosting plan with Docker support
- Docker and Docker Compose installed on the server
- Domain name configured to point to your server
- SSH access to your server

## Architecture

```
┌─────────────────────────────────────────┐
│           Hostinger Server              │
│                                         │
│  ┌─────────┐         ┌──────────────┐  │
│  │  Nginx  │────────▶│   PHP-FPM    │  │
│  │  :8080  │         │   Backend    │  │
│  └─────────┘         └──────────────┘  │
│       │                      │          │
│       │                      ▼          │
│       │              ┌──────────────┐  │
│       │              │  PostgreSQL  │  │
│       │              │   Database   │  │
│       │              └──────────────┘  │
│       │                                 │
│  ┌─────────┐                           │
│  │ React   │ (Optional)                │
│  │ :3001   │                           │
│  └─────────┘                           │
└─────────────────────────────────────────┘
```

## Deployment Steps

### Step 1: Prepare Your Server

SSH into your Hostinger server:
```bash
ssh your-username@your-server-ip
```

Install Docker and Docker Compose if not already installed:
```bash
# Update package index
sudo apt-get update

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo apt-get install docker-compose-plugin
```

### Step 2: Clone the Repository

```bash
cd /var/www  # or your preferred directory
git clone https://github.com/jakubciszak/family-plan.git
cd family-plan
```

### Step 3: Configure Environment Variables

Create a production environment file:
```bash
cp .env.prod .env.prod.local
```

Edit the `.env.prod.local` file with your production values:
```bash
nano .env.prod.local
```

**Important: Update these values:**
- `APP_SECRET` - Generate a new random secret key
- `POSTGRES_PASSWORD` - Set a strong database password
- `SUPER_ADMIN_PASSWORD` - Set a strong admin password
- `SUPER_ADMIN_EMAIL` - Set your admin email
- `APP_PORT` - Port for the main application (default: 8080)

Example production configuration:
```env
APP_ENV=prod
APP_SECRET=your-random-secret-key-here
POSTGRES_DB=family_plan
POSTGRES_USER=family_plan_user
POSTGRES_PASSWORD=your-strong-database-password
SUPER_ADMIN_EMAIL=admin@yourdomain.com
SUPER_ADMIN_NAME="Admin"
SUPER_ADMIN_PASSWORD=your-strong-admin-password
APP_PORT=8080
REACT_PORT=3001
```

### Step 4: Build and Start the Application

Build the Docker images:
```bash
docker compose -f docker-compose.hostinger.yml build
```

Start all services:
```bash
docker compose -f docker-compose.hostinger.yml up -d
```

This will:
1. Start the PostgreSQL database
2. Build and start the PHP backend with migrations
3. Create the super admin user automatically
4. Start the Nginx web server
5. (Optional) Build and start the React frontend

### Step 5: Verify the Deployment

Check that all containers are running:
```bash
docker compose -f docker-compose.hostinger.yml ps
```

You should see all services in "Up" state:
```
NAME                    STATUS
family-plan-db          Up (healthy)
family-plan-php         Up
family-plan-nginx       Up
family-plan-frontend    Up
```

Check the logs:
```bash
# View all logs
docker compose -f docker-compose.hostinger.yml logs

# View specific service logs
docker compose -f docker-compose.hostinger.yml logs php
docker compose -f docker-compose.hostinger.yml logs nginx
```

### Step 6: Access Your Application

The application should now be accessible at:
- **Main Application**: `http://your-server-ip:8080`
- **React Frontend** (optional): `http://your-server-ip:3001`

Login with your super admin credentials:
- Email: The email you set in `SUPER_ADMIN_EMAIL`
- Password: The password you set in `SUPER_ADMIN_PASSWORD`

### Step 7: Configure Domain (Optional)

If you have a domain name, configure it to point to your application:

1. Update your DNS records to point to your server IP
2. Configure Nginx to use your domain:

Create a custom Nginx configuration:
```bash
nano docker/nginx/production.conf
```

Add your domain configuration:
```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    
    root /app/public;
    index index.php;
    
    location / {
        try_files $uri /index.php$is_args$args;
    }
    
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $document_root;
    }
    
    location ~ /\. {
        deny all;
    }
}
```

Update the docker-compose file to use the new configuration and restart.

### Step 8: Enable SSL/HTTPS (Recommended)

For production, enable HTTPS using Let's Encrypt:

```bash
# Install certbot
sudo apt-get install certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

## Maintenance Commands

### View Logs
```bash
docker compose -f docker-compose.hostinger.yml logs -f
```

### Restart Services
```bash
docker compose -f docker-compose.hostinger.yml restart
```

### Stop Services
```bash
docker compose -f docker-compose.hostinger.yml down
```

### Update Application
```bash
# Pull latest changes
git pull origin main

# Rebuild and restart
docker compose -f docker-compose.hostinger.yml up -d --build
```

### Run Database Migrations Manually
```bash
docker compose -f docker-compose.hostinger.yml exec php php bin/console doctrine:migrations:migrate
```

### Create/Update Super Admin
```bash
docker compose -f docker-compose.hostinger.yml exec php php bin/console app:create-super-admin
```

### Access Database
```bash
docker compose -f docker-compose.hostinger.yml exec database psql -U family_plan_user -d family_plan
```

### Backup Database
```bash
docker compose -f docker-compose.hostinger.yml exec database pg_dump -U family_plan_user family_plan > backup.sql
```

### Restore Database
```bash
docker compose -f docker-compose.hostinger.yml exec -T database psql -U family_plan_user family_plan < backup.sql
```

## Troubleshooting

### Container won't start
```bash
# Check container logs
docker compose -f docker-compose.hostinger.yml logs <service-name>

# Check container status
docker compose -f docker-compose.hostinger.yml ps
```

### Database connection errors
1. Ensure the database container is healthy
2. Verify environment variables are correct
3. Check the DATABASE_URL in .env.prod.local

### Permission errors
```bash
# Fix permissions on var directory
docker compose -f docker-compose.hostinger.yml exec php chown -R www-data:www-data /app/var
```

### Application not accessible
1. Check if the port is open on your firewall
2. Verify Nginx is running: `docker compose -f docker-compose.hostinger.yml logs nginx`
3. Check if the correct port is mapped in docker-compose.hostinger.yml

## Security Considerations

1. **Change default passwords**: Always use strong, unique passwords for production
2. **Use HTTPS**: Enable SSL/TLS certificates for encrypted communication
3. **Firewall**: Configure firewall to only allow necessary ports
4. **Regular updates**: Keep Docker images and application dependencies up to date
5. **Database backups**: Set up automated database backups
6. **Environment files**: Ensure .env.prod.local is not committed to git

## Performance Optimization

### Enable OPcache
OPcache is already enabled in the production PHP Dockerfile with optimized settings.

### Use CDN for Static Assets
Consider using a CDN for serving static assets (CSS, JS, images) to improve performance.

### Database Optimization
```bash
# Tune PostgreSQL for production
docker compose -f docker-compose.hostinger.yml exec database \
    psql -U family_plan_user -d family_plan -c "VACUUM ANALYZE;"
```

## Monitoring

### Check Container Health
```bash
docker compose -f docker-compose.hostinger.yml ps
```

### Monitor Resource Usage
```bash
docker stats
```

### Check Application Logs
```bash
docker compose -f docker-compose.hostinger.yml logs -f php
```

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Symfony Production Best Practices](https://symfony.com/doc/current/deployment.html)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [Nginx Documentation](https://nginx.org/en/docs/)

## Support

For issues specific to the Family Plan application, please refer to the main README.md or open an issue on the GitHub repository.
