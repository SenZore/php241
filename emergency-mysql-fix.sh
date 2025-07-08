#!/bin/bash

# Emergency MySQL Fix Script
# Fixes the "Start request repeated too quickly" error

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

echo "======================================"
echo "MySQL Emergency Fix Script"
echo "======================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    error "This script must be run as root. Use: sudo $0"
    exit 1
fi

log "Fixing MySQL 'Start request repeated too quickly' error..."

# Step 1: Reset systemd failure state
log "Resetting systemd failure state..."
systemctl reset-failed mysql.service
systemctl daemon-reload

# Step 2: Stop any existing MySQL processes
log "Stopping any existing MySQL processes..."
pkill -f mysql || true
sleep 2

# Step 3: Check and fix MySQL data directory ownership
log "Checking MySQL data directory..."
MYSQL_DATA_DIR="/var/lib/mysql"
if [ -d "$MYSQL_DATA_DIR" ]; then
    log "Fixing MySQL data directory ownership..."
    chown -R mysql:mysql "$MYSQL_DATA_DIR"
    chmod 750 "$MYSQL_DATA_DIR"
else
    warning "MySQL data directory not found, creating..."
    mkdir -p "$MYSQL_DATA_DIR"
    chown mysql:mysql "$MYSQL_DATA_DIR"
    chmod 750 "$MYSQL_DATA_DIR"
fi

# Step 4: Check MySQL configuration
log "Checking MySQL configuration..."
MYSQL_CONF="/etc/mysql/mysql.conf.d/mysqld.cnf"
if [ -f "$MYSQL_CONF" ]; then
    log "MySQL configuration file exists"
else
    warning "MySQL configuration file missing"
fi

# Step 5: Check for disk space
log "Checking disk space..."
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt 90 ]; then
    error "Disk usage is ${DISK_USAGE}% - MySQL may fail due to low disk space"
    exit 1
else
    log "Disk usage is ${DISK_USAGE}% - OK"
fi

# Step 6: Initialize MySQL if needed
if [ ! -f "$MYSQL_DATA_DIR/mysql/user.frm" ] && [ ! -f "$MYSQL_DATA_DIR/mysql/user.MYD" ]; then
    log "MySQL data directory appears empty, initializing..."
    mysqld --initialize-insecure --user=mysql --datadir="$MYSQL_DATA_DIR"
fi

# Step 7: Try to start MySQL with timeout
log "Attempting to start MySQL service..."
timeout 30 systemctl start mysql.service || {
    error "MySQL failed to start within 30 seconds"
    
    # Show detailed error information
    echo ""
    info "MySQL service status:"
    systemctl status mysql.service --no-pager || true
    
    echo ""
    info "MySQL error log (last 20 lines):"
    tail -20 /var/log/mysql/error.log 2>/dev/null || echo "No error log found"
    
    echo ""
    info "System journal for MySQL (last 10 lines):"
    journalctl -u mysql.service --no-pager -n 10 || true
    
    exit 1
}

# Step 8: Verify MySQL is running
sleep 3
if systemctl is-active --quiet mysql; then
    log "✓ MySQL service is now running successfully!"
    
    # Test basic connection
    if mysql -e "SELECT 1;" >/dev/null 2>&1; then
        log "✓ MySQL connection test successful"
    else
        info "MySQL is running but may need initial setup"
    fi
    
    # Enable MySQL to start on boot
    systemctl enable mysql
    log "✓ MySQL enabled for automatic startup"
    
else
    error "MySQL service failed to start"
    exit 1
fi

echo ""
log "MySQL fix completed successfully!"
echo ""
info "You can now continue with your installation."
info "If you need to set a root password, run: mysqladmin -u root password 'your_password'"
