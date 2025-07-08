#!/bin/bash

# Fix symbolic links and yt-dlp installation issues
# Addresses the "Too many levels of symbolic links" error

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
echo "yt-dlp Installation Fix Script"
echo "======================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    error "This script must be run as root. Use: sudo $0"
    exit 1
fi

log "Fixing yt-dlp installation issues..."

# Step 1: Clean up broken symlinks
log "Cleaning up broken symbolic links..."
find /usr/local/bin -type l -name "*yt-dlp*" -delete 2>/dev/null || true
find /usr/bin -type l -name "*yt-dlp*" -delete 2>/dev/null || true

# Step 2: Remove any existing yt-dlp installations
log "Removing existing yt-dlp installations..."
pip3 uninstall yt-dlp -y 2>/dev/null || true
pipx uninstall yt-dlp 2>/dev/null || true

# Step 3: Clear pip cache
log "Clearing pip cache..."
pip3 cache purge 2>/dev/null || true

# Step 4: Update PATH for current session
export PATH="/root/.local/bin:$PATH"

# Step 5: Try different installation methods
log "Attempting yt-dlp installation..."

# Method 1: Direct pip with virtual environment
log "Method 1: Virtual environment installation"
VENV_PATH="/opt/yt-dlp-venv"
rm -rf "$VENV_PATH" 2>/dev/null || true

python3 -m venv "$VENV_PATH"
"$VENV_PATH/bin/pip" install --upgrade pip
"$VENV_PATH/bin/pip" install yt-dlp

if [ -f "$VENV_PATH/bin/yt-dlp" ]; then
    log "✓ yt-dlp installed in virtual environment"
    
    # Create clean symlink
    ln -sf "$VENV_PATH/bin/yt-dlp" /usr/local/bin/yt-dlp
    chmod +x /usr/local/bin/yt-dlp
    
    # Test installation
    if /usr/local/bin/yt-dlp --version >/dev/null 2>&1; then
        log "✓ yt-dlp is working: $(/usr/local/bin/yt-dlp --version)"
        log "Installation successful via virtual environment!"
        exit 0
    fi
fi

# Method 2: pip with --break-system-packages
log "Method 2: pip with --break-system-packages"
pip3 install --upgrade --break-system-packages yt-dlp

if command -v yt-dlp >/dev/null 2>&1; then
    log "✓ yt-dlp installed via pip with --break-system-packages"
    log "Version: $(yt-dlp --version)"
    exit 0
fi

# Method 3: Download binary directly
log "Method 3: Direct binary download"
YTDLP_URL="https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp"
wget -O /usr/local/bin/yt-dlp "$YTDLP_URL"
chmod +x /usr/local/bin/yt-dlp

if /usr/local/bin/yt-dlp --version >/dev/null 2>&1; then
    log "✓ yt-dlp installed via direct download"
    log "Version: $(/usr/local/bin/yt-dlp --version)"
    exit 0
fi

# Method 4: pipx with force reinstall
log "Method 4: pipx force reinstall"
pipx install yt-dlp --force --include-deps

# Update PATH
export PATH="$HOME/.local/bin:$PATH"

if command -v yt-dlp >/dev/null 2>&1; then
    log "✓ yt-dlp installed via pipx force reinstall"
    
    # Create system-wide symlink
    YTDLP_PATH=$(which yt-dlp)
    ln -sf "$YTDLP_PATH" /usr/local/bin/yt-dlp
    
    log "Version: $(yt-dlp --version)"
    exit 0
fi

error "All installation methods failed"
echo ""
info "Please check the following:"
info "1. Internet connectivity: ping -c 1 pypi.org"
info "2. Disk space: df -h"
info "3. Python installation: python3 --version"
echo ""
info "You can also try manually:"
info "wget https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp"
info "chmod +x yt-dlp"
info "sudo mv yt-dlp /usr/local/bin/"

exit 1
