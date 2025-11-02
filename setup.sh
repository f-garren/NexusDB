#!/bin/bash

# NexusDB Food Distribution System - Setup Script for Ubuntu 24.04
# This script sets up the web server, database, and configures the application
# 
# This script is standalone and will clone the repository from GitHub.
# It can be run from any directory and does not require local files.
#
# Usage: sudo ./setup.sh

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Print colored output
print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    print_error "Please run as root (use sudo)"
    exit 1
fi

print_info "Starting NexusDB setup for Ubuntu 24.04..."

# Repository URL
REPO_URL="https://github.com/f-garren/NexusDB.git"
INSTALL_DIR="/opt/nexusdb"

# Step 1: Update system
print_info "Updating system packages..."
apt-get update -qq
apt-get upgrade -y -qq

# Step 2: Install git if not present
print_info "Installing git..."
apt-get install -y git

# Step 3: Clone repository
print_info "Cloning NexusDB repository from GitHub..."
if [ -d "$INSTALL_DIR" ]; then
    print_warning "Install directory $INSTALL_DIR already exists. Removing old installation..."
    rm -rf "$INSTALL_DIR"
fi

git clone "$REPO_URL" "$INSTALL_DIR" || {
    print_error "Failed to clone repository. Please check your internet connection and repository URL."
    exit 1
}

print_info "Repository cloned successfully to $INSTALL_DIR"

# Step 4: Install necessary packages
print_info "Installing required packages (Apache, PHP, MySQL)..."
apt-get install -y apache2 mysql-server php php-mysql php-pdo php-xml php-mbstring php-curl unzip expect

# Step 5: Enable Apache modules
print_info "Enabling Apache modules..."
a2enmod rewrite
a2enmod php

# Step 6: Generate random MySQL root password
print_info "Generating secure MySQL root password..."
MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
DB_NAME="nexusdb"
DB_USER="nexusdb_user"
DB_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)

# Save passwords to file (with restricted permissions)
PASSWORD_FILE="/root/nexusdb_passwords.txt"
echo "=== NexusDB Database Passwords ===" > "$PASSWORD_FILE"
echo "Generated: $(date)" >> "$PASSWORD_FILE"
echo "" >> "$PASSWORD_FILE"
echo "MySQL Root Password: $MYSQL_ROOT_PASSWORD" >> "$PASSWORD_FILE"
echo "Database Name: $DB_NAME" >> "$PASSWORD_FILE"
echo "Database User: $DB_USER" >> "$PASSWORD_FILE"
echo "Database User Password: $DB_PASSWORD" >> "$PASSWORD_FILE"
echo "" >> "$PASSWORD_FILE"
echo "WARNING: Keep this file secure! Store it in a safe location." >> "$PASSWORD_FILE"
chmod 600 "$PASSWORD_FILE"

print_info "Passwords saved to $PASSWORD_FILE (chmod 600)"

# Step 7: Secure MySQL installation and set root password
print_info "Configuring MySQL..."
systemctl start mysql
systemctl enable mysql

# Set MySQL root password using mysql_secure_installation automation
print_info "Setting MySQL root password..."
SECURE_MYSQL=$(expect -c "
set timeout 10
spawn mysql_secure_installation
expect \"Press y|Y for Yes, any other key for No:\"
send \"n\r\"
expect \"New password:\"
send \"$MYSQL_ROOT_PASSWORD\r\"
expect \"Re-enter new password:\"
send \"$MYSQL_ROOT_PASSWORD\r\"
expect \"Remove anonymous users? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"
expect \"Disallow root login remotely? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"
expect \"Remove test database and access to it? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"
expect \"Reload privilege tables now? (Press y|Y for Yes, any other key for No) :\"
send \"y\r\"
expect eof
")

echo "$SECURE_MYSQL" > /dev/null

# Step 8: Create database and user
print_info "Creating database and user..."
mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

# Step 9: Import database schema
print_info "Importing database schema..."
SCHEMA_FILE="$INSTALL_DIR/database_schema.sql"

if [ -f "$SCHEMA_FILE" ]; then
    mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$DB_NAME" < "$SCHEMA_FILE"
    print_info "Database schema imported successfully"
else
    print_error "database_schema.sql not found in $INSTALL_DIR"
    exit 1
fi

# Step 10: Copy files to web root
WEB_ROOT="/var/www/html"
APP_DIR="$WEB_ROOT/nexusdb"

print_info "Copying application files to web root..."
if [ -d "$APP_DIR" ]; then
    print_warning "Web directory $APP_DIR already exists. Removing old files..."
    rm -rf "$APP_DIR"
fi

mkdir -p "$APP_DIR"
# Copy all files except .git directory and setup.sh
rsync -a --exclude='.git' --exclude='setup.sh' "$INSTALL_DIR/" "$APP_DIR/" || {
    # Fallback to cp if rsync not available
    cp -r "$INSTALL_DIR"/* "$APP_DIR/" 2>/dev/null
    rm -rf "$APP_DIR/.git" "$APP_DIR/setup.sh" 2>/dev/null
}

# Step 11: Update config.php with database credentials
print_info "Updating config.php with database credentials..."
CONFIG_FILE="$APP_DIR/config.php"

if [ -f "$CONFIG_FILE" ]; then
    # Backup original config
    cp "$CONFIG_FILE" "$CONFIG_FILE.backup"
    
    # Update database credentials
    sed -i "s/define('DB_NAME', '[^']*');/define('DB_NAME', '$DB_NAME');/" "$CONFIG_FILE"
    sed -i "s/define('DB_USER', '[^']*');/define('DB_USER', '$DB_USER');/" "$CONFIG_FILE"
    sed -i "s/define('DB_PASS', '[^']*');/define('DB_PASS', '$DB_PASSWORD');/" "$CONFIG_FILE"
    
    print_info "Config file updated successfully"
else
    print_error "config.php not found in $APP_DIR"
    exit 1
fi

# Step 12: Set proper permissions
print_info "Setting file permissions..."
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod 600 "$CONFIG_FILE"

# Step 13: Restart services
print_info "Restarting Apache..."
systemctl restart apache2
systemctl restart mysql

# Step 14: Display information
print_info "Setup completed successfully!"
echo ""
echo "=========================================="
echo "NexusDB Setup Summary"
echo "=========================================="
echo "Database Name: $DB_NAME"
echo "Database User: $DB_USER"
echo "Web Directory: $APP_DIR"
echo ""
echo "IMPORTANT: Passwords saved to: $PASSWORD_FILE"
echo ""
echo "Access your application at:"
echo "  http://localhost/nexusdb/"
echo "  or"
echo "  http://$(hostname -I | awk '{print $1}')/nexusdb/"
echo ""
print_warning "Make sure to secure your server and consider setting up HTTPS!"
echo "=========================================="

