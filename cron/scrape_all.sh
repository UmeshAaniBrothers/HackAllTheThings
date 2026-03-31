#!/bin/bash
# Cron job: Scrape all advertisers from Google Ads Transparency Center
# Runs from Mac only (server IP gets rate-limited by Google)
# Schedule: Twice daily (8 AM and 8 PM)

PROJECT_DIR="/Users/aanibrothers/Workspace/Ads Intelligent"
PHP="/opt/homebrew/Cellar/php/8.5.4/bin/php"
LOG_FILE="$PROJECT_DIR/cron/scrape.log"

echo "" >> "$LOG_FILE"
echo "=== Scrape started: $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG_FILE"

# Step 1: Scrape all advertisers
cd "$PROJECT_DIR"
$PHP cli/scrape.php fetchall >> "$LOG_FILE" 2>&1

echo "=== Scrape finished: $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG_FILE"

# Step 2: Trigger server processing (fire and forget)
curl -s --max-time 10 "https://phpstack-1170423-6314737.cloudwaysapps.com/cron/process.php?token=ads-intelligent-2024" > /dev/null 2>&1 &

# Keep log file from growing too large (keep last 5000 lines)
tail -5000 "$LOG_FILE" > "$LOG_FILE.tmp" && mv "$LOG_FILE.tmp" "$LOG_FILE"
