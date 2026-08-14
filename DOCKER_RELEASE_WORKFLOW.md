# Docker Build and Release Workflow

## Overview

This repository includes a GitHub Actions workflow that automatically builds and pushes production-ready Docker images to GitHub Container Registry (ghcr.io) whenever a new release is published.

## Workflow Details

**File**: `.github/workflows/docker-build-release.yml`

**Trigger**: Automatically runs when a new GitHub release is published

**Images Built**:
1. **Backend (PHP/Symfony)**: Built from `docker/php/Dockerfile.prod`
2. **Frontend (React)**: Built from `frontend/Dockerfile.prod`

## Required Repository Configuration

**No manual configuration required!** The workflow uses GitHub's built-in `GITHUB_TOKEN` with automatic authentication to GitHub Container Registry.

The workflow automatically uses:
- **Registry**: `ghcr.io` (GitHub Container Registry)
- **Authentication**: `GITHUB_TOKEN` (automatically provided by GitHub Actions)
- **Namespace**: Repository owner's username/organization

## How It Works

1. When you publish a new release in GitHub:
   - Go to your repository → Releases → Draft a new release
   - Create a new tag (e.g., `v1.0.0`, `v1.2.3`)
   - Publish the release

2. The workflow automatically:
   - Checks out the code
   - Logs into GitHub Container Registry using `GITHUB_TOKEN`
   - Builds the backend Docker image with production optimizations
   - Builds the frontend Docker image with production optimizations
   - Tags images with both `latest` and the version number
   - Pushes both images to ghcr.io

## Image Tags

Each image is tagged with two tags:

### Backend Image
- `ghcr.io/{repository_owner}/family-plan-backend:latest`
- `ghcr.io/{repository_owner}/family-plan-backend:{version}`

### Frontend Image
- `ghcr.io/{repository_owner}/family-plan-frontend:latest`
- `ghcr.io/{repository_owner}/family-plan-frontend:{version}`

**Example** (for repository owner `jakubciszak` and release `v1.2.3`):
- Backend: `ghcr.io/jakubciszak/family-plan-backend:latest` and `ghcr.io/jakubciszak/family-plan-backend:1.2.3`
- Frontend: `ghcr.io/jakubciszak/family-plan-frontend:latest` and `ghcr.io/jakubciszak/family-plan-frontend:1.2.3`

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
    image: ghcr.io/jakubciszak/family-plan-backend:latest
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
    image: ghcr.io/jakubciszak/family-plan-frontend:latest
    ports:
      - "3000:80"

volumes:
  postgres_data:
```

Then deploy with:
```bash
docker-compose -f docker-compose.prod.yml up -d
```

### Pulling Images

To pull the images manually:

```bash
# Pull backend
docker pull ghcr.io/jakubciszak/family-plan-backend:latest

# Pull frontend
docker pull ghcr.io/jakubciszak/family-plan-frontend:latest
```

**Note**: Images published to ghcr.io are public by default. To make them private, go to the package settings on GitHub.

### Kubernetes

Pull and deploy the images to your Kubernetes cluster using the tags.

## Troubleshooting

### Workflow Fails to Build

1. Check the GitHub Actions logs in the repository's Actions tab
2. Verify that all required files exist:
   - `docker/php/Dockerfile.prod`
   - `docker/php/docker-entrypoint.sh`
   - `frontend/Dockerfile.prod`
   - `frontend/nginx.conf`

### Images Not Appearing in GitHub Packages

1. Check the Actions workflow run for errors
2. Verify the workflow has `packages: write` permission
3. Check your repository's Packages section on GitHub

### Permission Issues When Pulling Images

If images are private, you need to authenticate:

```bash
echo $GITHUB_TOKEN | docker login ghcr.io -u USERNAME --password-stdin
```

## Security Notes

- `GITHUB_TOKEN` is automatically provided by GitHub Actions - no manual configuration needed
- Images are published to your repository's package registry
- You can configure package visibility (public/private) in GitHub package settings
- The workflow uses minimal permissions: `contents: read` and `packages: write`

## Monitoring

You can view the workflow execution in your repository under:
- **Actions** tab → **Build and Push Production Docker Images**

Each workflow run provides:
- Build logs for both images
- Summary of built images and tags
- Any errors encountered during the build process
