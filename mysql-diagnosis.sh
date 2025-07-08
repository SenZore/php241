#!/bin/bash

# Quick MySQL Diagnostic and Fix
# Let's see exactly what's wrong and fix it step by step

echo "======================================"
echo "MySQL Diagnostic and Fix"
echo "======================================"

# Check if MySQL service exists and its current state
echo "1. Checking MySQL service state..."
systemctl status mysql.service --no-pager -l || echo "MySQL service status check failed"

echo ""
echo "2. Checking MySQL configuration files..."
find /etc/mysql -name "*.cnf" -type f 2>/dev/null || echo "No MySQL config files found"

echo ""
echo "3. Checking for MySQL data directory issues..."
ls -la /var/lib/mysql/ 2>/dev/null || echo "MySQL data directory not accessible"

echo ""
echo "4. Checking MySQL error logs..."
find /var/log -name "*mysql*" -type f 2>/dev/null || echo "No MySQL log files found"
if [ -f "/var/log/mysql/error.log" ]; then
    echo "Recent MySQL errors:"
    tail -10 /var/log/mysql/error.log
fi

echo ""
echo "5. Checking journal for MySQL errors..."
journalctl -u mysql.service --no-pager -n 10 2>/dev/null || echo "No journal entries found"

echo ""
echo "6. Checking if MySQL is installed properly..."
dpkg -l | grep mysql || echo "MySQL packages not found"

echo ""
echo "7. Checking MySQL processes..."
ps aux | grep mysql | grep -v grep || echo "No MySQL processes running"

echo ""
echo "8. Checking MySQL configuration for datadir issues..."
if [ -f "/etc/mysql/mysql.conf.d/mysqld.cnf" ]; then
    echo "MySQL config file contents:"
    cat /etc/mysql/mysql.conf.d/mysqld.cnf | grep -E "(datadir|socket|log)" || echo "No datadir/socket/log settings found"
else
    echo "Main MySQL config file missing!"
fi

echo ""
echo "======================================"
echo "DIAGNOSIS COMPLETE"
echo "======================================"
