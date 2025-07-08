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
    pip3 install -U yt-dlp
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
