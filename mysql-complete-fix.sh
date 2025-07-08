#!/bin/bash

# MySQL Emergency Fix Script - Complete Working Version
# Fixes MySQL startup issues on Ubuntu

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
echo "MySQL Complete Emergency Fix"
echo "======================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    error "This script must be run as root. Use: sudo $0"
    exit 1
fi

log "Starting comprehensive MySQL fix..."

# Install net-tools if missing (for netstat)
if ! command -v netstat >/dev/null 2>&1; then
    log "Installing net-tools..."
    apt update && apt install -y net-tools
fi

# Step 1: Stop all MySQL processes
log "Stopping all MySQL processes..."
pkill -f mysql || true
systemctl stop mysql || true
sleep 3

# Step 2: Reset systemd
log "Resetting systemd failure state..."
systemctl reset-failed mysql.service
systemctl daemon-reload

# Step 3: Check MySQL configuration
log "Checking MySQL configuration..."
MYSQL_CNF="/etc/mysql/mysql.conf.d/mysqld.cnf"

# The critical issue: MySQL is using wrong datadir
if [ -f "$MYSQL_CNF" ]; then
    log "Found MySQL config file: $MYSQL_CNF"
    
    # Check for wrong datadir setting
    if grep -q "datadir.*=.*usr" "$MYSQL_CNF"; then
        warning "Found incorrect datadir setting, fixing..."
        cp "$MYSQL_CNF" "$MYSQL_CNF.backup.$(date +%Y%m%d_%H%M%S)"
        sed -i 's|datadir.*=.*usr.*|datadir = /var/lib/mysql|g' "$MYSQL_CNF"
        log "Fixed datadir in MySQL configuration"
    fi
    
    # Ensure correct datadir is set
    if ! grep -q "datadir.*=.*var/lib/mysql" "$MYSQL_CNF"; then
        echo "" >> "$MYSQL_CNF"
        echo "datadir = /var/lib/mysql" >> "$MYSQL_CNF"
        log "Added correct datadir to MySQL configuration"
    fi
else
    warning "MySQL config file not found, creating basic configuration..."
    mkdir -p /etc/mysql/mysql.conf.d/
    cat > "$MYSQL_CNF" << 'EOF'
[mysqld]
datadir = /var/lib/mysql
socket = /var/run/mysqld/mysqld.sock
bind-address = 127.0.0.1
log-error = /var/log/mysql/error.log
pid-file = /var/run/mysqld/mysqld.pid
EOF
    log "Created basic MySQL configuration"
fi

# Step 4: Fix data directory
log "Setting up MySQL data directory..."
MYSQL_DATA_DIR="/var/lib/mysql"

# Remove any corrupted data and start fresh
if [ -d "$MYSQL_DATA_DIR" ] && [ "$(ls -A $MYSQL_DATA_DIR)" ]; then
    log "Backing up existing MySQL data..."
    mv "$MYSQL_DATA_DIR" "${MYSQL_DATA_DIR}.backup.$(date +%Y%m%d_%H%M%S)"
fi

# Create fresh data directory
mkdir -p "$MYSQL_DATA_DIR"
chown mysql:mysql "$MYSQL_DATA_DIR"
chmod 750 "$MYSQL_DATA_DIR"

# Create run directory
mkdir -p /var/run/mysqld
chown mysql:mysql /var/run/mysqld
chmod 755 /var/run/mysqld

# Create log directory
mkdir -p /var/log/mysql
chown mysql:mysql /var/log/mysql
chmod 755 /var/log/mysql

# Step 5: Initialize MySQL with correct datadir
log "Initializing MySQL database..."
mysqld --initialize-insecure --user=mysql --datadir="$MYSQL_DATA_DIR"

# Step 6: Start MySQL
log "Starting MySQL service..."
systemctl start mysql

# Wait for MySQL to start
sleep 5

# Step 7: Verify MySQL is running
if systemctl is-active --quiet mysql; then
    log "✓ MySQL service is running!"
    
    # Test connection
    if mysql -e "SELECT 1;" >/dev/null 2>&1; then
        log "✓ MySQL connection successful!"
    else
        info "MySQL is running but may need password setup"
    fi
    
    # Enable auto-start
    systemctl enable mysql
    log "✓ MySQL enabled for auto-start"
    
    # Show status
    echo ""
    info "MySQL Status:"
    systemctl status mysql --no-pager -l
    
    echo ""
    info "MySQL Version:"
    mysql --version
    
    echo ""
    log "SUCCESS: MySQL is now running correctly!"
    
else
    error "MySQL failed to start"
    echo ""
    info "Service status:"
    systemctl status mysql --no-pager -l
    
    echo ""
    info "Error log:"
    tail -20 /var/log/mysql/error.log 2>/dev/null || echo "No error log found"
    
    echo ""
    info "Journal entries:"
    journalctl -u mysql.service --no-pager -n 10
    
    exit 1
fi

echo ""
log "MySQL emergency fix completed successfully!"
echo ""
info "Next steps:"
info "1. Set MySQL root password: mysqladmin -u root password 'your_password'"
info "2. Continue with your application installation"
info "3. Test MySQL: mysql -u root -p"
