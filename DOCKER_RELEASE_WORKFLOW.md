# Docker Build and Release Workflow

## Overview

This repository includes a GitHub Actions workflow that automatically builds and pushes production-ready Docker images to a Docker registry whenever a new release is published.

## Workflow Details

**File**: `.github/workflows/docker-build-release.yml`

**Trigger**: Automatically runs when a new GitHub release is published

**Images Built**:
1. **Backend (PHP/Symfony)**: Built from `docker/php/Dockerfile.prod`
2. **Frontend (React)**: Built from `frontend/Dockerfile.prod`

## Required Repository Configuration

Before the workflow can run successfully, you need to configure the following repository variables and secrets:

### Repository Variables

Navigate to your repository's **Settings → Secrets and variables → Actions → Variables** and add:

1. **`DOCKER_REGISTRY_HOST`**
   - Description: The hostname of your Docker registry
   - Example: `docker.io` (for Docker Hub)
   - Example: `ghcr.io` (for GitHub Container Registry)
   - Example: `registry.example.com` (for private registry)

2. **`DOCKER_REGISTRY_USER`**
   - Description: Your Docker registry username
   - Example: `myusername` (for Docker Hub)
   - Example: `myorg` (for GitHub Container Registry)

### Repository Secrets

Navigate to your repository's **Settings → Secrets and variables → Actions → Secrets** and add:

1. **`DOCKER_REGISTRY_PASS`**
   - Description: Your Docker registry password or access token
   - For Docker Hub: Use your Docker Hub password or access token
   - For GitHub Container Registry: Use a GitHub Personal Access Token with `write:packages` scope

## How It Works

1. When you publish a new release in GitHub:
   - Go to your repository → Releases → Draft a new release
   - Create a new tag (e.g., `v1.0.0`, `v1.2.3`)
   - Publish the release

2. The workflow automatically:
   - Checks out the code
   - Logs into the Docker registry using configured credentials
   - Builds the backend Docker image with production optimizations
   - Builds the frontend Docker image with production optimizations
   - Tags images with both `latest` and the version number
   - Pushes both images to the registry

## Image Tags

Each image is tagged with two tags:

### Backend Image
- `{REGISTRY_HOST}/{REGISTRY_USER}/family-plan-backend:latest`
- `{REGISTRY_HOST}/{REGISTRY_USER}/family-plan-backend:{version}`

### Frontend Image
- `{REGISTRY_HOST}/{REGISTRY_USER}/family-plan-frontend:latest`
- `{REGISTRY_HOST}/{REGISTRY_USER}/family-plan-frontend:{version}`

**Example** (for Docker Hub user `johndoe` and release `v1.2.3`):
- Backend: `docker.io/johndoe/family-plan-backend:latest` and `docker.io/johndoe/family-plan-backend:1.2.3`
- Frontend: `docker.io/johndoe/family-plan-frontend:latest` and `docker.io/johndoe/family-plan-frontend:1.2.3`

## Production Docker Images

### Backend Image (`docker/php/Dockerfile.prod`)

The backend production image includes:
- PHP 8.4-FPM with production optimizations
- All required PHP extensions (intl, pdo_pgsql, zip)
- OpCache configured for maximum performance
- Composer dependencies (production only)
- Pre-warmed Symfony cache
- Built frontend assets (from webpack)
- Database migration support via entrypoint script
- Super admin user creation on startup

**Environment Variables** (set at runtime):
- `APP_ENV`: Production environment (set to `prod`)
- `DATABASE_URL`: PostgreSQL connection string
- `APP_SECRET`: Symfony application secret
- `POSTGRES_*`: Database credentials
- `SUPER_ADMIN_*`: Admin user configuration

### Frontend Image (`frontend/Dockerfile.prod`)

The frontend production image includes:
- Nginx 1.27-alpine for serving static files
- Built React application (optimized and minified)
- Gzip compression enabled
- Security headers configured
- SPA routing support
- Health check endpoint at `/health`

## Deployment

After the workflow successfully builds and pushes the images, you can deploy them using:

### Docker Compose

Create a `docker-compose.prod.yml`:

```yaml
services:
  database:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: family_plan
      POSTGRES_USER: family_plan_user
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data

  backend:
    image: ${DOCKER_REGISTRY_HOST}/${DOCKER_REGISTRY_USER}/family-plan-backend:latest
    environment:
      APP_ENV: prod
      APP_SECRET: ${APP_SECRET}
      DATABASE_URL: postgresql://family_plan_user:${POSTGRES_PASSWORD}@database:5432/family_plan
      SUPER_ADMIN_EMAIL: ${SUPER_ADMIN_EMAIL}
      SUPER_ADMIN_PASSWORD: ${SUPER_ADMIN_PASSWORD}
    depends_on:
      - database

  nginx:
    image: nginx:1.27-alpine
    ports:
      - "8080:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - backend

  frontend:
    image: ${DOCKER_REGISTRY_HOST}/${DOCKER_REGISTRY_USER}/family-plan-frontend:latest
    ports:
      - "3000:80"

volumes:
  postgres_data:
```

Then deploy with:
```bash
docker-compose -f docker-compose.prod.yml up -d
```

### Kubernetes

Pull and deploy the images to your Kubernetes cluster using the tags.

## Troubleshooting

### Workflow Fails to Login

**Error**: `Error: Cannot perform an interactive login from a non TTY device`

**Solution**: Verify that `DOCKER_REGISTRY_PASS` secret is set correctly in repository settings.

### Build Fails

1. Check the GitHub Actions logs in the repository's Actions tab
2. Verify that all required files exist:
   - `docker/php/Dockerfile.prod`
   - `docker/php/docker-entrypoint.sh`
   - `frontend/Dockerfile.prod`
   - `frontend/nginx.conf`

### Images Not Appearing in Registry

1. Verify registry credentials are correct
2. Check that the registry user has permission to push images
3. For private registries, ensure the registry URL is correct

## Security Notes

- Never commit registry passwords to the repository
- Use secrets for sensitive data (`DOCKER_REGISTRY_PASS`)
- Use variables for non-sensitive configuration (`DOCKER_REGISTRY_HOST`, `DOCKER_REGISTRY_USER`)
- Regularly rotate access tokens and passwords
- Use least-privilege access tokens when possible

## Monitoring

You can view the workflow execution in your repository under:
- **Actions** tab → **Build and Push Production Docker Images**

Each workflow run provides:
- Build logs for both images
- Summary of built images and tags
- Any errors encountered during the build process
