#!/bin/bash
#===============================================================================
# SalamPay Deployment Script
# Target: EC2 Server (same as salamticket.net)
# Domain: salampay.com
# Web Server: Apache2 (same as salamticket.net)
#===============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="salampay"
DEPLOY_USER="ubuntu"
DEPLOY_PATH="/var/www/salampay"
REPO_URL="https://github.com/cherifsarr/salampay.git"
BRANCH="main"
PHP_VERSION="8.3"

# Web server detection (Apache2 or Nginx)
# salamticket.net uses Apache2, so we default to Apache2
if systemctl is-active --quiet apache2; then
    WEB_SERVER="apache2"
elif systemctl is-active --quiet nginx; then
    WEB_SERVER="nginx"
else
    WEB_SERVER="apache2"  # Default to Apache2
fi

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  SalamPay Deployment Script${NC}"
echo -e "${GREEN}========================================${NC}"

# Function to print step
step() {
    echo -e "\n${YELLOW}➜ $1${NC}"
}

# Function to print success
success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Function to print error
error() {
    echo -e "${RED}✗ $1${NC}"
    exit 1
}

#-------------------------------------------------------------------------------
# Step 1: Create directory structure
#-------------------------------------------------------------------------------
step "Creating directory structure..."

sudo mkdir -p ${DEPLOY_PATH}
sudo mkdir -p ${DEPLOY_PATH}/releases
sudo mkdir -p ${DEPLOY_PATH}/shared/storage/app/public
sudo mkdir -p ${DEPLOY_PATH}/shared/storage/framework/{cache,sessions,views}
sudo mkdir -p ${DEPLOY_PATH}/shared/storage/logs

sudo chown -R ${DEPLOY_USER}:www-data ${DEPLOY_PATH}
sudo chmod -R 775 ${DEPLOY_PATH}/shared/storage

success "Directory structure created"

#-------------------------------------------------------------------------------
# Step 2: Clone/Update repository
#-------------------------------------------------------------------------------
step "Cloning repository..."

RELEASE_DIR="${DEPLOY_PATH}/releases/$(date +%Y%m%d%H%M%S)"
git clone --depth 1 --branch ${BRANCH} ${REPO_URL} ${RELEASE_DIR}

success "Repository cloned to ${RELEASE_DIR}"

#-------------------------------------------------------------------------------
# Step 3: Navigate to backend directory
#-------------------------------------------------------------------------------
cd ${RELEASE_DIR}/backend

#-------------------------------------------------------------------------------
# Step 4: Install Composer dependencies
#-------------------------------------------------------------------------------
step "Installing Composer dependencies..."

composer install --no-dev --optimize-autoloader --no-interaction

success "Composer dependencies installed"

#-------------------------------------------------------------------------------
# Step 5: Link shared directories
#-------------------------------------------------------------------------------
step "Linking shared directories..."

# Remove existing storage and link to shared
rm -rf ${RELEASE_DIR}/backend/storage
ln -s ${DEPLOY_PATH}/shared/storage ${RELEASE_DIR}/backend/storage

# Link .env file
ln -s ${DEPLOY_PATH}/shared/.env ${RELEASE_DIR}/backend/.env

success "Shared directories linked"

#-------------------------------------------------------------------------------
# Step 6: Run Laravel optimizations
#-------------------------------------------------------------------------------
step "Running Laravel optimizations..."

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

success "Laravel optimizations complete"

#-------------------------------------------------------------------------------
# Step 7: Run database migrations
#-------------------------------------------------------------------------------
step "Running database migrations..."

php artisan migrate --force

success "Database migrations complete"

#-------------------------------------------------------------------------------
# Step 8: Run seeders (first deployment only - comment out after)
#-------------------------------------------------------------------------------
# Uncomment these lines for first deployment:
# step "Running database seeders..."
# php artisan db:seed --class=AccountingSeeder --force
# success "Database seeders complete"

#-------------------------------------------------------------------------------
# Step 9: Update symlink to current release
#-------------------------------------------------------------------------------
step "Updating current symlink..."

ln -sfn ${RELEASE_DIR} ${DEPLOY_PATH}/current

success "Symlink updated"

#-------------------------------------------------------------------------------
# Step 10: Set permissions
#-------------------------------------------------------------------------------
step "Setting permissions..."

sudo chown -R ${DEPLOY_USER}:www-data ${RELEASE_DIR}
sudo chmod -R 755 ${RELEASE_DIR}
sudo chmod -R 775 ${DEPLOY_PATH}/shared/storage

success "Permissions set"

#-------------------------------------------------------------------------------
# Step 11: Restart services
#-------------------------------------------------------------------------------
step "Restarting services..."

sudo systemctl reload php${PHP_VERSION}-fpm

if [ "$WEB_SERVER" = "apache2" ]; then
    sudo systemctl reload apache2
    success "Apache2 reloaded"
else
    sudo systemctl reload nginx
    success "Nginx reloaded"
fi

success "Services restarted"

#-------------------------------------------------------------------------------
# Step 12: Cleanup old releases (keep last 5)
#-------------------------------------------------------------------------------
step "Cleaning up old releases..."

cd ${DEPLOY_PATH}/releases
ls -1t | tail -n +6 | xargs -r rm -rf

success "Old releases cleaned up"

#-------------------------------------------------------------------------------
# Done!
#-------------------------------------------------------------------------------
echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}  Deployment Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "  App URL: https://salampay.com"
echo -e "  Release: ${RELEASE_DIR}"
echo -e "${GREEN}========================================${NC}"
