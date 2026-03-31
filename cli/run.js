#!/usr/bin/env node
/**
 * One-click: Scrape Ads + YouTube + Trigger Processing
 *
 * Does everything in one command:
 *   1. Scrapes all advertisers from Google Ads Transparency (Puppeteer)
 *   2. Triggers server processing (raw ads → enriched data)
 *   3. Fetches YouTube metadata (view counts, titles)
 *   4. Triggers server processing again (YouTube data → dashboard)
 *
 * Usage:
 *   node cli/run.js                # Full pipeline
 *   node cli/run.js --visible      # Show browser windows (debug)
 *   node cli/run.js --youtube-only # Skip ads, only YouTube
 *   node cli/run.js --ads-only     # Skip YouTube, only ads
 */

const { execSync, spawn } = require('child_process');
const path = require('path');
const https = require('https');

const SERVER_URL = 'https://phpstack-1170423-6314737.cloudwaysapps.com';
const AUTH_TOKEN = 'ads-intelligent-2024';
const PROJECT_DIR = path.join(__dirname, '..');
const NODE = process.execPath;

const args = process.argv.slice(2);
const visible = args.includes('--visible') ? ' --visible' : '';
const youtubeOnly = args.includes('--youtube-only');
const adsOnly = args.includes('--ads-only');

(async () => {
    const startTime = Date.now();
    console.log('╔══════════════════════════════════════════════╗');
    console.log('║   Ads Intelligence — Full Pipeline           ║');
    console.log('╚══════════════════════════════════════════════╝');
    console.log(`Started: ${new Date().toLocaleString()}\n`);

    let adsOk = true;
    let ytOk = true;

    // ── Step 1: Scrape Ads ──────────────────────────────
    if (!youtubeOnly) {
        console.log('━━━ Step 1/4: Scraping Ads from Google ━━━\n');
        adsOk = await runScript(`${PROJECT_DIR}/cli/scraper.js${visible}`);

        // ── Step 2: Trigger Processing ──────────────────
        console.log('\n━━━ Step 2/4: Processing scraped ads ━━━\n');
        await triggerProcessing('Processing raw ads...');
        // Run processing multiple times to handle all steps
        for (let i = 0; i < 3; i++) {
            await sleep(2000);
            await triggerProcessing(`Processing batch ${i + 2}...`);
        }
    }

    // ── Step 3: YouTube Metadata ────────────────────────
    if (!adsOnly) {
        console.log('\n━━━ Step 3/4: Fetching YouTube metadata ━━━\n');
        ytOk = await runScript(`${PROJECT_DIR}/cli/youtube.js --refresh${visible}`);

        // ── Step 4: Final Processing ────────────────────
        console.log('\n━━━ Step 4/4: Final processing ━━━\n');
        await triggerProcessing('Final processing...');
    }

    // ── Summary ─────────────────────────────────────────
    const elapsed = ((Date.now() - startTime) / 1000 / 60).toFixed(1);
    console.log('\n╔══════════════════════════════════════════════╗');
    console.log('║   ✅ Pipeline Complete                        ║');
    console.log('╚══════════════════════════════════════════════╝');
    console.log(`Time: ${elapsed} minutes`);
    console.log(`Ads scraping: ${youtubeOnly ? 'Skipped' : adsOk ? '✅ Done' : '⚠️ Had errors'}`);
    console.log(`YouTube:      ${adsOnly ? 'Skipped' : ytOk ? '✅ Done' : '⚠️ Had errors'}`);
    console.log(`\nDashboard: ${SERVER_URL}/dashboard/`);
    console.log(`Finished: ${new Date().toLocaleString()}`);
})();

function runScript(scriptPath) {
    return new Promise((resolve) => {
        const child = spawn(NODE, scriptPath.split(' '), {
            stdio: 'inherit',
            cwd: PROJECT_DIR,
        });

        child.on('close', (code) => {
            resolve(code === 0);
        });

        child.on('error', (err) => {
            console.error(`Script error: ${err.message}`);
            resolve(false);
        });
    });
}

async function triggerProcessing(label) {
    process.stdout.write(`${label} `);
    const url = `${SERVER_URL}/cron/process.php?token=${encodeURIComponent(AUTH_TOKEN)}`;
    try {
        const result = await httpGet(url, 60000);
        const data = JSON.parse(result);
        const parts = [];
        if (data.processed > 0) parts.push(`${data.processed} ads processed`);
        if (data.text_enriched > 0) parts.push(`${data.text_enriched} text enriched`);
        if (data.youtube > 0) parts.push(`${data.youtube} YouTube`);
        if (data.countries_enriched > 0) parts.push(`${data.countries_enriched} countries`);
        if (data.products_mapped > 0) parts.push(`${data.products_mapped} products`);
        console.log(parts.length > 0 ? `✅ ${parts.join(', ')}` : '✅ Up to date');
    } catch (err) {
        console.log(`⚠️ ${err.message}`);
    }
}

function httpGet(url, timeout = 30000) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const req = https.get(urlObj, { timeout }, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve(data));
        });
        req.on('error', reject);
        req.on('timeout', () => { req.destroy(); reject(new Error('Timeout')); });
    });
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}
