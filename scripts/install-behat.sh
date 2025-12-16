#!/bin/bash

# Behat Installation Helper Script
# This script helps install Behat with proper configuration for PHP 8.5 and Symfony 8.0

set -e

echo "================================"
echo "Behat Installation Helper"
echo "================================"
echo ""

# Check PHP version
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "✓ Detected PHP version: $PHP_VERSION"

# Check Symfony version
if [ -f "composer.json" ]; then
    SYMFONY_VERSION=$(grep -o '"symfony/framework-bundle": "[^"]*"' composer.json | grep -o '[0-9.]*' | head -1)
    echo "✓ Detected Symfony version constraint: $SYMFONY_VERSION"
fi

echo ""
echo "Checking Behat compatibility..."
echo ""

# Function to check if Behat 4.x is stable
check_behat_4() {
    echo "Checking for Behat 4.x stable release..."
    BEHAT_4_STATUS=$(composer show behat/behat --all 2>/dev/null | grep "^versions" | grep -o "v4\.[0-9]*\.[0-9]*" | head -1 || echo "")
    
    if [ ! -z "$BEHAT_4_STATUS" ]; then
        echo "✓ Behat 4.x stable found: $BEHAT_4_STATUS"
        return 0
    else
        echo "✗ Behat 4.x stable not yet available"
        return 1
    fi
}

# Try to install Behat
install_behat() {
    echo ""
    echo "Attempting to install Behat..."
    echo ""
    
    if check_behat_4; then
        echo "Installing Behat 4.x (stable)..."
        composer require --dev behat/behat:"^4.0" --no-interaction
    else
        echo "Behat 4.x stable not available. Options:"
        echo ""
        echo "1. Install Behat 4.x-dev (development version, supports Symfony 8.0)"
        echo "2. Wait for Behat 4.x stable release"
        echo "3. Use PHPUnit for E2E tests (already configured)"
        echo ""
        read -p "Choose option (1/2/3): " choice
        
        case $choice in
            1)
                echo "Installing Behat 4.x-dev..."
                composer require --dev "behat/behat:4.x-dev" --no-interaction
                ;;
            2)
                echo "Skipping installation. Please check back later for Behat 4.x stable release."
                exit 0
                ;;
            3)
                echo "Using PHPUnit for E2E tests."
                echo "Run: vendor/bin/phpunit tests/Api/"
                exit 0
                ;;
            *)
                echo "Invalid option. Exiting."
                exit 1
                ;;
        esac
    fi
}

# Main installation flow
install_behat

# Verify installation
if [ -f "vendor/bin/behat" ]; then
    echo ""
    echo "✓ Behat installed successfully!"
    echo ""
    echo "Run tests with:"
    echo "  vendor/bin/behat"
    echo ""
    echo "Or with Docker:"
    echo "  docker compose exec php vendor/bin/behat"
    echo ""
else
    echo ""
    echo "✗ Behat installation failed or skipped."
    echo "See features/README.md for more information."
    echo ""
fi
