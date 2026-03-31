#!/bin/bash
# Cron: Full pipeline — Ads + YouTube + Processing
# Uses Puppeteer (real Chrome) — no rate limits.
# Runs via macOS LaunchAgent (auto-runs on wake if missed)

PROJECT_DIR="/Users/aanibrothers/Workspace/Ads Intelligent"
NODE="/opt/homebrew/bin/node"
LOG_FILE="$PROJECT_DIR/cron/scrape.log"

echo "" >> "$LOG_FILE"
echo "=== Pipeline started: $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG_FILE"

cd "$PROJECT_DIR"
$NODE cli/run.js >> "$LOG_FILE" 2>&1

echo "=== Pipeline finished: $(date '+%Y-%m-%d %H:%M:%S') ===" >> "$LOG_FILE"

# Keep log file from growing too large
tail -5000 "$LOG_FILE" > "$LOG_FILE.tmp" && mv "$LOG_FILE.tmp" "$LOG_FILE"
