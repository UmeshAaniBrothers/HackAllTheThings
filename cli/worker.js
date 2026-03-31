#!/usr/bin/env node
/**
 * ╔══════════════════════════════════════════════════╗
 * ║  Scraping Worker — Proxy-aware, Enterprise-grade  ║
 * ╚══════════════════════════════════════════════════╝
 *
 * A single worker that scrapes assigned advertisers through a proxy.
 * Designed to be spawned by orchestrator.js in parallel.
 *
 * Usage (called by orchestrator, not directly):
 *   node cli/worker.js --proxy "http://user:pass@host:port" --advertisers '["AR123|Name"]' --id 1
 */

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const path = require('path');
const https = require('https');
const http = require('http');

puppeteer.use(StealthPlugin());

const SERVER_URL = 'https://phpstack-1170423-6314737.cloudwaysapps.com';
const AUTH_TOKEN = 'ads-intelligent-2024';
const GOOGLE_BASE = 'https://adstransparency.google.com';
const PROJECT_DIR = path.join(__dirname, '..');

// ── Parse Args ──────────────────────────────────────────
const args = process.argv.slice(2);
function getArg(name) {
    const i = args.indexOf(name);
    return i >= 0 && i + 1 < args.length ? args[i + 1] : null;
}

const PROXY = getArg('--proxy');
const WORKER_ID = getArg('--id') || '0';
const VISIBLE = args.includes('--visible');
const advertisersJson = getArg('--advertisers');

if (!advertisersJson) {
    console.error('Usage: node cli/worker.js --proxy "http://..." --advertisers \'[...]\' --id N');
    process.exit(1);
}

const advertisers = JSON.parse(advertisersJson);

// ── Logging with worker prefix ──────────────────────────
function log(msg) {
    const line = JSON.stringify({
        worker: parseInt(WORKER_ID),
        time: new Date().toISOString(),
        msg,
    });
    console.log(line);
}

function report(type, data) {
    console.log(JSON.stringify({ worker: parseInt(WORKER_ID), type, ...data }));
}

// ── Main ────────────────────────────────────────────────
(async () => {
    const proxyLabel = PROXY ? PROXY.replace(/\/\/.*@/, '//***@') : 'direct';
    log(`Starting worker ${WORKER_ID} with ${advertisers.length} advertisers via ${proxyLabel}`);

    const profileDir = path.join(PROJECT_DIR, `.chrome-profile-w${WORKER_ID}`);
    const launchArgs = [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-blink-features=AutomationControlled',
        '--window-size=1440,900',
    ];
    if (PROXY) launchArgs.push(`--proxy-server=${PROXY}`);

    let browser;
    try {
        browser = await puppeteer.launch({
            headless: VISIBLE ? false : 'new',
            userDataDir: profileDir,
            args: launchArgs,
            defaultViewport: { width: 1440, height: 900 },
        });

        const page = await browser.newPage();
        await page.setExtraHTTPHeaders({ 'Accept-Language': 'en-US,en;q=0.9' });

        // ── Init Session ────────────────────────────────
        log('Initializing session...');
        const firstAdv = advertisers[0];
        const initUrl = `${GOOGLE_BASE}/advertiser/${firstAdv.id}?region=anywhere`;

        let sessionReady = false;
        for (let attempt = 1; attempt <= 3; attempt++) {
            await page.goto(initUrl, { waitUntil: 'networkidle2', timeout: 45000 }).catch(() => {});
            await sleep(3000);

            const currentUrl = page.url();
            if (currentUrl.includes('google.com/sorry') || currentUrl.includes('consent.google')) {
                log(`Blocked (attempt ${attempt}/3)`);
                if (VISIBLE) {
                    log('Waiting for manual CAPTCHA solve...');
                    try {
                        await page.waitForFunction(() => !window.location.href.includes('google.com/sorry'), { timeout: 120000 });
                        log('CAPTCHA solved');
                        await page.goto(initUrl, { waitUntil: 'networkidle2', timeout: 45000 });
                        await sleep(3000);
                    } catch { break; }
                } else {
                    if (attempt < 3) {
                        const wait = 30 * attempt;
                        log(`Waiting ${wait}s, then retrying with fresh session...`);
                        await sleep(wait * 1000);
                        const client = await page.target().createCDPSession();
                        await client.send('Network.clearBrowserCookies');
                        await client.detach();
                        continue;
                    }
                    report('blocked', { advertisers_remaining: advertisers.length });
                    return;
                }
            }

            // Handle consent
            try {
                const btn = await page.$('button[aria-label="Accept all"], form[action*="consent"] button');
                if (btn) { await btn.click(); await sleep(2000); }
            } catch {}

            if (page.url().includes('adstransparency.google.com')) {
                sessionReady = true;
                break;
            }
        }

        if (!sessionReady) {
            report('failed', { reason: 'session_init_failed' });
            return;
        }

        log('Session ready');

        // ── Scrape Each Advertiser ──────────────────────
        let totalAds = 0;
        let totalSuccess = 0;
        let totalFailed = 0;
        let consecutiveBlocks = 0;
        let advertisersScraped = 0;

        for (let i = 0; i < advertisers.length; i++) {
            const adv = advertisers[i];

            // Session rotation: refresh after N advertisers
            if (advertisersScraped > 0 && advertisersScraped % 50 === 0) {
                log('Rotating session (50 advertisers done)...');
                await page.goto(`${GOOGLE_BASE}/?region=anywhere`, { waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {});
                await sleep(3000);
                if (page.url().includes('google.com/sorry')) {
                    log('Session rotation triggered block. Stopping.');
                    report('blocked', { advertisers_remaining: advertisers.length - i });
                    break;
                }
            }

            log(`[${i + 1}/${advertisers.length}] ${adv.name} (${adv.id})`);

            try {
                const payloads = await scrapeAdvertiser(page, adv.id);
                if (payloads.length > 0) {
                    let adsInPayloads = 0;
                    for (const raw of payloads) {
                        try {
                            const d = JSON.parse(raw);
                            adsInPayloads += Array.isArray(d['1']) ? d['1'].length : 0;
                        } catch {}
                    }
                    await sendPayloadsToServer(adv.id, adv.name, 'anywhere', payloads);
                    totalAds += adsInPayloads;
                    totalSuccess++;
                    consecutiveBlocks = 0;
                    report('progress', { advertiser: adv.id, ads: adsInPayloads, done: i + 1, total: advertisers.length });
                } else {
                    totalFailed++;
                }
            } catch (err) {
                log(`Error: ${err.message}`);
                totalFailed++;
                consecutiveBlocks++;
                if (consecutiveBlocks >= 5) {
                    log('5 consecutive failures — likely blocked. Stopping.');
                    report('blocked', { advertisers_remaining: advertisers.length - i });
                    break;
                }
            }

            advertisersScraped++;

            // Smart delay between advertisers (randomized)
            if (i < advertisers.length - 1) {
                const delay = 8000 + Math.random() * 7000; // 8-15s
                await sleep(delay);
            }
        }

        await page.close();

        report('done', {
            success: totalSuccess,
            failed: totalFailed,
            total_ads: totalAds,
            advertisers_scraped: advertisersScraped,
        });

    } catch (err) {
        report('error', { message: err.message });
    } finally {
        if (browser) await browser.close();
    }
})();


// ═══════════════════════════════════════════════════════
// Scraping Functions (same API as run.js but optimized)
// ═══════════════════════════════════════════════════════

async function scrapeAdvertiser(page, advertiserId) {
    const allPayloads = [];
    let totalAds = 0;
    let pageToken = null;
    let pageNum = 0;

    // Navigate to advertiser page first to be on the right domain
    if (!page.url().includes('adstransparency.google.com')) {
        await page.goto(`${GOOGLE_BASE}/advertiser/${advertiserId}?region=anywhere`, {
            waitUntil: 'networkidle2', timeout: 30000
        }).catch(() => {});
        await sleep(2000);
    }

    do {
        pageNum++;
        const reqData = {
            '2': 100,
            '3': { '12': { '1': '', '2': true }, '13': { '1': [advertiserId] } },
            '7': { '1': 1 },
        };
        if (pageToken) reqData['4'] = pageToken;

        if (pageNum > 1) await sleep(2000 + Math.random() * 2000);

        let result;
        try {
            result = await page.evaluate(async (reqData) => {
                try {
                    const body = 'f.req=' + encodeURIComponent(JSON.stringify(reqData));
                    const resp = await fetch('/anji/_/rpc/SearchService/SearchCreatives?authuser=0', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body,
                        credentials: 'include',
                    });
                    if (!resp.ok) return { error: `HTTP ${resp.status}` };
                    return { data: await resp.text() };
                } catch (e) { return { error: e.message }; }
            }, reqData);
        } catch (e) {
            result = { error: e.message };
        }

        if (result.error) {
            if (pageNum === 1) {
                // Try fallback: navigate to advertiser page and retry
                await page.goto(`${GOOGLE_BASE}/advertiser/${advertiserId}?region=anywhere`, {
                    waitUntil: 'networkidle2', timeout: 30000
                }).catch(() => {});
                await sleep(3000);

                if (page.url().includes('google.com/sorry')) {
                    throw new Error('BLOCKED');
                }

                // Retry the API call
                try {
                    result = await page.evaluate(async (reqData) => {
                        try {
                            const body = 'f.req=' + encodeURIComponent(JSON.stringify(reqData));
                            const resp = await fetch('/anji/_/rpc/SearchService/SearchCreatives?authuser=0', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body,
                                credentials: 'include',
                            });
                            if (!resp.ok) return { error: `HTTP ${resp.status}` };
                            return { data: await resp.text() };
                        } catch (e) { return { error: e.message }; }
                    }, reqData);
                } catch (e) {
                    result = { error: e.message };
                }
            }
            if (result.error) break;
        }

        let data;
        try { data = JSON.parse(result.data); } catch { break; }

        const ads = data['1'] || [];
        const count = Array.isArray(ads) ? ads.length : 0;
        if (count > 0) {
            allPayloads.push(result.data);
            totalAds += count;
        }

        pageToken = (data['2'] && typeof data['2'] === 'string' && data['2'] !== '') ? data['2'] : null;
    } while (pageToken !== null);

    log(`  ${totalAds} ads in ${allPayloads.length} pages`);
    return allPayloads;
}

async function sendPayloadsToServer(advertiserId, name, region, payloads) {
    const storeUrl = `${SERVER_URL}/dashboard/api/ingest.php?action=store_payload&token=${AUTH_TOKEN}`;
    const updateUrl = `${SERVER_URL}/dashboard/api/ingest.php?action=update_advertiser&token=${AUTH_TOKEN}`;

    await httpPost(updateUrl, { advertiser_id: advertiserId, advertiser_name: name, region: region.toUpperCase() });

    let sent = 0;
    for (const payload of payloads) {
        try {
            await httpPost(storeUrl, { advertiser_id: advertiserId, payload });
            sent++;
        } catch {}
    }
    log(`  Sent ${sent}/${payloads.length} pages to server`);
}

// ── Utilities ───────────────────────────────────────────
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

function httpPost(url, data) {
    return new Promise((resolve, reject) => {
        const body = JSON.stringify(data);
        const urlObj = new URL(url);
        const client = urlObj.protocol === 'https:' ? https : http;
        const req = client.request(urlObj, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) },
            timeout: 30000,
        }, (res) => {
            let d = ''; res.on('data', c => d += c); res.on('end', () => resolve(d));
        });
        req.on('error', reject);
        req.on('timeout', () => { req.destroy(); reject(new Error('Timeout')); });
        req.write(body); req.end();
    });
}
