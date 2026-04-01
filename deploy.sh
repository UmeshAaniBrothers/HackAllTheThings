#!/bin/bash
# Deploy script: pushes to both GitHub repos and triggers Cloudways deploy
#
# Usage: ./deploy.sh
# Or:    bash deploy.sh

set -e
cd "$(dirname "$0")"

echo "📦 Pushing to origin (Ads-Intelligent)..."
git push origin main

echo "🚀 Pushing to Cloudways deploy repo (HackAllTheThings)..."
git push cloudways main:claude/ad-intelligence-dashboard-Cw2P8

echo "🔄 Triggering Cloudways deploy..."
RESPONSE=$(curl -s --max-time 30 "https://phpstack-1170423-6314737.cloudwaysapps.com/gitautodeploy.php?server_id=1170423&app_id=6314737&git_url=git@github.com:UmeshAaniBrothers/HackAllTheThings.git&branch_name=claude%2Fad-intelligence-dashboard-Cw2P8")
echo "Deploy response: $RESPONSE"

echo ""
echo "✅ Done! Code pushed to both repos and deploy triggered."
echo "   Check: https://phpstack-1170423-6314737.cloudwaysapps.com/dashboard/"
