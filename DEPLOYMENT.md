# Deployment Guide

This guide provides multiple deployment options for the YouTube Downloader with YT-DLP.

## Quick Start (Recommended)

### Option 1: Automated Script Installation

1. **Prepare your Ubuntu server (22.04-24.04)**
   ```bash
   # Update system
   sudo apt update && sudo apt upgrade -y
   
   # Download the project
   git clone https://github.com/your-username/ytdlp-downloader.git
   cd ytdlp-downloader
   ```

2. **Configure DNS**
   - Point your domain's A record to your server's public IP
   - Wait for DNS propagation (5-15 minutes)

3. **Run the installer**
   ```bash
   chmod +x install.sh
   sudo ./install.sh
   ```

4. **Follow the prompts**
   - Enter your domain name
   - Enter SSL email address
   - Set database password

5. **Access your site**
   - Open https://your-domain.com
   - Start downloading videos!

### Option 2: Docker Deployment

1. **Install Docker and Docker Compose**
   ```bash
   curl -fsSL https://get.docker.com -o get-docker.sh
   sudo sh get-docker.sh
   sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
   sudo chmod +x /usr/local/bin/docker-compose
   ```

2. **Deploy with Docker Compose**
   ```bash
   # Clone repository
   git clone https://github.com/your-username/ytdlp-downloader.git
   cd ytdlp-downloader
   
   # Configure environment
   cp .env.example .env
   nano .env  # Edit with your settings
   
   # Deploy
   docker-compose up -d
   ```

3. **Access via localhost**
   - Open http://localhost
   - For production, configure reverse proxy

## Manual Installation Guide

### Prerequisites

- Ubuntu 22.04 or 24.04 LTS
- Root or sudo access
- Domain pointing to server IP
- Minimum 1GB RAM, 10GB storage

### Step 1: Install Dependencies

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install web server and database
sudo apt install -y nginx mysql-server

# Install PHP and extensions
sudo apt install -y php8.1 php8.1-fpm php8.1-mysql php8.1-curl php8.1-gd php8.1-mbstring php8.1-xml php8.1-zip

# Install Python and multimedia tools
sudo apt install -y python3 python3-pip ffmpeg

# Install SSL tools
sudo apt install -y certbot python3-certbot-nginx

# Install system tools
sudo apt install -y curl wget unzip git htop
```

### Step 2: Install yt-dlp

```bash
# For Ubuntu 22.04+ (pipx method - recommended)
sudo apt install -y pipx
pipx install yt-dlp
pipx ensurepath

# Create system-wide symlink
sudo ln -sf ~/.local/bin/yt-dlp /usr/local/bin/yt-dlp

# Alternative for older systems or if pipx fails
# pip3 install -U yt-dlp --break-system-packages

# Verify installation
yt-dlp --version
```

**Note:** Ubuntu 22.04+ uses "externally-managed-environment" which prevents pip from installing packages system-wide. The auto-installer handles this automatically, but for manual installation, use pipx as shown above.

### Step 3: Configure Database

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -e "CREATE DATABASE ytdlp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'ytdlp_user'@'localhost' IDENTIFIED BY 'your_secure_password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON ytdlp_db.* TO 'ytdlp_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

### Step 4: Deploy Application

```bash
# Clone repository
git clone https://github.com/your-username/ytdlp-downloader.git
cd ytdlp-downloader

# Copy files to web root
sudo cp -r . /var/www/html/

# Set permissions
sudo chown -R www-data:www-data /var/www/html/
sudo chmod -R 755 /var/www/html/

# Create required directories
sudo mkdir -p /var/www/html/downloads
sudo mkdir -p /var/log/ytdlp
sudo chown -R www-data:www-data /var/www/html/downloads
sudo chown -R www-data:www-data /var/log/ytdlp
```

### Step 5: Configure Application

```bash
# Edit configuration file
sudo nano /var/www/html/includes/config.php

# Update these values:
# - DB_PASS: your database password
# - DOMAIN_NAME: your domain
# - SSL_EMAIL: your email
```

### Step 6: Configure Nginx

```bash
# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Create new site configuration
sudo nano /etc/nginx/sites-available/your-domain.com
```

Copy the Nginx configuration from the install script or use:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    
    root /var/www/html;
    index index.php index.html;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    
    # PHP configuration
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Rate limiting
    limit_req_zone $binary_remote_addr zone=download:10m rate=5r/m;
    location /api.php {
        limit_req zone=download burst=10 nodelay;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/your-domain.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Step 7: Configure SSL

```bash
# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com --email your-email@domain.com --agree-tos --non-interactive

# Verify auto-renewal
sudo certbot renew --dry-run
```

### Step 8: Configure PHP

```bash
# Edit PHP configuration
sudo nano /etc/php/8.1/fpm/php.ini

# Update these values:
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

### Step 9: Set Up Firewall

```bash
# Install and configure UFW
sudo apt install -y ufw
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
```

### Step 10: Create System Services

```bash
# Create cleanup service
sudo nano /etc/systemd/system/ytdlp-cleanup.service
```

Add the cleanup service configuration from the install script.

```bash
# Create cleanup timer
sudo nano /etc/systemd/system/ytdlp-cleanup.timer
```

Add the timer configuration from the install script.

```bash
# Enable and start services
sudo systemctl daemon-reload
sudo systemctl enable ytdlp-cleanup.timer
sudo systemctl start ytdlp-cleanup.timer
```

## Production Deployment

### Additional Security Measures

1. **Fail2Ban Installation**
   ```bash
   sudo apt install fail2ban
   sudo systemctl enable fail2ban
   sudo systemctl start fail2ban
   ```

2. **Log Monitoring**
   ```bash
   # Set up log rotation
   sudo nano /etc/logrotate.d/ytdlp
   ```

3. **Performance Monitoring**
   ```bash
   # Install monitoring tools
   sudo apt install htop iotop nethogs
   ```

### Backup Strategy

1. **Database Backup**
   ```bash
   # Create backup script
   sudo nano /usr/local/bin/backup-ytdlp.sh
   
   #!/bin/bash
   mysqldump -u ytdlp_user -p ytdlp_db > /backup/ytdlp_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **File Backup**
   ```bash
   # Backup application files
   tar -czf /backup/ytdlp_files_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/html/
   ```

### Monitoring and Maintenance

1. **Health Checks**
   ```bash
   # Check service status
   sudo systemctl status nginx php8.1-fpm mysql
   
   # Check logs
   sudo tail -f /var/log/ytdlp/app.log
   sudo tail -f /var/log/nginx/error.log
   ```

2. **Performance Monitoring**
   ```bash
   # Monitor system resources
   htop
   df -h
   free -h
   ```

3. **Update Procedures**
   ```bash
   # Update yt-dlp
   pip3 install -U yt-dlp
   
   # Update system packages
   sudo apt update && sudo apt upgrade
   
   # Update SSL certificates
   sudo certbot renew
   ```

## Troubleshooting

### Common Issues

1. **502 Bad Gateway**
   - Check PHP-FPM status: `sudo systemctl status php8.1-fpm`
   - Check Nginx configuration: `sudo nginx -t`

2. **Database Connection Issues**
   - Verify MySQL is running: `sudo systemctl status mysql`
   - Check credentials in config.php

3. **SSL Certificate Issues**
   - Check certificate status: `sudo certbot certificates`
   - Renew manually: `sudo certbot renew`

4. **Permission Issues**
   - Fix ownership: `sudo chown -R www-data:www-data /var/www/html/`
   - Fix permissions: `sudo chmod -R 755 /var/www/html/`

### Log Files

- **Application Logs**: `/var/log/ytdlp/app.log`
- **Error Logs**: `/var/log/ytdlp/error.log`
- **Nginx Logs**: `/var/log/nginx/access.log`, `/var/log/nginx/error.log`
- **PHP Logs**: `/var/log/php8.1-fpm.log`

## Performance Optimization

### Nginx Optimization

```nginx
# Add to nginx.conf
worker_processes auto;
worker_connections 1024;

# Enable gzip compression
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
```

### PHP Optimization

```ini
# In php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

### Database Optimization

```sql
-- Optimize database tables
OPTIMIZE TABLE downloads;
OPTIMIZE TABLE rate_limits;
OPTIMIZE TABLE system_stats;
```

## Security Checklist

- [ ] SSL certificate installed and auto-renewing
- [ ] Firewall configured with minimal ports
- [ ] Database secured with strong passwords
- [ ] File permissions properly set
- [ ] Security headers enabled
- [ ] Rate limiting configured
- [ ] Log monitoring in place
- [ ] Backup strategy implemented
- [ ] Updates scheduled
- [ ] Monitoring tools installed

## Support

For additional support:
- Check the README.md for detailed documentation
- Review logs for error messages
- Check GitHub issues for known problems
- Contact system administrator for server-specific issues
