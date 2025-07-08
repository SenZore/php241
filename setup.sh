#!/bin/bash

# YouTube Downloader Auto Installer/Updater for Ubuntu 22.04-24.04
# This script can install from scratch or update existing installations
# Repository: https://github.com/SenZore/php241

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
REPO_URL="https://github.com/SenZore/php241.git"
REPO_BRANCH="main"
DOMAIN_NAME=""
SSL_EMAIL=""
DB_PASSWORD=""
ADMIN_USERNAME=""
ADMIN_PASSWORD=""
INSTALL_DIR="/var/www/html"
BACKUP_DIR="/var/backups/ytdlp"
LOG_FILE="/var/log/ytdlp-setup.log"
TEMP_DIR="/tmp/ytdlp-update"

# Script mode: install or update
MODE=""

# Logging functions
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

success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}"
    echo "[SUCCESS] $1" >> "$LOG_FILE"
}

# Print banner
print_banner() {
    echo -e "${PURPLE}"
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                                                              ║"
    echo "║          YouTube Downloader - Setup & Update Tool           ║"
    echo "║                                                              ║"
    echo "║              Repository: SenZore/php241                      ║"
    echo "║              Support: Ubuntu 22.04-24.04                    ║"
    echo "║                                                              ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
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

# Detect installation mode
detect_mode() {
    if [ -f "$INSTALL_DIR/index.php" ] && [ -f "$INSTALL_DIR/includes/config.php" ]; then
        MODE="update"
        info "Existing installation detected - Update mode"
    else
        MODE="install"
        info "No existing installation found - Install mode"
    fi
}

# Create backup of existing installation
create_backup() {
    if [ "$MODE" = "update" ]; then
        log "Creating backup of existing installation..."
        
        BACKUP_NAME="ytdlp-backup-$(date +%Y%m%d-%H%M%S)"
        mkdir -p "$BACKUP_DIR"
        
        # Backup web files
        if [ -d "$INSTALL_DIR" ]; then
            cp -r "$INSTALL_DIR" "$BACKUP_DIR/$BACKUP_NAME-web"
            log "Web files backed up to $BACKUP_DIR/$BACKUP_NAME-web"
        fi
        
        # Backup database
        if command -v mysql >/dev/null 2>&1; then
            if [ -f "$INSTALL_DIR/includes/config.php" ]; then
                # Extract database credentials from config
                DB_USER=$(grep "DB_USER" "$INSTALL_DIR/includes/config.php" | cut -d"'" -f4 2>/dev/null || echo "ytdlp_user")
                DB_NAME=$(grep "DB_NAME" "$INSTALL_DIR/includes/config.php" | cut -d"'" -f4 2>/dev/null || echo "ytdlp_db")
                
                if [ -n "$DB_PASSWORD" ] || [ -f /root/.my.cnf ]; then
                    mysqldump -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" > "$BACKUP_DIR/$BACKUP_NAME-database.sql" 2>/dev/null || {
                        warning "Could not backup database automatically"
                    }
                    log "Database backed up to $BACKUP_DIR/$BACKUP_NAME-database.sql"
                fi
            fi
        fi
        
        success "Backup completed!"
    fi
}

# Download latest version from GitHub
download_latest() {
    log "Downloading latest version from GitHub..."
    
    # Clean temp directory
    rm -rf "$TEMP_DIR"
    mkdir -p "$TEMP_DIR"
    
    # Clone repository
    cd "$TEMP_DIR"
    git clone -b "$REPO_BRANCH" "$REPO_URL" . || {
        error "Failed to download from GitHub"
        exit 1
    }
    
    # Remove git files
    rm -rf .git .gitignore
    
    success "Latest version downloaded successfully!"
}

# Get user input for new installation
get_user_input() {
    if [ "$MODE" = "install" ]; then
        echo -e "${BLUE}=== New Installation Configuration ===${NC}"
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
    else
        # For updates, try to read existing config
        if [ -f "$INSTALL_DIR/includes/config.php" ]; then
            DOMAIN_NAME=$(grep "DOMAIN" "$INSTALL_DIR/includes/config.php" | cut -d"'" -f4 2>/dev/null || echo "")
            SSL_EMAIL=$(grep "ADMIN_EMAIL" "$INSTALL_DIR/includes/config.php" | cut -d"'" -f4 2>/dev/null || echo "")
            
            if [ -z "$DOMAIN_NAME" ]; then
                read -p "Enter your domain name: " DOMAIN_NAME
            fi
            
            info "Using existing configuration for domain: $DOMAIN_NAME"
        fi
    fi
}

# Validate DNS configuration (only for new installs)
validate_dns() {
    if [ "$MODE" = "install" ]; then
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
    fi
}

# Update system packages
update_system() {
    log "Updating system packages..."
    apt update && apt upgrade -y
    log "System updated successfully!"
}

# Install dependencies (only for new installs)
install_dependencies() {
    if [ "$MODE" = "install" ]; then
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
    else
        log "Updating existing packages..."
        apt update
        apt install -y git python3-pip
    fi
}

# Install/Update yt-dlp
install_ytdlp() {
    log "Installing/updating yt-dlp..."
    
    # Try different installation methods based on system
    if command -v pipx >/dev/null 2>&1; then
        # Use pipx if available (recommended for newer systems)
        log "Installing yt-dlp via pipx..."
        pipx install yt-dlp --force
        pipx ensurepath
    else
        # Install pipx first, then yt-dlp
        log "Installing pipx and yt-dlp..."
        apt install -y pipx
        pipx install yt-dlp
        pipx ensurepath
        
        # Add pipx bin to PATH for current session
        export PATH="$HOME/.local/bin:$PATH"
    fi
    
    # Create system-wide symlink
    YTDLP_PATH=$(which yt-dlp 2>/dev/null || echo "$HOME/.local/bin/yt-dlp")
    if [ -f "$YTDLP_PATH" ]; then
        ln -sf "$YTDLP_PATH" /usr/local/bin/yt-dlp
        ln -sf "$YTDLP_PATH" /usr/bin/yt-dlp
    fi
    
    # Verify installation
    if yt-dlp --version > /dev/null 2>&1; then
        log "yt-dlp updated successfully: $(yt-dlp --version)"
    else
        # Fallback: try with --break-system-packages (not recommended but works)
        warning "Trying alternative installation method..."
        pip3 install -U yt-dlp --break-system-packages
        
        if yt-dlp --version > /dev/null 2>&1; then
            log "yt-dlp installed successfully: $(yt-dlp --version)"
        else
            error "yt-dlp installation failed"
            exit 1
        fi
    fi
}

# Configure MySQL (only for new installs)
configure_mysql() {
    if [ "$MODE" = "install" ]; then
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
    fi
}

# Deploy application files
deploy_application() {
    log "Deploying application files..."
    
    # Stop services during update
    if [ "$MODE" = "update" ]; then
        systemctl stop nginx || true
        systemctl stop php8.1-fpm || true
    fi
    
    # Create directories
    mkdir -p $INSTALL_DIR
    mkdir -p /var/www/html/downloads
    mkdir -p /var/log/ytdlp
    
    # Preserve config during update
    CONFIG_BACKUP=""
    if [ "$MODE" = "update" ] && [ -f "$INSTALL_DIR/includes/config.php" ]; then
        CONFIG_BACKUP="/tmp/config.php.backup"
        cp "$INSTALL_DIR/includes/config.php" "$CONFIG_BACKUP"
        log "Existing config preserved"
    fi
    
    # Copy new files
    cp -r "$TEMP_DIR"/* "$INSTALL_DIR/"
    
    # Restore config for updates
    if [ "$MODE" = "update" ] && [ -f "$CONFIG_BACKUP" ]; then
        cp "$CONFIG_BACKUP" "$INSTALL_DIR/includes/config.php"
        rm "$CONFIG_BACKUP"
        log "Config restored"
    fi
    
    # Set permissions
    chown -R www-data:www-data $INSTALL_DIR
    chown -R www-data:www-data /var/www/html/downloads
    chown -R www-data:www-data /var/log/ytdlp
    
    chmod -R 755 $INSTALL_DIR
    chmod -R 755 /var/www/html/downloads
    chmod -R 755 /var/log/ytdlp
    
    # Update config for new installs
    if [ "$MODE" = "install" ]; then
        # Update database config
        sed -i "s/ytdlp_password/$DB_PASSWORD/g" $INSTALL_DIR/includes/config.php
        sed -i "s/your-domain.com/$DOMAIN_NAME/g" $INSTALL_DIR/includes/config.php
        sed -i "s/admin@your-domain.com/$SSL_EMAIL/g" $INSTALL_DIR/includes/config.php
        
        # Initialize database
        log "Initializing database..."
        mysql -u ytdlp_user -p$DB_PASSWORD ytdlp_db < $INSTALL_DIR/database/schema.sql 2>/dev/null || {
            # If schema file doesn't exist, create tables via PHP
            php -f $INSTALL_DIR/includes/init_db.php 2>/dev/null || {
                warning "Could not initialize database automatically"
            }
        }
        
        # Create admin user
        HASHED_PASSWORD=$(php -r "echo password_hash('$ADMIN_PASSWORD', PASSWORD_DEFAULT);")
        mysql -u ytdlp_user -p$DB_PASSWORD ytdlp_db -e "INSERT INTO admin_users (username, password, role, created_at) VALUES ('$ADMIN_USERNAME', '$HASHED_PASSWORD', 'admin', NOW()) ON DUPLICATE KEY UPDATE password='$HASHED_PASSWORD';" 2>/dev/null || {
            warning "Could not create admin user automatically"
        }
    fi
    
    log "Application deployed successfully!"
}

# Configure Nginx (only for new installs)
configure_nginx() {
    if [ "$MODE" = "install" ]; then
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
    fi
}

# Configure SSL (only for new installs)
configure_ssl() {
    if [ "$MODE" = "install" ]; then
        log "Configuring SSL certificate with Let's Encrypt..."
        
        # Obtain SSL certificate
        certbot --nginx -d $DOMAIN_NAME --email $SSL_EMAIL --agree-tos --non-interactive --redirect
        
        # Setup auto-renewal
        systemctl enable certbot.timer
        systemctl start certbot.timer
        
        log "SSL certificate configured successfully!"
    fi
}

# Setup/Update systemd services
setup_services() {
    log "Setting up systemd services..."
    
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
    
    log "Services configured successfully!"
}

# Create cleanup script
create_cleanup_script() {
    if [ ! -f "$INSTALL_DIR/cleanup.php" ]; then
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
    fi
}

# Setup firewall (only for new installs)
setup_firewall() {
    if [ "$MODE" = "install" ]; then
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
    fi
}

# Run database migrations/updates
run_migrations() {
    if [ "$MODE" = "update" ]; then
        log "Running database migrations..."
        
        # Check if migrations directory exists
        if [ -d "$INSTALL_DIR/database/migrations" ]; then
            for migration in "$INSTALL_DIR/database/migrations"/*.sql; do
                if [ -f "$migration" ]; then
                    log "Running migration: $(basename $migration)"
                    mysql -u ytdlp_user -p$DB_PASSWORD ytdlp_db < "$migration" 2>/dev/null || {
                        warning "Migration $(basename $migration) failed or already applied"
                    }
                fi
            done
        fi
        
        # Run PHP migration script if exists
        if [ -f "$INSTALL_DIR/database/migrate.php" ]; then
            php -f "$INSTALL_DIR/database/migrate.php" 2>/dev/null || {
                warning "PHP migration script failed"
            }
        fi
        
        log "Database migrations completed!"
    fi
}

# Restart services
restart_services() {
    log "Restarting services..."
    
    systemctl restart php8.1-fpm || warning "Failed to restart PHP-FPM"
    systemctl restart nginx || warning "Failed to restart Nginx"
    
    if [ "$MODE" = "install" ]; then
        systemctl restart mysql || warning "Failed to restart MySQL"
    fi
    
    log "Services restarted successfully!"
}

# Final testing and cleanup
final_setup() {
    log "Performing final setup and testing..."
    
    # Clean up temp directory
    rm -rf "$TEMP_DIR"
    
    # Test the website
    if [ -n "$DOMAIN_NAME" ]; then
        HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://$DOMAIN_NAME" 2>/dev/null || echo "000")
        if [ "$HTTP_STATUS" -eq 200 ] || [ "$HTTP_STATUS" -eq 301 ] || [ "$HTTP_STATUS" -eq 302 ]; then
            log "Website is accessible!"
        else
            warning "Website might not be accessible (HTTP $HTTP_STATUS)"
        fi
        
        # Test HTTPS for installs
        if [ "$MODE" = "install" ]; then
            HTTPS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://$DOMAIN_NAME" 2>/dev/null || echo "000")
            if [ "$HTTPS_STATUS" -eq 200 ]; then
                log "HTTPS is working!"
            else
                warning "HTTPS might not be working (HTTP $HTTPS_STATUS)"
            fi
        fi
    fi
    
    log "Final setup completed!"
}

# Display summary
display_summary() {
    echo
    if [ "$MODE" = "install" ]; then
        echo -e "${GREEN}=== Installation Summary ===${NC}"
        echo -e "${BLUE}Domain:${NC} https://$DOMAIN_NAME"
        echo -e "${BLUE}Admin Panel:${NC} https://$DOMAIN_NAME/admin/"
        echo -e "${BLUE}Admin User:${NC} $ADMIN_USERNAME"
        echo -e "${BLUE}SSL:${NC} Let's Encrypt certificate installed"
        echo -e "${BLUE}Database:${NC} MySQL with ytdlp_db database"
    else
        echo -e "${GREEN}=== Update Summary ===${NC}"
        echo -e "${BLUE}Repository:${NC} $REPO_URL"
        echo -e "${BLUE}Branch:${NC} $REPO_BRANCH"
        echo -e "${BLUE}Backup:${NC} Created in $BACKUP_DIR"
    fi
    
    echo -e "${BLUE}Features:${NC}"
    echo "  ✓ YouTube video downloading with yt-dlp"
    echo "  ✓ Admin panel with user management"
    echo "  ✓ Rate limiting and maintenance mode"
    echo "  ✓ Real-time server monitoring"
    echo "  ✓ Automatic cleanup of old files"
    echo "  ✓ Security headers and firewall"
    echo
    echo -e "${BLUE}Important Files:${NC}"
    echo "  • Website: $INSTALL_DIR"
    echo "  • Downloads: /var/www/html/downloads"
    echo "  • Logs: /var/log/ytdlp/"
    echo "  • Backups: $BACKUP_DIR"
    echo
    echo -e "${BLUE}Useful Commands:${NC}"
    echo "  • Update: sudo $0"
    echo "  • Check logs: tail -f /var/log/ytdlp/app.log"
    echo "  • Restart services: systemctl restart nginx php8.1-fpm"
    echo "  • Update yt-dlp: pip3 install -U yt-dlp"
    echo
    if [ "$MODE" = "install" ]; then
        echo -e "${GREEN}Installation completed successfully!${NC}"
        echo -e "${GREEN}Your YouTube downloader is ready at: https://$DOMAIN_NAME${NC}"
        echo -e "${YELLOW}Please login to admin panel and change default settings!${NC}"
    else
        echo -e "${GREEN}Update completed successfully!${NC}"
        echo -e "${GREEN}Your YouTube downloader has been updated to the latest version!${NC}"
    fi
}

# Main execution function
main() {
    print_banner
    
    log "Starting YouTube Downloader setup..."
    
    check_root
    check_ubuntu_version
    detect_mode
    
    # Create backup before update
    create_backup
    
    # Download latest version
    download_latest
    
    # Get configuration
    get_user_input
    
    # Validate DNS for new installs
    validate_dns
    
    # System setup
    update_system
    install_dependencies
    install_ytdlp
    
    # Database setup (new installs only)
    configure_mysql
    
    # Deploy application
    deploy_application
    
    # Run migrations for updates
    run_migrations
    
    # Server configuration (new installs only)
    configure_nginx
    configure_ssl
    
    # Services setup
    create_cleanup_script
    setup_services
    
    # Firewall setup (new installs only)
    setup_firewall
    
    # Restart services
    restart_services
    
    # Final testing
    final_setup
    
    # Show summary
    display_summary
    
    success "Setup completed successfully!"
}

# Handle command line arguments
case "${1:-}" in
    --update|-u)
        MODE="update"
        ;;
    --install|-i)
        MODE="install"
        ;;
    --help|-h)
        echo "Usage: $0 [OPTIONS]"
        echo "Options:"
        echo "  --install, -i    Force installation mode"
        echo "  --update, -u     Force update mode"
        echo "  --help, -h       Show this help"
        echo ""
        echo "If no option is specified, the script will auto-detect the mode."
        exit 0
        ;;
esac

# Trap errors
trap 'error "Setup failed at line $LINENO. Check $LOG_FILE for details."; exit 1' ERR

# Run main function
main "$@"
