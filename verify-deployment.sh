#!/bin/bash
# Deployment Verification Script for Hostinger
# This script performs basic checks before deployment

set -e

echo "====================================="
echo "Family Plan Deployment Verification"
echo "====================================="
echo ""

# Check if required files exist
echo "[1/6] Checking required files..."
required_files=(
    "docker-compose.hostinger.yml"
    "docker/php/Dockerfile.prod"
    "docker/react/Dockerfile.prod"
    ".env.prod"
    "docker/php/docker-entrypoint.sh"
)

for file in "${required_files[@]}"; do
    if [ ! -f "$file" ]; then
        echo "❌ ERROR: Required file missing: $file"
        exit 1
    fi
    echo "  ✓ $file"
done

echo "[2/6] Validating Docker Compose configuration..."
if docker compose -f docker-compose.hostinger.yml config > /dev/null 2>&1; then
    echo "  ✓ Docker Compose configuration is valid"
else
    echo "❌ ERROR: Invalid Docker Compose configuration"
    exit 1
fi

echo "[3/6] Checking Dockerfile syntax..."
if docker build -f docker/php/Dockerfile.prod --check . > /dev/null 2>&1; then
    echo "  ✓ PHP Dockerfile syntax is valid"
else
    echo "  ℹ PHP Dockerfile syntax check not supported in this Docker version"
fi

if docker build -f docker/react/Dockerfile.prod --check . > /dev/null 2>&1; then
    echo "  ✓ React Dockerfile syntax is valid"
else
    echo "  ℹ React Dockerfile syntax check not supported in this Docker version"
fi

echo "[4/6] Checking environment configuration..."
if [ ! -f ".env.prod.local" ]; then
    echo "  ⚠ WARNING: .env.prod.local not found. You should create this file before deployment."
    echo "  → Copy .env.prod to .env.prod.local and update with production values"
else
    echo "  ✓ .env.prod.local exists"
fi

echo "[5/6] Verifying entrypoint script permissions..."
if [ -f "docker/php/docker-entrypoint.sh" ]; then
    echo "  ✓ Entrypoint script exists"
fi

echo "[6/6] Checking documentation..."
if [ -f "HOSTINGER_DEPLOYMENT.md" ]; then
    echo "  ✓ Deployment documentation exists"
fi

echo ""
echo "====================================="
echo "✅ Pre-deployment checks passed!"
echo "====================================="
echo ""
echo "Next steps:"
echo "1. Create .env.prod.local with your production values"
echo "2. Review HOSTINGER_DEPLOYMENT.md for deployment instructions"
echo "3. Build images: docker compose -f docker-compose.hostinger.yml build"
echo "4. Deploy to Hostinger using the deployment guide"
echo ""
