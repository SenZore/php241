#!/bin/bash

# Fix script for "externally-managed-environment" Python error
# Run this if you encounter pip installation issues on Ubuntu 22.04+

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
echo "Python Environment Fix Script"
echo "======================================"
echo ""

# Check if yt-dlp is already installed
if command -v yt-dlp >/dev/null 2>&1; then
    log "yt-dlp is already installed: $(yt-dlp --version)"
    exit 0
fi

info "Fixing Python environment and installing yt-dlp..."
echo ""

# Method 1: Try pipx (recommended)
log "Attempting Method 1: pipx installation"
if command -v pipx >/dev/null 2>&1; then
    info "pipx is already installed"
else
    info "Installing pipx..."
    if command -v apt >/dev/null 2>&1; then
        sudo apt update && sudo apt install -y pipx
    else
        error "This script is designed for Ubuntu/Debian systems"
        exit 1
    fi
fi

# Install yt-dlp with pipx
log "Installing yt-dlp via pipx..."
pipx install yt-dlp
pipx ensurepath

# Add to current session PATH
export PATH="$HOME/.local/bin:$PATH"

# Create system-wide symlinks
if [ -f "$HOME/.local/bin/yt-dlp" ]; then
    log "Creating system-wide symlinks..."
    sudo ln -sf "$HOME/.local/bin/yt-dlp" /usr/local/bin/yt-dlp
    sudo ln -sf "$HOME/.local/bin/yt-dlp" /usr/bin/yt-dlp
fi

# Test installation
if yt-dlp --version >/dev/null 2>&1; then
    log "✓ Success! yt-dlp installed via pipx"
    log "Version: $(yt-dlp --version)"
    exit 0
fi

# Method 2: Virtual environment fallback
warning "pipx method failed, trying virtual environment..."
log "Attempting Method 2: Virtual environment"

VENV_PATH="/opt/ytdlp-venv"
sudo mkdir -p /opt
sudo python3 -m venv "$VENV_PATH"
sudo "$VENV_PATH/bin/pip" install -U yt-dlp

# Create system-wide symlink
sudo ln -sf "$VENV_PATH/bin/yt-dlp" /usr/local/bin/yt-dlp
sudo ln -sf "$VENV_PATH/bin/yt-dlp" /usr/bin/yt-dlp

# Test installation
if yt-dlp --version >/dev/null 2>&1; then
    log "✓ Success! yt-dlp installed via virtual environment"
    log "Version: $(yt-dlp --version)"
    exit 0
fi

# Method 3: Break system packages (last resort)
warning "Virtual environment method failed, trying --break-system-packages..."
log "Attempting Method 3: Override system protection (not recommended)"

pip3 install -U yt-dlp --break-system-packages

# Test installation
if yt-dlp --version >/dev/null 2>&1; then
    warning "✓ yt-dlp installed with --break-system-packages"
    warning "This method bypasses system package management"
    log "Version: $(yt-dlp --version)"
    exit 0
fi

# All methods failed
error "All installation methods failed!"
error "Please check your Python installation and try again"
echo ""
info "You can also try running the full installer: sudo ./install.sh"
exit 1
