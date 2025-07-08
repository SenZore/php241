#!/bin/bash

# Simple YouTube Downloader Update Script
# This is a lightweight version for quick updates

set -e

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

REPO_URL="https://github.com/SenZore/php241.git"
INSTALL_DIR="/var/www/html"
BACKUP_DIR="/var/backups/ytdlp"
TEMP_DIR="/tmp/ytdlp-quick-update"

echo -e "${GREEN}YouTube Downloader - Quick Update${NC}"
echo "================================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}This script must be run as root. Use: sudo $0${NC}"
    exit 1
fi

# Check if installation exists
if [ ! -f "$INSTALL_DIR/index.php" ]; then
    echo -e "${RED}No existing installation found. Please run the full installer first.${NC}"
    exit 1
fi

# Create backup
echo -e "${YELLOW}Creating backup...${NC}"
BACKUP_NAME="ytdlp-quickbackup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -r "$INSTALL_DIR" "$BACKUP_DIR/$BACKUP_NAME"
echo "Backup created: $BACKUP_DIR/$BACKUP_NAME"

# Download latest version
echo -e "${YELLOW}Downloading latest version...${NC}"
rm -rf "$TEMP_DIR"
git clone "$REPO_URL" "$TEMP_DIR"
cd "$TEMP_DIR"
rm -rf .git .gitignore

# Preserve config
echo -e "${YELLOW}Preserving configuration...${NC}"
cp "$INSTALL_DIR/includes/config.php" "$TEMP_DIR/includes/config.php"

# Stop services
echo -e "${YELLOW}Stopping services...${NC}"
systemctl stop nginx
systemctl stop php8.1-fpm

# Update files
echo -e "${YELLOW}Updating files...${NC}"
cp -r "$TEMP_DIR"/* "$INSTALL_DIR/"

# Set permissions
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"

# Update yt-dlp
echo -e "${YELLOW}Updating yt-dlp...${NC}"
pip3 install -U yt-dlp

# Run database migrations
echo -e "${YELLOW}Running database migrations...${NC}"
php -f "$INSTALL_DIR/database/migrate.php" 2>/dev/null || echo "No migrations to run"

# Restart services
echo -e "${YELLOW}Restarting services...${NC}"
systemctl start php8.1-fpm
systemctl start nginx

# Cleanup
rm -rf "$TEMP_DIR"

echo -e "${GREEN}Update completed successfully!${NC}"
echo "Backup available at: $BACKUP_DIR/$BACKUP_NAME"
echo "Check your site to ensure everything is working correctly."
