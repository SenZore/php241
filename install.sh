#!/bin/bash

# YouTube Downloader Auto Installer for Ubuntu 22.04-24.04
# This script will install and configure everything needed for the YT-DLP website

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DOMAIN_NAME=""
SSL_EMAIL=""
DB_PASSWORD=""
ADMIN_USERNAME=""
ADMIN_PASSWORD=""
INSTALL_DIR="/var/www/html"
LOG_FILE="/var/log/ytdlp-installer.log"

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
    echo "[ERROR] $1" >> "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
    echo "[WARNING] $1" >> "$LOG_FILE"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
    echo "[INFO] $1" >> "$LOG_FILE"
}

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        error "This script must be run as root. Use: sudo $0"
        exit 1
    fi
}

# Check Ubuntu version
check_ubuntu_version() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        if [[ "$ID" != "ubuntu" ]]; then
            error "This script is designed for Ubuntu only"
            exit 1
        fi
        
        VERSION_ID_NUM=$(echo $VERSION_ID | cut -d. -f1)
        if [ "$VERSION_ID_NUM" -lt 22 ] || [ "$VERSION_ID_NUM" -gt 24 ]; then
            error "This script supports Ubuntu 22.04-24.04 only. Found: $VERSION_ID"
            exit 1
        fi
        
        log "Ubuntu $VERSION_ID detected - Compatible!"
    else
        error "Cannot determine Ubuntu version"
        exit 1
    fi
}

# Get user input
get_user_input() {
    echo -e "${BLUE}=== YouTube Downloader Installation Configuration ===${NC}"
    echo
    
    while [ -z "$DOMAIN_NAME" ]; do
        read -p "Enter your domain name (e.g., ytdl.example.com): " DOMAIN_NAME
        if [ -z "$DOMAIN_NAME" ]; then
            error "Domain name is required!"
        fi
    done
    
    while [ -z "$SSL_EMAIL" ]; do
        read -p "Enter email for SSL certificate (e.g., admin@example.com): " SSL_EMAIL
        if [ -z "$SSL_EMAIL" ]; then
            error "Email is required for SSL certificate!"
        fi
    done
    
    while [ -z "$DB_PASSWORD" ]; do
        read -s -p "Enter MySQL password for ytdlp_user: " DB_PASSWORD
        echo
        if [ -z "$DB_PASSWORD" ]; then
            error "Database password is required!"
        fi
    done
    
    echo
    echo -e "${BLUE}=== Admin Panel Configuration ===${NC}"
    
    while [ -z "$ADMIN_USERNAME" ]; do
        read -p "Enter admin username: " ADMIN_USERNAME
        if [ -z "$ADMIN_USERNAME" ]; then
            error "Admin username is required!"
        fi
    done
    
    while [ -z "$ADMIN_PASSWORD" ]; do
        read -s -p "Enter admin password: " ADMIN_PASSWORD
        echo
        if [ -z "$ADMIN_PASSWORD" ]; then
            error "Admin password is required!"
        fi
    done
    
    echo
    log "Configuration collected successfully!"
}

# Validate DNS configuration
validate_dns() {
    log "Validating DNS configuration for $DOMAIN_NAME..."
    
    # Get public IP
    PUBLIC_IP=$(curl -s ifconfig.me || curl -s ipinfo.io/ip || curl -s icanhazip.com)
    if [ -z "$PUBLIC_IP" ]; then
        error "Could not determine public IP address"
        exit 1
    fi
    
    log "Public IP: $PUBLIC_IP"
    
    # Check A record
    DNS_IP=$(dig +short $DOMAIN_NAME @8.8.8.8)
    if [ -z "$DNS_IP" ]; then
        error "No A record found for $DOMAIN_NAME"
        error "Please create an A record pointing $DOMAIN_NAME to $PUBLIC_IP"
        exit 1
    fi
    
    if [ "$DNS_IP" != "$PUBLIC_IP" ]; then
        error "DNS A record ($DNS_IP) does not match public IP ($PUBLIC_IP)"
        error "Please update your A record to point to $PUBLIC_IP"
        exit 1
    fi
    
    log "DNS configuration is correct!"
}

# Update system
update_system() {
    log "Updating system packages..."
    apt update && apt upgrade -y
    log "System updated successfully!"
}

# Install dependencies
install_dependencies() {
    log "Installing required packages..."
    
    apt install -y \
        nginx \
        mysql-server \
        php8.1 \
        php8.1-fpm \
        php8.1-mysql \
        php8.1-curl \
        php8.1-gd \
        php8.1-mbstring \
        php8.1-xml \
        php8.1-zip \
        python3 \
        python3-pip \
        ffmpeg \
        curl \
        wget \
        unzip \
        certbot \
        python3-certbot-nginx \
        dnsutils \
        htop \
        git
    
    log "Dependencies installed successfully!"
}

# Install yt-dlp
install_ytdlp() {
    log "Installing yt-dlp..."
    
    # Install via pip for latest version
    pip3 install -U yt-dlp
    
    # Create symlink for easier access
    ln -sf /usr/local/bin/yt-dlp /usr/bin/yt-dlp
    
    # Verify installation
    if yt-dlp --version > /dev/null 2>&1; then
        log "yt-dlp installed successfully: $(yt-dlp --version)"
    else
        error "yt-dlp installation failed"
        exit 1
    fi
}

# Configure MySQL
configure_mysql() {
    log "Configuring MySQL..."
    
    # Secure MySQL installation
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '$DB_PASSWORD';"
    mysql -u root -p$DB_PASSWORD -e "DELETE FROM mysql.user WHERE User='';"
    mysql -u root -p$DB_PASSWORD -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
    mysql -u root -p$DB_PASSWORD -e "DROP DATABASE IF EXISTS test;"
    mysql -u root -p$DB_PASSWORD -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
    
    # Create database and user
    mysql -u root -p$DB_PASSWORD -e "CREATE DATABASE IF NOT EXISTS ytdlp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -u root -p$DB_PASSWORD -e "CREATE USER IF NOT EXISTS 'ytdlp_user'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
    mysql -u root -p$DB_PASSWORD -e "GRANT ALL PRIVILEGES ON ytdlp_db.* TO 'ytdlp_user'@'localhost';"
    mysql -u root -p$DB_PASSWORD -e "FLUSH PRIVILEGES;"
    
    log "MySQL configured successfully!"
}

# Configure PHP
configure_php() {
    log "Configuring PHP..."
    
    # Update PHP configuration
    PHP_INI="/etc/php/8.1/fpm/php.ini"
    
    sed -i 's/upload_max_filesize = .*/upload_max_filesize = 100M/' $PHP_INI
    sed -i 's/post_max_size = .*/post_max_size = 100M/' $PHP_INI
    sed -i 's/max_execution_time = .*/max_execution_time = 300/' $PHP_INI
    sed -i 's/memory_limit = .*/memory_limit = 256M/' $PHP_INI
    
    systemctl restart php8.1-fpm
    log "PHP configured successfully!"
}

# Setup application
setup_application() {
    log "Setting up application files..."
    
    # Create directories
    mkdir -p $INSTALL_DIR
    mkdir -p /var/www/html/downloads
    mkdir -p /var/log/ytdlp
    
    # Set permissions
    chown -R www-data:www-data $INSTALL_DIR
    chown -R www-data:www-data /var/www/html/downloads
    chown -R www-data:www-data /var/log/ytdlp
    
    chmod -R 755 $INSTALL_DIR
    chmod -R 755 /var/www/html/downloads
    chmod -R 755 /var/log/ytdlp
    
    # Update config with actual values
    sed -i "s/ytdlp_password/$DB_PASSWORD/g" $INSTALL_DIR/includes/config.php
    sed -i "s/your-domain.com/$DOMAIN_NAME/g" $INSTALL_DIR/includes/config.php
    sed -i "s/admin@your-domain.com/$SSL_EMAIL/g" $INSTALL_DIR/includes/config.php
    
    # Create admin user
    HASHED_PASSWORD=$(php -r "echo password_hash('$ADMIN_PASSWORD', PASSWORD_DEFAULT);")
    mysql -u root -p$DB_PASSWORD ytdlp_db -e "INSERT INTO admin_users (username, password, role, created_at) VALUES ('$ADMIN_USERNAME', '$HASHED_PASSWORD', 'admin', NOW()) ON DUPLICATE KEY UPDATE password='$HASHED_PASSWORD';"
    
    log "Application setup completed!"
}

# Configure Nginx
configure_nginx() {
    log "Configuring Nginx..."
    
    # Remove default site
    rm -f /etc/nginx/sites-enabled/default
    
    # Create site configuration
    cat > /etc/nginx/sites-available/$DOMAIN_NAME << EOF
server {
    listen 80;
    server_name $DOMAIN_NAME;
    
    root $INSTALL_DIR;
    index index.php index.html;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    
    # PHP configuration
    location ~ \\.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Static files
    location ~* \\.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Downloads
    location /downloads/ {
        internal;
        alias /var/www/html/downloads/;
    }
    
    # Deny access to sensitive files
    location ~ /\\. {
        deny all;
    }
    
    location ~ /(includes|vendor)/ {
        deny all;
    }
    
    # Rate limiting
    limit_req_zone \$binary_remote_addr zone=download:10m rate=5r/m;
    location /api.php {
        limit_req zone=download burst=10 nodelay;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
}
EOF
    
    # Enable site
    ln -sf /etc/nginx/sites-available/$DOMAIN_NAME /etc/nginx/sites-enabled/
    
    # Test configuration
    nginx -t
    systemctl restart nginx
    
    log "Nginx configured successfully!"
}

# Configure SSL with Certbot
configure_ssl() {
    log "Configuring SSL certificate with Let's Encrypt..."
    
    # Obtain SSL certificate
    certbot --nginx -d $DOMAIN_NAME --email $SSL_EMAIL --agree-tos --non-interactive --redirect
    
    # Setup auto-renewal
    systemctl enable certbot.timer
    systemctl start certbot.timer
    
    log "SSL certificate configured successfully!"
}

# Create systemd services
create_services() {
    log "Creating systemd services..."
    
    # Create cleanup service
    cat > /etc/systemd/system/ytdlp-cleanup.service << EOF
[Unit]
Description=YT-DLP Cleanup Service
After=network.target

[Service]
Type=oneshot
User=www-data
ExecStart=/usr/bin/php $INSTALL_DIR/cleanup.php
EOF
    
    # Create cleanup timer
    cat > /etc/systemd/system/ytdlp-cleanup.timer << EOF
[Unit]
Description=Run YT-DLP cleanup daily
Requires=ytdlp-cleanup.service

[Timer]
OnCalendar=daily
Persistent=true

[Install]
WantedBy=timers.target
EOF
    
    # Enable and start services
    systemctl daemon-reload
    systemctl enable ytdlp-cleanup.timer
    systemctl start ytdlp-cleanup.timer
    
    log "Services created and started!"
}

# Create cleanup script
create_cleanup_script() {
    cat > $INSTALL_DIR/cleanup.php << 'EOF'
<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Clean up old downloads (older than 7 days)
cleanupOldDownloads('7 days');

// Clean up old system stats (older than 24 hours)
$stmt = $pdo->prepare("DELETE FROM system_stats WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt->execute();

// Clean up old rate limit entries
$stmt = $pdo->prepare("DELETE FROM rate_limits WHERE last_download < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt->execute();

logMessage("Cleanup completed successfully");
echo "Cleanup completed\n";
EOF
    
    chown www-data:www-data $INSTALL_DIR/cleanup.php
}

# Setup firewall
setup_firewall() {
    log "Configuring firewall..."
    
    # Install and configure UFW
    apt install -y ufw
    
    # Allow SSH, HTTP, and HTTPS
    ufw allow ssh
    ufw allow 80/tcp
    ufw allow 443/tcp
    
    # Enable firewall
    ufw --force enable
    
    log "Firewall configured successfully!"
}

# Final configuration and testing
final_setup() {
    log "Performing final setup..."
    
    # Restart all services
    systemctl restart nginx
    systemctl restart php8.1-fpm
    systemctl restart mysql
    
    # Test the website
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://$DOMAIN_NAME)
    if [ "$HTTP_STATUS" -eq 200 ] || [ "$HTTP_STATUS" -eq 301 ] || [ "$HTTP_STATUS" -eq 302 ]; then
        log "Website is accessible!"
    else
        warning "Website might not be accessible (HTTP $HTTP_STATUS)"
    fi
    
    # Test HTTPS
    HTTPS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN_NAME)
    if [ "$HTTPS_STATUS" -eq 200 ]; then
        log "HTTPS is working!"
    else
        warning "HTTPS might not be working (HTTP $HTTPS_STATUS)"
    fi
    
    log "Final setup completed!"
}

# Display installation summary
display_summary() {
    echo
    echo -e "${GREEN}=== Installation Summary ===${NC}"
    echo -e "${BLUE}Domain:${NC} https://$DOMAIN_NAME"
    echo -e "${BLUE}SSL:${NC} Let's Encrypt certificate installed"
    echo -e "${BLUE}Database:${NC} MySQL with ytdlp_db database"
    echo -e "${BLUE}Features:${NC}"
    echo "  ✓ YouTube video downloading with yt-dlp"
    echo "  ✓ Rate limiting (5 downloads per 30 minutes)"
    echo "  ✓ Real-time server monitoring"
    echo "  ✓ SSL encryption"
    echo "  ✓ Automatic cleanup of old files"
    echo "  ✓ Security headers and firewall"
    echo
    echo -e "${BLUE}Important Files:${NC}"
    echo "  • Website: $INSTALL_DIR"
    echo "  • Downloads: /var/www/html/downloads"
    echo "  • Logs: /var/log/ytdlp/"
    echo "  • Nginx config: /etc/nginx/sites-available/$DOMAIN_NAME"
    echo
    echo -e "${BLUE}Useful Commands:${NC}"
    echo "  • Check logs: tail -f /var/log/ytdlp/app.log"
    echo "  • Restart services: systemctl restart nginx php8.1-fpm"
    echo "  • Check SSL: certbot certificates"
    echo "  • Update yt-dlp: pip3 install -U yt-dlp"
    echo
    echo -e "${GREEN}Installation completed successfully!${NC}"
    echo -e "${GREEN}Your YouTube downloader is ready at: https://$DOMAIN_NAME${NC}"
}

# Main installation function
main() {
    log "Starting YouTube Downloader installation..."
    
    check_root
    check_ubuntu_version
    get_user_input
    validate_dns
    update_system
    install_dependencies
    install_ytdlp
    configure_mysql
    configure_php
    setup_application
    configure_nginx
    configure_ssl
    create_cleanup_script
    create_services
    setup_firewall
    final_setup
    display_summary
    
    log "Installation completed successfully!"
}

# Trap errors
trap 'error "Installation failed at line $LINENO. Check $LOG_FILE for details."; exit 1' ERR

# Run main installation
main "$@"
