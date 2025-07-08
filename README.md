# YouTube Downloader with YT-DLP

A modern, secure YouTube video downloader built with PHP, featuring real-time server monitoring, rate limiting, and automated deployment.

## 🚀 Quick Start

### One-Command Installation

```bash
# Download and run the installer (recommended)
curl -fsSL https://raw.githubusercontent.com/SenZore/php241/main/oneliner.sh | sudo bash
```

### One-Command Update

```bash
# Update existing installation
curl -fsSL https://raw.githubusercontent.com/SenZore/php241/main/update.sh | sudo bash
```

### Alternative Installation Methods

```bash
# Method 1: Auto-detect install/update
wget https://raw.githubusercontent.com/SenZore/php241/main/setup.sh
chmod +x setup.sh
sudo ./setup.sh

# Method 2: Traditional installer
wget https://raw.githubusercontent.com/SenZore/php241/main/install.sh
chmod +x install.sh
sudo ./install.sh

# Method 3: Git clone
git clone https://github.com/SenZore/php241.git
cd php241
sudo ./setup.sh
```

## 🚀 Quick Deployment Guide

### Step 1: Server Setup
```bash
# Ubuntu 22.04-24.04 server with domain pointing to IP
```

### Step 2: One-Command Deploy
```bash
curl -fsSL https://raw.githubusercontent.com/SenZore/php241/main/oneliner.sh | sudo bash
```

### Step 3: Access Your Site
- **Website**: `https://yourdomain.com`
- **Admin Panel**: `https://yourdomain.com/admin/`

### Step 4: Configure Settings
1. Login to admin panel with your credentials
2. Configure rate limits and settings
3. Test video download functionality

## ⚡ What You'll Need

During installation, you'll be prompted for:
- 🌐 **Domain name** (e.g., `ytdl.yoursite.com`)
- 📧 **SSL email** (for Let's Encrypt certificate)
- 🔐 **Database password** (choose a strong password)
- 👤 **Admin username** (for admin panel access)
- 🔑 **Admin password** (for admin panel access)

## Features

### Core Features
- **YouTube Video Downloading**: Powered by yt-dlp for high-quality downloads
- **Smart Video Analysis**: Auto-detect video info, quality, and formats
- **Multiple Formats**: Support for MP4, MP3, WebM formats
- **Quality Selection**: Choose from various quality options (360p to best available)
- **Rate Limiting**: 5 downloads per 30 minutes per IP address
- **Real-time Progress**: Live download progress with percentage and speed
- **Download History**: Track and manage download history

### Admin Panel Features
- **Secure Login**: Admin-only access at `/admin/`
- **Maintenance Mode**: Toggle site maintenance with custom message
- **User Management**: Create/manage admin users and permissions
- **Rate Limit Control**: Per-user and global rate limits
- **System Settings**: Configure site behavior and limits
- **Activity Logs**: Track all admin actions and downloads

### System Monitoring
- **Real-time CPU Usage**: Live CPU monitoring with visual indicators
- **RAM Usage**: Memory usage tracking with dynamic charts
- **Disk Usage**: Storage space monitoring
- **Load Average**: System load monitoring
- **Uptime Tracking**: Server uptime display
- **Active Downloads**: Current download queue status

### Security Features
- **Rate Limiting**: IP-based download restrictions
- **SSL/TLS**: Automatic HTTPS with Let's Encrypt
- **Input Validation**: Secure URL and parameter validation
- **Access Control**: Protected download directories
- **Firewall Configuration**: UFW firewall setup
- **Security Headers**: Comprehensive HTTP security headers

### Automation
- **One-click Installation**: Automated Ubuntu deployment script
- **Auto-Updates**: Seamless updates with backup system
- **DNS Validation**: Automatic domain configuration checking
- **SSL Certificate**: Automatic Let's Encrypt certificate generation
- **System Services**: Automated cleanup and maintenance
- **Log Management**: Comprehensive logging system

## System Requirements

### Server Requirements
- **OS**: Ubuntu 22.04 - 24.04 LTS
- **RAM**: Minimum 1GB (2GB recommended)
- **Storage**: 10GB+ free space
- **Network**: Public IP address with domain pointing to it

### Software Dependencies (Auto-installed)
- **Web Server**: Nginx
- **Database**: MySQL 8.0+
- **PHP**: PHP 8.1+
- **Python**: Python 3.8+
- **yt-dlp**: Latest version
- **FFmpeg**: For video processing
- **yt-dlp**: Latest version via pip

## Installation

### Automated Installation (Recommended)

1. **Prepare Your Server**
   ```bash
   # Update system
   sudo apt update && sudo apt upgrade -y
   
   # Download installer
   wget https://raw.githubusercontent.com/your-repo/ytdlp-downloader/main/install.sh
   chmod +x install.sh
   ```

2. **Configure DNS**
   - Point your domain's A record to your server's public IP
   - Wait for DNS propagation (usually 5-15 minutes)

3. **Run Installation**
   ```bash
   sudo ./install.sh
   ```

4. **Follow Prompts**
   - Enter your domain name (e.g., ytdl.example.com)
   - Enter email for SSL certificate
   - Set database password

### Manual Installation

If you prefer manual installation, follow these steps:

1. **Install Dependencies**
   ```bash
   sudo apt install nginx mysql-server php8.1 php8.1-fpm php8.1-mysql \
   python3 python3-pip ffmpeg certbot python3-certbot-nginx
   ```

2. **Install yt-dlp**
   ```bash
   pip3 install -U yt-dlp
   ```

3. **Configure Database**
   ```bash
   sudo mysql
   CREATE DATABASE ytdlp_db;
   CREATE USER 'ytdlp_user'@'localhost' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON ytdlp_db.* TO 'ytdlp_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

4. **Deploy Application**
   ```bash
   sudo cp -r * /var/www/html/
   sudo chown -R www-data:www-data /var/www/html/
   ```

5. **Configure Nginx**
   - Copy nginx configuration
   - Enable SSL with Certbot

## Configuration

### Environment Variables
Set these in your `includes/config.php`:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'ytdlp_user');
define('DB_PASS', 'your_secure_password');
define('DB_NAME', 'ytdlp_db');

// Domain Configuration
define('DOMAIN_NAME', 'your-domain.com');
define('SSL_EMAIL', 'admin@your-domain.com');

// Rate Limiting
define('RATE_LIMIT_DOWNLOADS', 5);
define('RATE_LIMIT_WINDOW', 1800); // 30 minutes
```

### Rate Limiting Configuration
- **Downloads per window**: 5 (configurable)
- **Time window**: 30 minutes (configurable)
- **IP-based tracking**: Automatic
- **Reset mechanism**: Automatic after window expires

### Download Configuration
- **Max file size**: 2GB (configurable)
- **Supported formats**: MP4, MP3, WebM
- **Quality options**: 360p, 480p, 720p, best, worst
- **Download directory**: `/var/www/html/downloads/`

## API Endpoints

### Download Video
```
POST /api.php
{
    "action": "download",
    "url": "https://youtube.com/watch?v=...",
    "quality": "720p",
    "format": "mp4"
}
```

### Check Progress
```
POST /api.php
{
    "action": "get_progress",
    "download_id": 123
}
```

### Get System Stats
```
POST /api.php
{
    "action": "get_stats"
}
```

### Get Recent Downloads
```
POST /api.php
{
    "action": "get_recent"
}
```

## Monitoring

### Real-time Monitoring
- **CPU Usage**: Updated every 5 seconds
- **RAM Usage**: Memory consumption tracking
- **Disk Usage**: Storage space monitoring
- **Active Downloads**: Current queue status

### Logs
- **Application Logs**: `/var/log/ytdlp/app.log`
- **Error Logs**: `/var/log/ytdlp/error.log`
- **Nginx Logs**: `/var/log/nginx/access.log`

### Health Checks
```bash
# Check services
systemctl status nginx
systemctl status php8.1-fpm
systemctl status mysql

# Check logs
tail -f /var/log/ytdlp/app.log

# Check disk space
df -h

# Check system load
htop
```

## Security

### Implemented Security Measures
- **HTTPS Enforcement**: Automatic SSL/TLS encryption
- **Rate Limiting**: IP-based download restrictions
- **Input Validation**: URL and parameter sanitization
- **Access Control**: Protected system directories
- **Security Headers**: CSP, XSS protection, etc.
- **Firewall**: UFW configured with minimal ports

### Best Practices
1. **Regular Updates**: Keep yt-dlp and system packages updated
2. **Monitor Logs**: Check for suspicious activity
3. **Backup Database**: Regular database backups
4. **SSL Renewal**: Auto-renewal configured with Certbot
5. **Disk Cleanup**: Automatic cleanup of old downloads

## Troubleshooting

### Common Issues

1. **DNS Not Resolving**
   ```bash
   # Check DNS
   dig your-domain.com
   nslookup your-domain.com
   ```

2. **SSL Certificate Issues**
   ```bash
   # Check certificates
   certbot certificates
   
   # Renew manually
   certbot renew
   ```

3. **Database Connection Issues**
   ```bash
   # Check MySQL
   systemctl status mysql
   mysql -u ytdlp_user -p -e "SELECT 1"
   ```

4. **yt-dlp Issues**
   ```bash
   # Update yt-dlp
   pip3 install -U yt-dlp
   
   # Test manually
   yt-dlp --version
   ```

### Performance Optimization

1. **Nginx Optimization**
   - Enable gzip compression
   - Configure proper caching headers
   - Optimize worker processes

2. **PHP Optimization**
   - Increase memory limits
   - Configure OPcache
   - Optimize FPM settings

3. **Database Optimization**
   - Regular maintenance
   - Index optimization
   - Query optimization

## Maintenance

### Automated Maintenance
- **Daily Cleanup**: Removes downloads older than 7 days
- **Log Rotation**: Automatic log file management
- **SSL Renewal**: Automatic certificate renewal
- **Database Cleanup**: Removes old records

### Manual Maintenance
```bash
# Update yt-dlp
pip3 install -U yt-dlp

# Clean old downloads
php /var/www/html/cleanup.php

# Check SSL expiration
certbot certificates

# Update system
sudo apt update && sudo apt upgrade
```

## 🔄 Updates & Maintenance

### Automatic Updates

The system includes an intelligent update mechanism that automatically detects whether you're installing or updating:

```bash
# Auto-detect and update (recommended)
sudo ./setup.sh --update
```

### One-Command Updates

```bash
# Quick update from GitHub
curl -fsSL https://raw.githubusercontent.com/SenZore/php241/main/update.sh | sudo bash

# Or download and run
wget https://raw.githubusercontent.com/SenZore/php241/main/update.sh
chmod +x update.sh
sudo ./update.sh
```

### Update Process

The update system automatically:
- ✅ **Creates backups** of your files and database
- ✅ **Downloads latest version** from GitHub
- ✅ **Preserves your configuration** during updates
- ✅ **Runs database migrations** for new features
- ✅ **Restarts services** automatically
- ✅ **Validates functionality** after update

### Manual Update

```bash
# For manual updates
git pull origin main
sudo ./setup.sh --update
```

## Contributing

1. Fork the repository
2. Create feature branch
3. Make changes
4. Test thoroughly
5. Submit pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:
- Check the troubleshooting section
- Review logs for error messages
- Open an issue on GitHub
- Contact system administrator

## Changelog

### v1.0.0 (Initial Release)
- YouTube video downloading with yt-dlp
- Real-time server monitoring
- Rate limiting system
- Automated installation script
- SSL/TLS encryption
- Security hardening
- Comprehensive logging
