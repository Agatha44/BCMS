#!/bin/bash

# Server Deployment Script for ZKTeco Package Installation
# This script installs Composer dependencies for DEVELOPMENT server

set -e  # Exit on any error

echo "=========================================="
echo "BMS Development Server Deployment Script"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Error: Composer is not installed${NC}"
    echo "Please install Composer first:"
    echo "  curl -sS https://getcomposer.org/installer | php"
    echo "  sudo mv composer.phar /usr/local/bin/composer"
    exit 1
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo -e "${RED}Error: PHP is not installed${NC}"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo -e "${YELLOW}PHP Version: $PHP_VERSION${NC}"

if (( $(echo "$PHP_VERSION < 8.0" | bc -l) )); then
    echo -e "${RED}Error: PHP 8.0 or higher is required${NC}"
    exit 1
fi

# Check for required PHP extensions
MISSING_EXTENSIONS=()
IGNORE_PLATFORM_REQS=""

if ! php -m | grep -q sockets; then
    MISSING_EXTENSIONS+=("sockets")
    echo -e "${YELLOW}Warning: PHP sockets extension not found${NC}"
    echo "The ZKTeco package requires the sockets extension."
fi

if ! php -m | grep -q http; then
    MISSING_EXTENSIONS+=("http")
    echo -e "${YELLOW}Warning: PHP http extension not found${NC}"
fi

if ! php -m | grep -q curl; then
    MISSING_EXTENSIONS+=("curl")
    echo -e "${YELLOW}Warning: PHP curl extension not found${NC}"
fi

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    echo ""
    echo "Missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
    echo "Installation instructions:"
    echo "  Ubuntu/Debian: sudo apt-get install php-sockets php-http php-curl"
    echo "  CentOS/RHEL: sudo yum install php-sockets php-http php-curl"
    echo ""
    echo -e "${YELLOW}For development, you can ignore platform requirements${NC}"
    read -p "Ignore platform requirements and continue? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        IGNORE_PLATFORM_REQS="--ignore-platform-reqs"
        echo -e "${YELLOW}Continuing with --ignore-platform-reqs flag${NC}"
    else
        echo -e "${RED}Please install the required extensions first${NC}"
        exit 1
    fi
fi

echo ""
echo -e "${GREEN}Step 1: Installing/Updating Composer dependencies (including dev)...${NC}"

# Build composer command with platform requirements flag if needed
COMPOSER_CMD="composer"
if [ -n "$IGNORE_PLATFORM_REQS" ]; then
    COMPOSER_FLAGS="$IGNORE_PLATFORM_REQS --optimize-autoloader --no-interaction"
else
    COMPOSER_FLAGS="--optimize-autoloader --no-interaction"
fi

# Check if composer.lock exists and if ZKTeco is in composer.json
if grep -q "jmrashed/zkteco" composer.json; then
    echo -e "${YELLOW}ZKTeco package found in composer.json${NC}"
    if [ -f "composer.lock" ]; then
        echo -e "${YELLOW}composer.lock found, running composer update to ensure ZKTeco is installed...${NC}"
        $COMPOSER_CMD update $COMPOSER_FLAGS
    else
        echo -e "${YELLOW}composer.lock not found, running composer install...${NC}"
        $COMPOSER_CMD install $COMPOSER_FLAGS || \
        $COMPOSER_CMD update $COMPOSER_FLAGS
    fi
else
    echo -e "${YELLOW}ZKTeco package not in composer.json, adding it...${NC}"
    $COMPOSER_CMD require jmrashed/zkteco --update-with-all-dependencies $COMPOSER_FLAGS
fi

echo ""
echo -e "${GREEN}Step 2: Verifying ZKTeco package installation...${NC}"
if [ -f "vendor/jmrashed/zkteco/src/Lib/ZKTeco.php" ]; then
    echo -e "${GREEN}✓ ZKTeco package found${NC}"
    mkdir -p vendor/jmrashed/zkteco/src/Lib/logs 2>/dev/null || true
    mkdir -p vendor/jmrashed/zkteco/src/Lib/Helper/logs 2>/dev/null || true
    chmod -R 775 vendor/jmrashed/zkteco/src/Lib/logs vendor/jmrashed/zkteco/src/Lib/Helper/logs 2>/dev/null || true
else
    echo -e "${YELLOW}ZKTeco package not found, checking composer.json...${NC}"
    if grep -q "jmrashed/zkteco" composer.json; then
        echo -e "${YELLOW}Package is in composer.json, running composer update...${NC}"
        $COMPOSER_CMD update jmrashed/zkteco $COMPOSER_FLAGS
    else
        echo -e "${RED}✗ ZKTeco package not found in composer.json!${NC}"
        echo -e "${YELLOW}Adding package to composer.json...${NC}"
        # Use update instead of require to avoid conflicts
        $COMPOSER_CMD require jmrashed/zkteco --update-with-all-dependencies $COMPOSER_FLAGS || \
        $COMPOSER_CMD update jmrashed/zkteco --with-all-dependencies $COMPOSER_FLAGS
    fi
    
    # Verify again after installation attempt
    if [ -f "vendor/jmrashed/zkteco/src/Lib/ZKTeco.php" ]; then
        echo -e "${GREEN}✓ ZKTeco package installed successfully${NC}"
    else
        echo -e "${RED}✗ Failed to install ZKTeco package${NC}"
        echo -e "${YELLOW}Running full composer update to resolve dependencies...${NC}"
        $COMPOSER_CMD update --with-all-dependencies $COMPOSER_FLAGS
    fi
fi

echo ""
echo -e "${GREEN}Step 3: Clearing Laravel caches...${NC}"
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan optimize:clear || true

echo ""
echo -e "${GREEN}Step 4: Regenerating autoloader (development mode)...${NC}"
if [ -n "$IGNORE_PLATFORM_REQS" ]; then
    composer dump-autoload --optimize $IGNORE_PLATFORM_REQS || true
else
    composer dump-autoload --optimize || true
fi

echo ""
echo -e "${YELLOW}Note: Development mode - caches are cleared for easier debugging${NC}"
echo -e "${YELLOW}Config, routes, and views are NOT cached in development${NC}"

echo ""
echo -e "${GREEN}Step 5: Verifying installation...${NC}"

# Check if ZKTeco class can be autoloaded
if php -r "require 'vendor/autoload.php'; echo class_exists('Jmrashed\Zkteco\Lib\ZKTeco') ? 'OK' : 'FAIL';" | grep -q "OK"; then
    echo -e "${GREEN}✓ ZKTeco class can be autoloaded${NC}"
    else
        echo -e "${RED}✗ ZKTeco class cannot be autoloaded${NC}"
        echo "Regenerating autoloader..."
        if [ -n "$IGNORE_PLATFORM_REQS" ]; then
            composer dump-autoload --optimize $IGNORE_PLATFORM_REQS
        else
            composer dump-autoload --optimize
        fi
    fi

echo ""
echo -e "${GREEN}=========================================="
echo -e "Deployment completed successfully!${NC}"
echo -e "${GREEN}=========================================="
echo ""
echo "Next steps:"
echo "1. Verify your .env file has correct configuration"
echo "2. Set APP_ENV=local or APP_ENV=development in .env"
echo "3. Set APP_DEBUG=true for development debugging"
echo "4. Test the attendance sync functionality"
echo "5. Check application logs for any errors"
echo ""
echo -e "${YELLOW}Development Tips:${NC}"
echo "- Changes to config files will be picked up immediately (no cache)"
echo "- Route changes will be reflected without clearing cache"
echo "- Use 'php artisan serve' to run the development server"
echo ""

