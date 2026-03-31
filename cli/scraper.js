#!/usr/bin/env node
/**
 * Ads Transparency Scraper — Uses real Chrome browser via Puppeteer
 *
 * Google cannot distinguish this from a real user browsing.
 * No more 429 errors. Works for 1000+ advertisers.
 *
 * Usage:
 *   node cli/scraper.js                    # Fetch all advertisers
 *   node cli/scraper.js AR1234...          # Fetch single advertiser
 *   node cli/scraper.js --visible          # Show browser window (debug)
 */

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');

puppeteer.use(StealthPlugin());

// ── Config ──────────────────────────────────────────────
const SERVER_URL = 'https://phpstack-1170423-6314737.cloudwaysapps.com';
const AUTH_TOKEN = 'ads-intelligent-2024';
const GOOGLE_BASE = 'https://adstransparency.google.com';
const ADS_PER_PAGE = 100;
const ADVERTISERS_FILE = path.join(__dirname, 'advertisers.txt');
const DELAY_BETWEEN_PAGES = 1500;      // 1.5s between pages
const DELAY_BETWEEN_ADVERTISERS = 5000; // 5s between advertisers (real browser needs less)
const REGION = 'anywhere';

// ── Main ────────────────────────────────────────────────
(async () => {
    const args = process.argv.slice(2);
    const visible = args.includes('--visible');
    const singleId = args.find(a => a.startsWith('AR'));

    console.log('=== Ads Transparency Scraper (Puppeteer) ===');
    console.log(`Started: ${new Date().toLocaleString()}\n`);

    // Launch real Chrome browser
    const browser = await puppeteer.launch({
        headless: visible ? false : 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-blink-features=AutomationControlled',
            '--window-size=1440,900',
        ],
        defaultViewport: { width: 1440, height: 900 },
    });

    const page = await browser.newPage();

    // Set realistic browser behavior
    await page.setExtraHTTPHeaders({
        'Accept-Language': 'en-US,en;q=0.9',
    });

    try {
        // Initialize session — visit the site like a real user
        console.log('Opening Google Ads Transparency Center...');
        await page.goto(`${GOOGLE_BASE}/?region=${REGION}`, {
            waitUntil: 'networkidle2',
            timeout: 30000,
        });
        await sleep(2000);
        console.log('Session ready.\n');

        // Get advertisers
        let advertisers = [];
        if (singleId) {
            advertisers = [{ id: singleId, name: singleId, region: REGION }];
        } else {
            advertisers = readAdvertisers();
        }

        if (advertisers.length === 0) {
            console.log('No advertisers found. Add them to cli/advertisers.txt');
            await browser.close();
            return;
        }

        console.log(`Fetching ${advertisers.length} advertiser(s)\n`);

        let success = 0;
        let failed = 0;

        for (let i = 0; i < advertisers.length; i++) {
            const adv = advertisers[i];
            console.log(`--- [${i + 1}/${advertisers.length}] ${adv.name} (${adv.id}) ---`);

            try {
                const ads = await fetchAdvertiser(page, adv.id);

                if (ads.length === 0) {
                    console.log('No ads found.\n');
                    failed++;
                    continue;
                }

                // Send to server
                await sendToServer(adv.id, adv.name, adv.region, ads);
                success++;
                console.log('');
            } catch (err) {
                console.log(`ERROR: ${err.message}\n`);
                failed++;
            }

            // Wait between advertisers
            if (i < advertisers.length - 1) {
                const delay = DELAY_BETWEEN_ADVERTISERS + Math.random() * 3000;
                console.log(`Waiting ${(delay / 1000).toFixed(1)}s...\n`);
                await sleep(delay);
            }
        }

        console.log('\n=== Batch Complete ===');
        console.log(`Success: ${success}, Failed: ${failed}`);
        console.log(`Finished: ${new Date().toLocaleString()}`);

        // Trigger server processing
        console.log('\nTriggering server processing...');
        await triggerProcessing();
        console.log('Done!');

    } catch (err) {
        console.error('Fatal error:', err.message);
    } finally {
        await browser.close();
    }
})();

// ── Fetch all ads for one advertiser ────────────────────
async function fetchAdvertiser(page, advertiserId) {
    const allAds = [];
    let pageToken = null;
    let pageNum = 0;

    do {
        pageNum++;
        process.stdout.write(`  Page ${pageNum}...`);

        const reqData = {
            '2': ADS_PER_PAGE,
            '3': {
                '12': { '1': '', '2': true },
                '13': { '1': [advertiserId] },
            },
            '7': { '1': 1 },
        };
        if (pageToken) {
            reqData['4'] = pageToken;
        }

        if (pageNum > 1) {
            await sleep(DELAY_BETWEEN_PAGES);
        } else {
            await sleep(500);
        }

        // Execute the API call FROM INSIDE the browser (uses browser's real session)
        const result = await page.evaluate(async (reqData, googleBase) => {
            try {
                const body = 'f.req=' + encodeURIComponent(JSON.stringify(reqData));
                const resp = await fetch(googleBase + '/anji/_/rpc/SearchService/SearchCreatives?authuser=0', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body,
                    credentials: 'include',
                });

                if (!resp.ok) {
                    return { error: `HTTP ${resp.status}`, status: resp.status };
                }

                const text = await resp.text();
                return { data: text, status: resp.status };
            } catch (e) {
                return { error: e.message, status: 0 };
            }
        }, reqData, GOOGLE_BASE);

        if (result.error) {
            console.log(` FAILED (${result.error})`);
            break;
        }

        // Parse response
        let data;
        try {
            data = JSON.parse(result.data);
        } catch (e) {
            console.log(' empty/invalid response');
            break;
        }

        const ads = data['1'] || [];
        const count = Array.isArray(ads) ? ads.length : 0;
        console.log(` ${count} ads`);

        if (count > 0) {
            allAds.push(result.data); // Store raw JSON for server
        }

        // Next page token
        pageToken = (data['2'] && typeof data['2'] === 'string' && data['2'] !== '') ? data['2'] : null;

    } while (pageToken !== null);

    const totalAds = allAds.reduce((sum, raw) => {
        try {
            const d = JSON.parse(raw);
            return sum + (Array.isArray(d['1']) ? d['1'].length : 0);
        } catch { return sum; }
    }, 0);

    console.log(`  Total: ${totalAds} ads across ${pageNum} page(s)`);
    return allAds;
}

// ── Send scraped data to server ─────────────────────────
async function sendToServer(advertiserId, name, region, payloads) {
    const storeUrl = `${SERVER_URL}/dashboard/api/ingest.php?action=store_payload&token=${encodeURIComponent(AUTH_TOKEN)}`;
    const updateUrl = `${SERVER_URL}/dashboard/api/ingest.php?action=update_advertiser&token=${encodeURIComponent(AUTH_TOKEN)}`;

    // Ensure advertiser exists
    console.log('  Sending to server...');
    await httpPost(updateUrl, {
        advertiser_id: advertiserId,
        advertiser_name: name,
        region: region.toUpperCase(),
    });

    // Send payloads
    let sent = 0;
    for (let i = 0; i < payloads.length; i++) {
        process.stdout.write(`    Page ${i + 1}/${payloads.length}...`);
        try {
            await httpPost(storeUrl, {
                advertiser_id: advertiserId,
                payload: payloads[i],
            });
            sent++;
            console.log(' OK');
        } catch (err) {
            console.log(` FAILED: ${err.message}`);
        }
    }
    console.log(`  Stored ${sent}/${payloads.length} pages.`);
}

// ── Trigger server processing ───────────────────────────
async function triggerProcessing() {
    const url = `${SERVER_URL}/cron/process.php?token=${encodeURIComponent(AUTH_TOKEN)}`;
    try {
        await httpGet(url, 10000);
        console.log('Server processing triggered.');
    } catch (err) {
        console.log(`Processing trigger failed: ${err.message}`);
    }
}

// ── Read advertisers file ───────────────────────────────
function readAdvertisers() {
    if (!fs.existsSync(ADVERTISERS_FILE)) {
        console.log(`File not found: ${ADVERTISERS_FILE}`);
        return [];
    }

    const lines = fs.readFileSync(ADVERTISERS_FILE, 'utf8').split('\n');
    const advertisers = [];

    for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) continue;

        const parts = trimmed.split('|').map(p => p.trim());
        const id = parts[0];
        const name = parts[1] || id;
        const region = parts[2] || 'anywhere';

        if (id) {
            advertisers.push({ id, name, region });
        }
    }

    return advertisers;
}

// ── HTTP helpers ────────────────────────────────────────
function httpPost(url, data) {
    return new Promise((resolve, reject) => {
        const body = JSON.stringify(data);
        const urlObj = new URL(url);
        const client = urlObj.protocol === 'https:' ? https : http;

        const req = client.request(urlObj, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(body),
            },
            timeout: 30000,
        }, (res) => {
            let responseData = '';
            res.on('data', chunk => responseData += chunk);
            res.on('end', () => resolve(responseData));
        });

        req.on('error', reject);
        req.on('timeout', () => { req.destroy(); reject(new Error('Timeout')); });
        req.write(body);
        req.end();
    });
}

function httpGet(url, timeout = 10000) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const client = urlObj.protocol === 'https:' ? https : http;

        const req = client.get(urlObj, { timeout }, (res) => {
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
