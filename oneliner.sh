#!/bin/bash

# YouTube Downloader - One-Click Installer/Updater
# Repository: https://github.com/SenZore/php241

set -e

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

REPO_URL="https://raw.githubusercontent.com/SenZore/php241/main"

print_banner() {
    echo -e "${PURPLE}"
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                                                              ║"
    echo "║          YouTube Downloader - One-Click Setup               ║"
    echo "║                                                              ║"
    echo "║              Repository: SenZore/php241                      ║"
    echo "║                                                              ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

check_root() {
    if [ "$EUID" -ne 0 ]; then
        echo -e "${RED}This script must be run as root. Use: sudo $0${NC}"
        exit 1
    fi
}

detect_mode() {
    if [ -f "/var/www/html/index.php" ] && [ -f "/var/www/html/includes/config.php" ]; then
        echo -e "${BLUE}Existing installation detected - Update mode${NC}"
        MODE="update"
    else
        echo -e "${BLUE}No existing installation found - Install mode${NC}"
        MODE="install"
    fi
}

download_and_run() {
    echo -e "${YELLOW}Downloading latest installer...${NC}"
    
    if [ "$MODE" = "install" ]; then
        curl -fsSL "$REPO_URL/setup.sh" -o "/tmp/ytdlp-setup.sh"
    else
        curl -fsSL "$REPO_URL/update.sh" -o "/tmp/ytdlp-update.sh"
    fi
    
    if [ "$MODE" = "install" ]; then
        chmod +x "/tmp/ytdlp-setup.sh"
        echo -e "${GREEN}Running installer...${NC}"
        exec "/tmp/ytdlp-setup.sh"
    else
        chmod +x "/tmp/ytdlp-update.sh"
        echo -e "${GREEN}Running updater...${NC}"
        exec "/tmp/ytdlp-update.sh"
    fi
}

main() {
    print_banner
    check_root
    detect_mode
    download_and_run
}

case "${1:-}" in
    --help|-h)
        echo "YouTube Downloader One-Click Setup"
        echo "Usage: $0 [OPTIONS]"
        echo ""
        echo "This script automatically detects if you're installing or updating"
        echo "and downloads the appropriate script from GitHub."
        echo ""
        echo "Options:"
        echo "  --help, -h    Show this help"
        echo ""
        echo "Examples:"
        echo "  $0            # Auto-detect and run"
        echo "  curl -fsSL https://raw.githubusercontent.com/SenZore/php241/main/oneliner.sh | sudo bash"
        exit 0
        ;;
esac

main "$@"
