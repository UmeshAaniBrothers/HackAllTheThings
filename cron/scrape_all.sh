#!/bin/bash
# Cron job: Scrape all advertisers using Puppeteer (real Chrome browser)
# No more 429 errors — Google sees a real browser, not a bot.
# Runs via macOS LaunchAgent (auto-runs on wake if missed)

PROJECT_DIR="/Users/aanibrothers/Workspace/Ads Intelligent"
NODE="/opt/homebrew/bin/node"
LOG_FILE="$PROJECT_DIR/cron/scrape.log"

echo "" >> "$LOG_FILE"
echo "=== Scrape started: $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG_FILE"

cd "$PROJECT_DIR"
$NODE cli/scraper.js >> "$LOG_FILE" 2>&1

echo "=== Scrape finished: $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG_FILE"

# Keep log file from growing too large (keep last 5000 lines)
tail -5000 "$LOG_FILE" > "$LOG_FILE.tmp" && mv "$LOG_FILE.tmp" "$LOG_FILE"
