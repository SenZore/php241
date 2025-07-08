#!/bin/bash

# MySQL Troubleshooting and Fix Script
# Run this if you encounter MySQL connection issues during installation

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
echo "MySQL Troubleshooting Script"
echo "======================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    error "This script must be run as root. Use: sudo $0"
    exit 1
fi

# Check if MySQL is installed
log "Checking MySQL installation..."
if ! dpkg -l | grep -q mysql-server; then
    error "MySQL server is not installed"
    info "Installing MySQL server..."
    apt update
    apt install -y mysql-server
fi

# Check MySQL service status
log "Checking MySQL service status..."
if systemctl is-active --quiet mysql; then
    log "✓ MySQL service is running"
else
    warning "MySQL service is not running, attempting to start..."
    
    # Enable and start MySQL
    systemctl enable mysql
    systemctl start mysql
    
    # Wait for service to start
    sleep 5
    
    if systemctl is-active --quiet mysql; then
        log "✓ MySQL service started successfully"
    else
        error "Failed to start MySQL service"
        echo ""
        info "MySQL service status:"
        systemctl status mysql --no-pager
        echo ""
        info "MySQL error logs:"
        tail -20 /var/log/mysql/error.log 2>/dev/null || echo "No error log found"
        exit 1
    fi
fi

# Check MySQL socket
log "Checking MySQL socket..."
SOCKET_PATH="/var/run/mysqld/mysqld.sock"
if [ -S "$SOCKET_PATH" ]; then
    log "✓ MySQL socket exists: $SOCKET_PATH"
else
    warning "MySQL socket not found at $SOCKET_PATH"
    
    # Check alternative locations
    for socket in /tmp/mysql.sock /var/lib/mysql/mysql.sock /run/mysqld/mysqld.sock; do
        if [ -S "$socket" ]; then
            info "Found MySQL socket at: $socket"
            break
        fi
    done
fi

# Test MySQL connection
log "Testing MySQL connection..."
if mysql -e "SELECT 1;" >/dev/null 2>&1; then
    log "✓ MySQL connection successful (no password)"
elif mysql -u root -e "SELECT 1;" >/dev/null 2>&1; then
    log "✓ MySQL connection successful (root user)"
else
    warning "Cannot connect to MySQL without password"
    info "This is normal for fresh installations"
fi

# Show MySQL process
log "MySQL processes:"
ps aux | grep mysql | grep -v grep || echo "No MySQL processes found"

# Show MySQL port
log "MySQL network status:"
netstat -tlnp | grep :3306 || echo "MySQL not listening on port 3306"

echo ""
log "MySQL troubleshooting complete!"
echo ""
info "If MySQL is now running, you can continue with the installation."
info "If issues persist, check the logs above for more details."
