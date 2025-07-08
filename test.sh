#!/bin/bash

# Test script for YouTube Downloader functionality
# This script tests the video info API without actually downloading

echo "YouTube Downloader - Test Script"
echo "================================"

# Test video URL (a short, safe test video)
TEST_URL="https://www.youtube.com/watch?v=dQw4w9WgXcQ"

echo "Testing video info extraction..."
echo "URL: $TEST_URL"
echo ""

# Test yt-dlp installation
if command -v yt-dlp &> /dev/null; then
    echo "✓ yt-dlp is installed"
    echo "Version: $(yt-dlp --version)"
else
    echo "✗ yt-dlp is not installed"
    echo "Installing yt-dlp..."
    
    # Try different installation methods for modern Ubuntu
    if command -v pipx &> /dev/null; then
        echo "Using pipx..."
        pipx install yt-dlp
        pipx ensurepath
    elif python3 -c "import sys; exit(0 if sys.version_info >= (3,11) else 1)" 2>/dev/null; then
        echo "Using pip with --break-system-packages (Ubuntu 22.04+)..."
        pip3 install -U yt-dlp --break-system-packages
    else
        echo "Using regular pip..."
        pip3 install -U yt-dlp
    fi
    
    # Verify installation
    if ! command -v yt-dlp &> /dev/null; then
        echo "✗ yt-dlp installation failed"
        echo "Please run the main installation script: sudo ./install.sh"
        exit 1
    fi
fi

echo ""
echo "Testing video information extraction..."

# Test video info extraction
yt-dlp --dump-json --no-warnings "$TEST_URL" 2>/dev/null | head -20

echo ""
echo "Testing available formats..."

# Test format listing
yt-dlp --list-formats --no-warnings "$TEST_URL" 2>/dev/null | head -10

echo ""
echo "Test completed. Check above output for any errors."
echo "If everything looks good, your YouTube downloader should work!"
