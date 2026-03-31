# Server Cron Setup (Cloudways)

## Go to Cloudways Panel:
1. Login to https://platform.cloudways.com
2. Select your server → Application → **Cron Job Management**
3. Add this cron job:

**Schedule:** Every 2 minutes
```
*/2 * * * *
```

**Command:**
```
cd /home/master/applications/phpstack-1170423/public_html && /usr/local/bin/php cron/process.php >> cron/process.log 2>&1
```

This automatically processes scraped data every 2 minutes (ads, YouTube, products, countries, app metadata).

## Alternative: Use wget cron (simpler)
```
*/2 * * * * wget -q -O /dev/null "https://phpstack-1170423-6314737.cloudwaysapps.com/cron/process.php?token=ads-intelligent-2024"
```
