#!/bin/bash

# Installation Status and Health Check Script
# Use this to verify your YouTube downloader installation

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log() {
    echo -e "${GREEN}[✓] $1${NC}"
}

error() {
    echo -e "${RED}[✗] $1${NC}"
}

warning() {
    echo -e "${YELLOW}[!] $1${NC}"
}

info() {
    echo -e "${BLUE}[i] $1${NC}"
}

echo "======================================"
echo "YouTube Downloader - System Check"
echo "======================================"
echo ""

# Check system requirements
info "Checking system requirements..."

# Check Ubuntu version
if grep -q "Ubuntu" /etc/os-release; then
    UBUNTU_VERSION=$(lsb_release -rs)
    log "Ubuntu version: $UBUNTU_VERSION"
    
    if [[ "$UBUNTU_VERSION" == "22.04" || "$UBUNTU_VERSION" == "24.04" ]]; then
        log "✓ Ubuntu version is supported"
    else
        warning "Ubuntu version may not be fully supported"
    fi
else
    warning "Not running on Ubuntu"
fi

# Check if running as root for some checks
if [ "$EUID" -eq 0 ]; then
    ROOT_CHECK=true
else
    ROOT_CHECK=false
    info "Some checks require root privileges"
fi

echo ""
info "Checking core services..."

# Check Nginx
if systemctl is-active --quiet nginx 2>/dev/null; then
    log "Nginx service is running"
    
    # Check if nginx is serving on port 80/443
    if netstat -tlnp 2>/dev/null | grep -q ":80\|:443"; then
        log "Nginx is listening on web ports"
    else
        warning "Nginx may not be configured properly"
    fi
else
    error "Nginx service is not running"
fi

# Check PHP-FPM
if systemctl is-active --quiet php8.1-fpm 2>/dev/null; then
    log "PHP-FPM service is running"
else
    error "PHP-FPM service is not running"
fi

# Check MySQL
if systemctl is-active --quiet mysql 2>/dev/null; then
    log "MySQL service is running"
    
    # Test MySQL connection
    if mysql -e "SELECT 1;" >/dev/null 2>&1; then
        log "MySQL connection successful"
    else
        warning "MySQL connection failed (may need password)"
    fi
else
    error "MySQL service is not running"
fi

echo ""
info "Checking Python environment..."

# Check Python
if command -v python3 >/dev/null 2>&1; then
    PYTHON_VERSION=$(python3 --version | cut -d' ' -f2)
    log "Python version: $PYTHON_VERSION"
else
    error "Python 3 is not installed"
fi

# Check pip
if command -v pip3 >/dev/null 2>&1; then
    log "pip3 is available"
else
    warning "pip3 is not available"
fi

# Check pipx
if command -v pipx >/dev/null 2>&1; then
    log "pipx is available"
else
    warning "pipx is not available"
fi

# Check yt-dlp
if command -v yt-dlp >/dev/null 2>&1; then
    YTDLP_VERSION=$(yt-dlp --version)
    log "yt-dlp version: $YTDLP_VERSION"
    
    # Test yt-dlp functionality
    if yt-dlp --help >/dev/null 2>&1; then
        log "yt-dlp is functional"
    else
        warning "yt-dlp may have issues"
    fi
else
    error "yt-dlp is not installed"
fi

echo ""
info "Checking application files..."

# Check web directory
if [ -d "/var/www/html" ]; then
    log "Web directory exists"
    
    # Check key files
    if [ -f "/var/www/html/index.php" ]; then
        log "Main application file exists"
    else
        error "Main application file missing"
    fi
    
    if [ -f "/var/www/html/includes/config.php" ]; then
        log "Configuration file exists"
    else
        error "Configuration file missing"
    fi
    
    if [ -f "/var/www/html/api.php" ]; then
        log "API file exists"
    else
        error "API file missing"
    fi
else
    error "Web directory not found"
fi

echo ""
info "Checking SSL certificates..."

# Check SSL certificates
if [ -d "/etc/letsencrypt/live" ]; then
    CERT_DIRS=$(find /etc/letsencrypt/live -maxdepth 1 -type d | wc -l)
    if [ $CERT_DIRS -gt 1 ]; then
        log "SSL certificates found"
    else
        warning "No SSL certificates found"
    fi
else
    warning "Let's Encrypt directory not found"
fi

echo ""
info "Checking firewall..."

# Check UFW
if command -v ufw >/dev/null 2>&1; then
    UFW_STATUS=$(ufw status | head -1)
    if echo "$UFW_STATUS" | grep -q "active"; then
        log "UFW firewall is active"
    else
        warning "UFW firewall is not active"
    fi
else
    warning "UFW not installed"
fi

echo ""
info "Checking network connectivity..."

# Check internet connectivity
if ping -c 1 google.com >/dev/null 2>&1; then
    log "Internet connectivity is working"
else
    error "No internet connectivity"
fi

echo ""
info "System resource usage:"

# Check disk space
df -h / | tail -1 | awk '{print "Disk usage: " $5 " of " $2 " used"}'

# Check memory
free -h | grep "Mem:" | awk '{print "Memory usage: " $3 " of " $2 " used"}'

# Check CPU load
uptime | awk '{print "Load average: " $10 " " $11 " " $12}'

echo ""
log "System check complete!"
echo ""
info "If you see any errors above, check the troubleshooting section in README.md"
info "Or run specific fix scripts: ./fix-mysql.sh, ./fix-python-env.sh"
