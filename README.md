# Ads Intelligent

Google Ads Transparency Center scraper and dashboard. Scrapes ad data locally via CLI, processes on server, and displays in a web dashboard.

## Architecture

- **CLI Scraper** (`cli/scrape.php`) — Run from your Mac to scrape Google Ads Transparency API (Google blocks server IPs)
- **Server Ingest** (`dashboard/api/ingest.php`) — Receives and stores raw scraped data
- **Background Processor** (`cron/process.php`) — Processes raw payloads, extracts YouTube URLs, enriches metadata, detects products
- **Dashboard** — Web UI for viewing and filtering ads

## Setup

1. Import `database/schema.sql` into your MySQL database
2. Copy `config/config.example.php` to `config/config.php` and fill in DB credentials
3. Set up cron for background processing:
   ```
   */2 * * * * cd /path/to/app && php cron/process.php >> cron/process.log 2>&1
   ```

## CLI Usage

```bash
php cli/scrape.php test                          # Test connections
php cli/scrape.php search "Nike"                 # Search advertisers
php cli/scrape.php fetch AR1234... "Name"        # Fetch all ads
```

## Stack

- PHP 7.4+ (server), PHP 8.x (CLI)
- MySQL/MariaDB
- Bootstrap 5 frontend
