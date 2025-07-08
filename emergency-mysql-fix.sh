#!/bin/bash

# Emergency MySQL Fix Script
# Fixes the "Start request repeated too quic# Step 5: Check for corrupted data and reinitialize if needed
log "Checking MySQL data directory integrity..."
if [ -d "$MYSQL_DATA_DIR/mysql" ]; then
    # Check if MySQL data is corrupted
    if [ -f "$MYSQL_DATA_DIR/auto.cnf" ] && [ ! -f "$MYSQL_DATA_DIR/mysql/user.MYD" ]; then
        warning "MySQL data appears corrupted, backing up and reinitializing..."
        mv "$MYSQL_DATA_DIR" "${MYSQL_DATA_DIR}.backup.$(date +%Y%m%d_%H%M%S)"
        mkdir -p "$MYSQL_DATA_DIR"
        chown mysql:mysql "$MYSQL_DATA_DIR"
        chmod 750 "$MYSQL_DATA_DIR"
    fi
fi

if [ ! -f "$MYSQL_DATA_DIR/mysql/user.frm" ] && [ ! -f "$MYSQL_DATA_DIR/mysql/user.MYD" ]; then
    log "MySQL data directory appears empty, initializing..."
    mysqld --initialize-insecure --user=mysql --datadir="$MYSQL_DATA_DIR"
fi

# Step 5.5: Remove any lock files
log "Removing any MySQL lock files..."
rm -f "$MYSQL_DATA_DIR"/*.pid 2>/dev/null || true
rm -f /var/run/mysqld/mysqld.pid 2>/dev/null || true
rm -f /tmp/mysql.sock 2>/dev/null || trueerror

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

# Step 0: Comprehensive diagnosis
log "Running comprehensive MySQL diagnosis..."

echo ""
info "=== CURRENT SYSTEM STATE ==="
info "1. MySQL service status:"
systemctl status mysql.service --no-pager || true

echo ""
info "2. MySQL processes:"
ps aux | grep mysql | grep -v grep || echo "No MySQL processes found"

echo ""
info "3. MySQL listening ports:"
netstat -tlnp | grep :3306 || echo "MySQL not listening on port 3306"

echo ""
info "4. MySQL data directory:"
ls -la /var/lib/mysql/ 2>/dev/null | head -10 || echo "Cannot access MySQL data directory"

echo ""
info "5. Recent MySQL errors:"
tail -10 /var/log/mysql/error.log 2>/dev/null || echo "No MySQL error log found"

echo ""
info "6. Disk space:"
df -h / | tail -1

echo ""
info "7. Memory:"
free -h | grep Mem

echo ""
log "=== STARTING MYSQL REPAIR ==="

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

# Step 7: Try multiple methods to start MySQL
log "Attempting to start MySQL service..."

# Method 1: Standard systemctl start
log "Method 1: Standard systemctl start"
if timeout 30 systemctl start mysql.service; then
    log "✓ MySQL started with systemctl"
else
    warning "systemctl start failed, trying alternative methods..."
    
    # Method 2: Start mysqld directly
    log "Method 2: Starting mysqld directly"
    sudo -u mysql mysqld --daemonize --user=mysql --datadir="$MYSQL_DATA_DIR" --pid-file=/var/run/mysqld/mysqld.pid || true
    sleep 5
    
    if pgrep mysqld >/dev/null; then
        log "✓ MySQL started directly with mysqld"
    else
        # Method 3: Safe mode start
        log "Method 3: MySQL safe mode start"
        mysqld_safe --user=mysql --datadir="$MYSQL_DATA_DIR" &
        sleep 10
        
        if pgrep mysqld >/dev/null; then
            log "✓ MySQL started in safe mode"
        else
            error "All MySQL start methods failed"
            
            # Show detailed diagnostics
            echo ""
            info "=== DETAILED DIAGNOSTICS ==="
            
            echo ""
            info "1. MySQL service status:"
            systemctl status mysql.service --no-pager || true
            
            echo ""
            info "2. MySQL error log (last 30 lines):"
            tail -30 /var/log/mysql/error.log 2>/dev/null || echo "No error log found"
            
            echo ""
            info "3. System journal for MySQL (last 20 lines):"
            journalctl -u mysql.service --no-pager -n 20 || true
            
            echo ""
            info "4. MySQL processes:"
            ps aux | grep mysql | grep -v grep || echo "No MySQL processes"
            
            echo ""
            info "5. MySQL ports:"
            netstat -tlnp | grep :3306 || echo "MySQL not listening on port 3306"
            
            echo ""
            info "6. MySQL data directory contents:"
            ls -la "$MYSQL_DATA_DIR" 2>/dev/null || echo "Cannot access MySQL data directory"
            
            echo ""
            info "7. Disk space:"
            df -h "$MYSQL_DATA_DIR"
            
            echo ""
            info "8. Memory usage:"
            free -h
            
            echo ""
            error "=== MANUAL FIX SUGGESTIONS ==="
            error "1. Check if another MySQL instance is running: sudo pkill -f mysql"
            error "2. Check disk space: df -h"
            error "3. Reinstall MySQL: sudo apt remove --purge mysql-server && sudo apt install mysql-server"
            error "4. Check system logs: journalctl -xe"
            
            exit 1
        fi
    fi
fi

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
