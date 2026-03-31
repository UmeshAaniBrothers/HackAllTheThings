#!/bin/bash
# ╔══════════════════════════════════════════════════════════╗
# ║  One-click scraping — uses your REAL Chrome browser       ║
# ║  Zero CAPTCHA. Zero rate limits. 100% free.               ║
# ╚══════════════════════════════════════════════════════════╝
#
# How it works:
#   1. Copies your real Chrome profile (cookies/sessions) to a debug profile
#   2. Opens Chrome-Debug with remote debugging enabled
#   3. Puppeteer connects to it (inherits all your cookies/sessions)
#   4. Google sees YOUR real Chrome — not a bot
#
# Usage:
#   bash cli/scrape.sh          # Full pipeline
#   bash cli/scrape.sh --ads    # Only ads
#   bash cli/scrape.sh --yt     # Only YouTube

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
NODE="/opt/homebrew/bin/node"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
DEBUG_PORT=9222
REAL_PROFILE="$HOME/Library/Application Support/Google/Chrome"
DEBUG_PROFILE="$HOME/Library/Application Support/Google/Chrome-Debug"

echo ""
echo "🚀 Ads Intelligence — Enterprise Pipeline (Free Mode)"
echo ""

# Check if Chrome is already running with debugging
if curl -s "http://localhost:${DEBUG_PORT}/json/version" > /dev/null 2>&1; then
    echo "✅ Chrome-Debug already running with debug port"
else
    echo "📱 Setting up Chrome with remote debugging..."

    # Kill any existing Chrome-Debug instances (by checking for our debug profile)
    if pgrep -f "Chrome-Debug" > /dev/null 2>&1; then
        echo "   Closing existing Chrome-Debug..."
        pkill -f "Chrome-Debug" 2>/dev/null
        sleep 2
    fi

    # Create/update debug profile from real Chrome profile
    mkdir -p "$DEBUG_PROFILE"

    # Always refresh cookies from real Chrome profile
    if [ -d "$REAL_PROFILE/Default" ]; then
        echo "   Syncing cookies from your real Chrome profile..."
        # Copy key files that hold login sessions
        cp "$REAL_PROFILE/Default/Cookies" "$DEBUG_PROFILE/Default/Cookies" 2>/dev/null
        cp "$REAL_PROFILE/Default/Login Data" "$DEBUG_PROFILE/Default/Login Data" 2>/dev/null
        cp "$REAL_PROFILE/Default/Web Data" "$DEBUG_PROFILE/Default/Web Data" 2>/dev/null
        cp "$REAL_PROFILE/Local State" "$DEBUG_PROFILE/Local State" 2>/dev/null

        # On first run, copy the full Default profile
        if [ ! -d "$DEBUG_PROFILE/Default" ]; then
            echo "   First run: copying full Chrome profile..."
            cp -R "$REAL_PROFILE/Default" "$DEBUG_PROFILE/Default"
        fi
    fi

    # Remove stale lock files
    rm -f "$DEBUG_PROFILE/SingletonLock" "$DEBUG_PROFILE/SingletonSocket" "$DEBUG_PROFILE/SingletonCookie" 2>/dev/null

    # Launch Chrome with remote debugging using debug profile
    echo "   Launching Chrome with debug port ${DEBUG_PORT}..."
    "$CHROME" \
        --remote-debugging-port=${DEBUG_PORT} \
        --no-first-run \
        --no-default-browser-check \
        --user-data-dir="$DEBUG_PROFILE" \
        &>/dev/null &

    echo "   Waiting for Chrome to start..."
    for i in $(seq 1 30); do
        if curl -s "http://localhost:${DEBUG_PORT}/json/version" > /dev/null 2>&1; then
            echo "   ✅ Chrome ready (took ${i}s)"
            break
        fi
        sleep 1
    done

    if ! curl -s "http://localhost:${DEBUG_PORT}/json/version" > /dev/null 2>&1; then
        echo "   ❌ Chrome failed to start with debug port"
        echo ""
        echo "   Troubleshooting:"
        echo "   1. Close ALL Chrome windows first"
        echo "   2. Try: pkill -9 -f 'Google Chrome' && sleep 3 && bash cli/scrape.sh"
        exit 1
    fi
fi

echo ""

# Run the scraper connected to real Chrome
$NODE "$PROJECT_DIR/cli/run-real-chrome.js" "$@"
