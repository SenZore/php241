#!/bin/bash

# Initialize MySQL
if [ ! -d "/var/lib/mysql/mysql" ]; then
    echo "Initializing MySQL..."
    mysql_install_db --user=mysql --datadir=/var/lib/mysql
fi

# Start MySQL temporarily
mysqld_safe --user=mysql &
sleep 10

# Configure MySQL
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_PASSWORD}';"
mysql -u root -p${DB_PASSWORD} -e "CREATE DATABASE IF NOT EXISTS ytdlp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p${DB_PASSWORD} -e "CREATE USER IF NOT EXISTS 'ytdlp_user'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
mysql -u root -p${DB_PASSWORD} -e "GRANT ALL PRIVILEGES ON ytdlp_db.* TO 'ytdlp_user'@'localhost';"
mysql -u root -p${DB_PASSWORD} -e "FLUSH PRIVILEGES;"

# Stop MySQL
mysqladmin -u root -p${DB_PASSWORD} shutdown

# Update configuration files
sed -i "s/ytdlp_password/${DB_PASSWORD}/g" /var/www/html/includes/config.php
sed -i "s/your-domain.com/${DOMAIN_NAME}/g" /var/www/html/includes/config.php
sed -i "s/admin@your-domain.com/${SSL_EMAIL}/g" /var/www/html/includes/config.php

# Create required directories
mkdir -p /run/php
mkdir -p /var/log/ytdlp

# Set permissions
chown -R www-data:www-data /var/www/html
chown -R www-data:www-data /var/log/ytdlp

echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
