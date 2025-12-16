#!/bin/bash
# CORS Connection Test Script
# This script verifies that CORS is properly configured between frontend and API

set -e

echo "========================================"
echo "CORS Configuration Test"
echo "========================================"
echo ""

# Check environment variables
echo "1. Checking environment configuration..."
if [ -f .env ]; then
    CORS_ORIGIN=$(grep CORS_ALLOWED_ORIGIN .env | cut -d '=' -f2)
    echo "   ✓ CORS_ALLOWED_ORIGIN: $CORS_ORIGIN"
else
    echo "   ✗ .env file not found"
    exit 1
fi

# Check Nelmio CORS Bundle configuration
echo ""
echo "2. Checking Nelmio CORS Bundle configuration..."
if [ -f config/packages/nelmio_cors.yaml ]; then
    echo "   ✓ nelmio_cors.yaml exists"
else
    echo "   ✗ nelmio_cors.yaml not found"
    exit 1
fi

# Check bundle registration
echo ""
echo "3. Checking bundle registration..."
if grep -q "NelmioCorsBundle" config/bundles.php; then
    echo "   ✓ NelmioCorsBundle is registered"
else
    echo "   ✗ NelmioCorsBundle is not registered in bundles.php"
    exit 1
fi

# Check frontend API client
echo ""
echo "4. Checking frontend API client configuration..."
if [ -f frontend/src/services/apiClient.js ]; then
    if grep -q "credentials: 'include'" frontend/src/services/apiClient.js; then
        echo "   ✓ API client includes credentials"
    else
        echo "   ✗ API client missing 'credentials: include'"
        exit 1
    fi
else
    echo "   ✗ apiClient.js not found"
    exit 1
fi

# Check composer.json
echo ""
echo "5. Checking composer.json..."
if grep -q "nelmio/cors-bundle" composer.json; then
    echo "   ✓ nelmio/cors-bundle is in composer.json"
else
    echo "   ✗ nelmio/cors-bundle not found in composer.json"
    exit 1
fi

echo ""
echo "========================================"
echo "✓ All CORS configuration checks passed!"
echo "========================================"
echo ""
echo "Next steps:"
echo "1. Run: docker compose up -d"
echo "2. Run: composer install (inside PHP container)"
echo "3. Access frontend at http://localhost:3000"
echo "4. Access API at http://localhost:8080/api"
echo "5. Check browser console for CORS errors"
echo ""
