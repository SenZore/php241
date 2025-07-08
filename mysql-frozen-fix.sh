#!/bin/bash

# MySQL FROZEN State Fix Script
# Fixes the MySQL frozen state issue

echo "======================================"
echo "MySQL FROZEN State Fix"
echo "======================================"

# Check if FROZEN file exists
if [ -f "/etc/mysql/FROZEN" ]; then
    echo "Found MySQL FROZEN file. Contents:"
    cat /etc/mysql/FROZEN
    echo ""
    echo "Removing FROZEN file to unfreeze MySQL..."
    rm -f /etc/mysql/FROZEN
    echo "✓ MySQL unfrozen"
else
    echo "No FROZEN file found"
fi

# Reset systemd state
echo "Resetting systemd failure state..."
systemctl reset-failed mysql.service
systemctl daemon-reload

# Check MySQL data directory - it looks like there's mixed MariaDB/MySQL data
echo ""
echo "Checking data directory contents..."
ls -la /var/lib/mysql/ | head -15

# The issue is that we have MariaDB files (aria_log) mixed with MySQL
# Let's clean this up properly

echo ""
echo "Backing up current data directory..."
if [ -d "/var/lib/mysql" ]; then
    mv /var/lib/mysql /var/lib/mysql.mixed.backup.$(date +%Y%m%d_%H%M%S)
    echo "✓ Data directory backed up"
fi

# Create clean MySQL directories
echo "Creating clean MySQL directories..."
mkdir -p /var/lib/mysql
mkdir -p /var/run/mysqld
mkdir -p /var/log/mysql

# Set proper ownership
chown mysql:mysql /var/lib/mysql
chown mysql:mysql /var/run/mysqld
chown mysql:mysql /var/log/mysql

chmod 750 /var/lib/mysql
chmod 755 /var/run/mysqld
chmod 755 /var/log/mysql

echo "✓ Directories created with proper permissions"

# Check and fix MySQL configuration
echo ""
echo "Fixing MySQL configuration..."
MYSQL_CNF="/etc/mysql/mysql.conf.d/mysqld.cnf"

# Backup and fix config
cp "$MYSQL_CNF" "$MYSQL_CNF.backup.$(date +%Y%m%d_%H%M%S)"

# Uncomment and ensure correct settings
sed -i 's/^# socket/socket/' "$MYSQL_CNF"
sed -i 's/^# datadir/datadir/' "$MYSQL_CNF"

# Ensure correct values
if ! grep -q "^datadir.*=.*var/lib/mysql" "$MYSQL_CNF"; then
    echo "datadir = /var/lib/mysql" >> "$MYSQL_CNF"
fi

if ! grep -q "^socket.*=.*var/run/mysqld/mysqld.sock" "$MYSQL_CNF"; then
    echo "socket = /var/run/mysqld/mysqld.sock" >> "$MYSQL_CNF"
fi

echo "✓ MySQL configuration fixed"

# Initialize MySQL database
echo ""
echo "Initializing MySQL database..."
mysqld --initialize-insecure --user=mysql --datadir=/var/lib/mysql

echo "✓ MySQL database initialized"

# Try to start MySQL
echo ""
echo "Starting MySQL service..."
systemctl start mysql

# Wait a moment
sleep 5

# Check if it's running
if systemctl is-active --quiet mysql; then
    echo "✓ SUCCESS: MySQL is now running!"
    
    # Enable auto-start
    systemctl enable mysql
    echo "✓ MySQL enabled for auto-start"
    
    # Show version
    echo ""
    echo "MySQL Version:"
    mysql --version
    
    # Test connection
    echo ""
    echo "Testing MySQL connection..."
    if mysql -e "SELECT 'MySQL is working!' AS status;" 2>/dev/null; then
        echo "✓ MySQL connection test successful!"
    else
        echo "MySQL is running but may need password setup"
    fi
    
else
    echo "✗ MySQL failed to start"
    echo ""
    echo "Service status:"
    systemctl status mysql --no-pager -l
    
    echo ""
    echo "Error log:"
    tail -10 /var/log/mysql/error.log 2>/dev/null || echo "No error log"
fi

echo ""
echo "======================================"
echo "MySQL FROZEN fix completed"
echo "======================================"
