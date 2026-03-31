#!/bin/bash
# Export Google cookies from Chrome for the scraper
# Run this once: bash cli/export_cookies.sh
#
# PREREQUISITE: First open https://adstransparency.google.com in Chrome
# so the cookies exist in your browser.

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
COOKIE_FILE="$PROJECT_DIR/cli/cookies.txt"
CHROME_COOKIE_DB="$HOME/Library/Application Support/Google/Chrome/Default/Cookies"

# Chrome encrypts cookies on macOS, so we use a Python approach
python3 - "$COOKIE_FILE" << 'PYEOF'
import sys, http.cookiejar, subprocess, json, os

cookie_file = sys.argv[1]

print("Exporting Google cookies from Chrome...")
print()
print("Option 1: Install 'cookies.txt' Chrome extension and export manually")
print("  1. Install: https://chrome.google.com/webstore/detail/cookies-txt/njabckikapfpefapmhpfgabbnlincjmg")
print("  2. Go to https://adstransparency.google.com")
print("  3. Click the extension icon → 'Export' → save to:")
print(f"     {cookie_file}")
print()
print("Option 2: Use this command (requires 'cookie-editor' extension):")
print("  Export from Chrome DevTools Console on adstransparency.google.com:")
print("  document.cookie")
print("  Then paste it when prompted below.")
print()

raw = input("Paste document.cookie value (or press Enter to skip): ").strip()
if raw:
    with open(cookie_file, 'w') as f:
        f.write("# Netscape HTTP Cookie File\n")
        for pair in raw.split(';'):
            pair = pair.strip()
            if '=' in pair:
                name, val = pair.split('=', 1)
                f.write(f".google.com\tTRUE\t/\tTRUE\t0\t{name.strip()}\t{val.strip()}\n")
    print(f"\nCookies saved to {cookie_file}")
    print("Now run: php cli/scrape.php fetchall")
else:
    print("\nSkipped. Please use Option 1 (cookies.txt extension) instead.")
PYEOF
