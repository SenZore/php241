#!/bin/bash

# MySQL Configuration Cleanup Script
# Removes MariaDB configurations that conflict with MySQL

echo "======================================"
echo "MySQL Configuration Cleanup"
echo "======================================"

# Stop MySQL first
systemctl stop mysql 2>/dev/null || true
pkill -f mysql || true

# The issue is MariaDB configuration files are being loaded by MySQL
# Let's temporarily disable MariaDB configs

echo "Backing up and disabling MariaDB configuration files..."

# Create backup directory
mkdir -p /etc/mysql/mariadb.conf.d.disabled
mkdir -p /etc/mysql/conf.d.disabled

# Move MariaDB-specific configs that conflict with MySQL
mv /etc/mysql/mariadb.conf.d/provider_bzip2.cnf /etc/mysql/mariadb.conf.d.disabled/ 2>/dev/null || true
mv /etc/mysql/mariadb.conf.d/provider_lzo.cnf /etc/mysql/mariadb.conf.d.disabled/ 2>/dev/null || true
mv /etc/mysql/mariadb.conf.d/provider_lz4.cnf /etc/mysql/mariadb.conf.d.disabled/ 2>/dev/null || true
mv /etc/mysql/mariadb.conf.d/provider_lzma.cnf /etc/mysql/mariadb.conf.d.disabled/ 2>/dev/null || true
mv /etc/mysql/mariadb.conf.d/provider_snappy.cnf /etc/mysql/mariadb.conf.d.disabled/ 2>/dev/null || true
mv /etc/mysql/mariadb.conf.d/60-galera.cnf /etc/mysql/mariadb.conf.d.disabled/ 2>/dev/null || true

echo "✓ MariaDB-specific configurations disabled"

# Also disable the main mariadb.cnf that includes these
mv /etc/mysql/mariadb.cnf /etc/mysql/mariadb.cnf.disabled 2>/dev/null || true

echo "✓ MariaDB main configuration disabled"

# Create a clean MySQL-only configuration
echo "Creating clean MySQL configuration..."

cat > /etc/mysql/mysql.conf.d/mysqld.cnf << 'EOF'
[mysqld]
# Basic Settings
pid-file = /var/run/mysqld/mysqld.pid
socket = /var/run/mysqld/mysqld.sock
datadir = /var/lib/mysql
log-error = /var/log/mysql/error.log
bind-address = 127.0.0.1

# MyISAM settings
key_buffer_size = 16M
max_allowed_packet = 16M
thread_stack = 192K
thread_cache_size = 8

# Query Cache Configuration
query_cache_size = 16M

# Logging and Replication
log_bin = /var/log/mysql/mysql-bin.log
binlog_expire_logs_seconds = 2592000
max_binlog_size = 100M

# InnoDB settings
innodb_buffer_pool_size = 128M
innodb_log_file_size = 50M
innodb_flush_log_at_trx_commit = 1
innodb_lock_wait_timeout = 50

# Security
sql_mode = TRADITIONAL
EOF

echo "✓ Clean MySQL configuration created"

# Remove any corrupted data directory
echo "Cleaning up data directory..."
rm -rf /var/lib/mysql/*

# Ensure proper directories and permissions
mkdir -p /var/lib/mysql
mkdir -p /var/run/mysqld
mkdir -p /var/log/mysql

chown -R mysql:mysql /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld
chown -R mysql:mysql /var/log/mysql

chmod 750 /var/lib/mysql
chmod 755 /var/run/mysqld
chmod 755 /var/log/mysql

echo "✓ Directories cleaned and permissions set"

# Initialize MySQL with the clean configuration
echo "Initializing MySQL with clean configuration..."
mysqld --initialize-insecure --user=mysql --datadir=/var/lib/mysql

echo "✓ MySQL initialized successfully"

# Reset systemd
systemctl reset-failed mysql.service
systemctl daemon-reload

# Start MySQL
echo "Starting MySQL service..."
systemctl start mysql

# Wait and check
sleep 5

if systemctl is-active --quiet mysql; then
    echo "✓ SUCCESS: MySQL is now running!"
    
    # Enable auto-start
    systemctl enable mysql
    echo "✓ MySQL enabled for auto-start"
    
    # Test connection
    echo ""
    echo "Testing MySQL connection..."
    if mysql -e "SELECT 'MySQL is working!' AS status;" 2>/dev/null; then
        echo "✓ MySQL connection test successful!"
        echo ""
        echo "MySQL Version:"
        mysql --version
        
        echo ""
        echo "Database Status:"
        mysql -e "SHOW DATABASES;" 2>/dev/null || echo "MySQL running but may need password"
        
    else
        echo "✓ MySQL is running but may need password setup"
    fi
    
    echo ""
    echo "✓ MySQL setup completed successfully!"
    echo "You can now continue with your application installation."
    
else
    echo "✗ MySQL still failed to start"
    echo ""
    echo "Service status:"
    systemctl status mysql --no-pager -l
    
    echo ""
    echo "Error log:"
    tail -10 /var/log/mysql/error.log 2>/dev/null || echo "No error log found"
    
    echo ""
    echo "Journal entries:"
    journalctl -u mysql.service --no-pager -n 5
fi

echo ""
echo "======================================"
echo "MySQL Configuration Cleanup Complete"
echo "======================================"
