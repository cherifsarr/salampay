#!/bin/bash
#===============================================================================
# SalamPay Database Setup Script
# Creates the salampay database and user on MySQL
# Run this ONCE during initial server setup
#===============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  SalamPay Database Setup              ${NC}"
echo -e "${GREEN}========================================${NC}"

# Configuration
DB_NAME="salampay"
DB_USER="salampay_user"

# Prompt for MySQL root password
echo -e "${YELLOW}Enter MySQL root password:${NC}"
read -s MYSQL_ROOT_PASSWORD

# Prompt for new database user password
echo -e "${YELLOW}Enter password for new salampay_user:${NC}"
read -s DB_PASSWORD
echo -e "${YELLOW}Confirm password:${NC}"
read -s DB_PASSWORD_CONFIRM

if [ "$DB_PASSWORD" != "$DB_PASSWORD_CONFIRM" ]; then
    echo -e "${RED}Passwords do not match. Exiting.${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Creating database and user...${NC}"

# Create database and user
mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<EOF
-- Create database
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Create user (if not exists)
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';

-- Grant privileges
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;

-- Verify
SELECT 'Database created successfully' AS status;
SHOW DATABASES LIKE '${DB_NAME}';
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  Database setup complete!             ${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""
    echo -e "Database: ${GREEN}${DB_NAME}${NC}"
    echo -e "User:     ${GREEN}${DB_USER}${NC}"
    echo -e "Host:     ${GREEN}127.0.0.1${NC}"
    echo -e "Port:     ${GREEN}3306${NC}"
    echo ""
    echo -e "${YELLOW}Add these to your .env file:${NC}"
    echo ""
    echo "DB_CONNECTION=mysql"
    echo "DB_HOST=127.0.0.1"
    echo "DB_PORT=3306"
    echo "DB_DATABASE=${DB_NAME}"
    echo "DB_USERNAME=${DB_USER}"
    echo "DB_PASSWORD=<your_password>"
    echo ""
else
    echo -e "${RED}Database setup failed!${NC}"
    exit 1
fi
