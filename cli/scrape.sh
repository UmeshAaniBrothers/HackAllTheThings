#!/bin/bash
# ╔══════════════════════════════════════════════════════════╗
# ║  One-click scraping — uses your REAL Chrome browser       ║
# ║  Zero CAPTCHA. Zero rate limits. 100% free.               ║
# ╚══════════════════════════════════════════════════════════╝
#
# How it works:
#   1. Opens your real Chrome with remote debugging enabled
#   2. Puppeteer connects to it (inherits all your cookies/sessions)
#   3. Google sees YOUR real Chrome — not a bot
#   4. Scrapes everything, sends to server, processes
#
# Usage:
#   bash cli/scrape.sh          # Full pipeline
#   bash cli/scrape.sh --ads    # Only ads
#   bash cli/scrape.sh --yt     # Only YouTube

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
NODE="/opt/homebrew/bin/node"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
DEBUG_PORT=9222

echo ""
echo "🚀 Ads Intelligence — Enterprise Pipeline (Free Mode)"
echo ""

# Check if Chrome is already running with debugging
if curl -s "http://localhost:${DEBUG_PORT}/json/version" > /dev/null 2>&1; then
    echo "✅ Chrome already running with debug port"
else
    # Close existing Chrome gracefully
    echo "📱 Launching Chrome with remote debugging..."

    # Check if Chrome is running without debug port
    if pgrep -x "Google Chrome" > /dev/null 2>&1; then
        echo "   ⚠ Chrome is open. Please close it first, then run this again."
        echo "   (We need to relaunch it with debug mode enabled)"
        echo ""
        echo "   Or run: bash cli/scrape.sh"
        exit 1
    fi

    # Launch Chrome with remote debugging
    "$CHROME" \
        --remote-debugging-port=${DEBUG_PORT} \
        --no-first-run \
        --no-default-browser-check \
        --user-data-dir="$HOME/Library/Application Support/Google/Chrome" \
        &>/dev/null &

    echo "   Waiting for Chrome to start..."
    for i in $(seq 1 15); do
        if curl -s "http://localhost:${DEBUG_PORT}/json/version" > /dev/null 2>&1; then
            echo "   ✅ Chrome ready"
            break
        fi
        sleep 1
    done

    if ! curl -s "http://localhost:${DEBUG_PORT}/json/version" > /dev/null 2>&1; then
        echo "   ❌ Chrome failed to start with debug port"
        exit 1
    fi
fi

echo ""

# Run the scraper connected to real Chrome
$NODE "$PROJECT_DIR/cli/run-real-chrome.js" "$@"
