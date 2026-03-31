#!/bin/bash
# Cron job: Scrape ads + YouTube metadata using Puppeteer (real Chrome)
# No rate limits — Google/YouTube see a real browser.
# Runs via macOS LaunchAgent (auto-runs on wake if missed)

PROJECT_DIR="/Users/aanibrothers/Workspace/Ads Intelligent"
NODE="/opt/homebrew/bin/node"
LOG_FILE="$PROJECT_DIR/cron/scrape.log"

echo "" >> "$LOG_FILE"
echo "=== Scrape started: $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG_FILE"

cd "$PROJECT_DIR"

# Step 1: Scrape all advertisers from Google Ads Transparency
echo "--- Ads scraping ---" >> "$LOG_FILE"
$NODE cli/scraper.js >> "$LOG_FILE" 2>&1

# Step 2: Fetch YouTube metadata (view counts, titles)
echo "" >> "$LOG_FILE"
echo "--- YouTube metadata ---" >> "$LOG_FILE"
$NODE cli/youtube.js --refresh >> "$LOG_FILE" 2>&1

echo "=== Scrape finished: $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG_FILE"

# Keep log file from growing too large (keep last 5000 lines)
tail -5000 "$LOG_FILE" > "$LOG_FILE.tmp" && mv "$LOG_FILE.tmp" "$LOG_FILE"
