#!/usr/bin/env node
/**
 * ╔══════════════════════════════════════════════════╗
 * ║  Ads Intelligence — One Click Full Pipeline      ║
 * ╚══════════════════════════════════════════════════╝
 *
 * ONE command does EVERYTHING:
 *   1. Syncs advertisers from advertisers.txt to server
 *   2. Scrapes all ads from Google Ads Transparency (Puppeteer)
 *   3. Processes data on server (text, countries, products, apps)
 *   4. Fetches YouTube metadata — view counts, titles (Puppeteer)
 *   5. Sends YouTube data to server
 *
 * Usage:
 *   node cli/run.js                # Full pipeline
 *   node cli/run.js --visible      # Show browser (debug)
 *   node cli/run.js --youtube-only # Only YouTube
 *   node cli/run.js --ads-only     # Only ads scraping
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
const PROJECT_DIR = path.join(__dirname, '..');
const ADVERTISERS_FILE = path.join(PROJECT_DIR, 'advertisers.txt');

// ── Parse Args ──────────────────────────────────────────
const args = process.argv.slice(2);
const VISIBLE = args.includes('--visible');
const YOUTUBE_ONLY = args.includes('--youtube-only');
const ADS_ONLY = args.includes('--ads-only');

// ── Main ────────────────────────────────────────────────
(async () => {
    const startTime = Date.now();
    log('╔══════════════════════════════════════════════════╗');
    log('║   Ads Intelligence — Full Pipeline               ║');
    log('╚══════════════════════════════════════════════════╝');
    log(`Started: ${new Date().toLocaleString()}\n`);

    const advertisers = readAdvertisers();
    if (advertisers.length === 0) {
        log('❌ No advertisers found. Add them to advertisers.txt');
        return;
    }
    log(`📋 ${advertisers.length} advertisers loaded\n`);

    let browser;
    try {
        // Launch ONE browser for everything
        log('🚀 Launching Chrome...');
        browser = await puppeteer.launch({
            headless: VISIBLE ? false : 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-blink-features=AutomationControlled',
                '--window-size=1440,900',
            ],
            defaultViewport: { width: 1440, height: 900 },
        });
        log('Chrome ready.\n');

        // ── STEP 1: Sync Advertisers ────────────────────
        log('━━━ Step 1: Syncing advertisers to server ━━━\n');
        await syncAdvertisers(advertisers);

        if (!YOUTUBE_ONLY) {
            // ── STEP 2: Scrape Ads ──────────────────────
            log('\n━━━ Step 2: Scraping ads from Google ━━━\n');
            const page = await browser.newPage();
            await page.setExtraHTTPHeaders({ 'Accept-Language': 'en-US,en;q=0.9' });

            // Init session
            log('Opening Google Ads Transparency Center...');
            await page.goto(`${GOOGLE_BASE}/?region=anywhere`, {
                waitUntil: 'networkidle2',
                timeout: 30000,
            });
            await sleep(2000);
            log('Session ready.\n');

            let adsSuccess = 0;
            let adsFailed = 0;

            for (let i = 0; i < advertisers.length; i++) {
                const adv = advertisers[i];
                log(`--- [${i + 1}/${advertisers.length}] ${adv.name} (${adv.id}) ---`);

                try {
                    const payloads = await scrapeAdvertiser(page, adv.id);
                    if (payloads.length === 0) {
                        log('  No ads found.\n');
                        adsFailed++;
                        continue;
                    }
                    await sendPayloadsToServer(adv.id, adv.name, adv.region, payloads);
                    adsSuccess++;
                } catch (err) {
                    log(`  ERROR: ${err.message}`);
                    adsFailed++;
                }

                if (i < advertisers.length - 1) {
                    const delay = 5000 + Math.random() * 3000;
                    log(`  Waiting ${(delay / 1000).toFixed(0)}s...\n`);
                    await sleep(delay);
                }
            }

            await page.close();
            log(`\n✅ Ads scraped: ${adsSuccess} success, ${adsFailed} failed\n`);

            // ── STEP 3: Process on Server ───────────────
            log('━━━ Step 3: Processing ads on server ━━━\n');
            for (let i = 0; i < 5; i++) {
                await triggerProcessing(`  Batch ${i + 1}...`);
                await sleep(2000);
            }
        }

        if (!ADS_ONLY) {
            // ── STEP 4: YouTube Metadata ────────────────
            log('\n━━━ Step 4: Fetching YouTube view counts ━━━\n');

            // Get video ads from server (existing API)
            const videoAds = await getVideoAdsFromServer();
            const pending = videoAds.filter(a => a.youtube_url && (!a.view_count || a.view_count === 0));
            log(`Found ${videoAds.length} video ads, ${pending.length} need YouTube data\n`);

            if (pending.length > 0) {
                const ytPage = await browser.newPage();

                // Block images/css for speed
                await ytPage.setRequestInterception(true);
                ytPage.on('request', (req) => {
                    const type = req.resourceType();
                    if (['image', 'stylesheet', 'font', 'media'].includes(type)) {
                        req.abort();
                    } else {
                        req.continue();
                    }
                });

                // Init YouTube session
                log('Opening YouTube...');
                await ytPage.goto('https://www.youtube.com', {
                    waitUntil: 'domcontentloaded',
                    timeout: 20000,
                });
                await sleep(2000);

                // Handle consent
                try {
                    const btn = await ytPage.$('button[aria-label*="Accept"], button[aria-label*="Reject"]');
                    if (btn) { await btn.click(); await sleep(1000); }
                } catch (e) {}

                log('YouTube ready.\n');

                let ytFetched = 0;
                let ytFailed = 0;
                let consecutiveFails = 0;
                let batch = [];

                // Deduplicate by video ID
                const seen = new Set();
                const uniqueVideos = [];
                for (const ad of pending) {
                    const vid = extractVideoId(ad.youtube_url);
                    if (vid && !seen.has(vid)) {
                        seen.add(vid);
                        uniqueVideos.push({ ...ad, video_id: vid });
                    }
                }

                log(`${uniqueVideos.length} unique videos to fetch\n`);

                for (let i = 0; i < uniqueVideos.length; i++) {
                    const video = uniqueVideos[i];
                    process.stdout.write(`  [${i + 1}/${uniqueVideos.length}] ${video.video_id}... `);

                    try {
                        const meta = await fetchYouTubeMetadata(ytPage, video.video_id);
                        if (meta) {
                            console.log(`✓ ${formatViews(meta.view_count)} views`);
                            batch.push({
                                creative_id: video.creative_id,
                                video_id: video.video_id,
                                title: meta.title,
                                author: meta.author,
                                view_count: meta.view_count,
                                description: buildDescription(meta),
                                headline_source: 'youtube',
                            });
                            ytFetched++;
                            consecutiveFails = 0;
                        } else {
                            console.log('✗ Failed');
                            ytFailed++;
                            consecutiveFails++;
                        }
                    } catch (err) {
                        console.log(`✗ ${err.message}`);
                        ytFailed++;
                        consecutiveFails++;
                    }

                    // Send batch every 50
                    if (batch.length >= 50 || i === uniqueVideos.length - 1) {
                        if (batch.length > 0) {
                            await sendYouTubeToServer(batch);
                            batch = [];
                        }
                    }

                    if (consecutiveFails >= 10) {
                        log('\n  ⚠ Too many failures, stopping YouTube fetch.');
                        break;
                    }

                    if (i < uniqueVideos.length - 1) {
                        await sleep(800 + Math.random() * 500);
                    }
                }

                await ytPage.close();
                log(`\n✅ YouTube: ${ytFetched} fetched, ${ytFailed} failed\n`);
            }

            // ── STEP 5: Final Processing ────────────────
            log('━━━ Step 5: Final processing ━━━\n');
            await triggerProcessing('  Processing...');
        }

    } catch (err) {
        log(`\n❌ Fatal error: ${err.message}`);
    } finally {
        if (browser) await browser.close();
    }

    // ── Summary ─────────────────────────────────────────
    const elapsed = ((Date.now() - startTime) / 1000 / 60).toFixed(1);
    log('\n╔══════════════════════════════════════════════════╗');
    log('║   ✅ Pipeline Complete                            ║');
    log('╚══════════════════════════════════════════════════╝');
    log(`⏱  Time: ${elapsed} minutes`);
    log(`🌐 Dashboard: ${SERVER_URL}/dashboard/`);
    log(`Finished: ${new Date().toLocaleString()}`);
})();


// ═══════════════════════════════════════════════════════
// Helper Functions
// ═══════════════════════════════════════════════════════

function log(msg) { console.log(msg); }

// ── Read advertisers.txt ────────────────────────────────
function readAdvertisers() {
    if (!fs.existsSync(ADVERTISERS_FILE)) {
        log(`File not found: ${ADVERTISERS_FILE}`);
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
        if (id) advertisers.push({ id, name, region });
    }
    return advertisers;
}

// ── Sync advertisers to server ──────────────────────────
async function syncAdvertisers(advertisers) {
    for (const adv of advertisers) {
        process.stdout.write(`  ${adv.name}... `);
        try {
            const url = `${SERVER_URL}/dashboard/api/manage.php?action=add_advertiser&token=${AUTH_TOKEN}`;
            await httpPost(url, {
                advertiser_id: adv.id,
                advertiser_name: adv.name,
            });
            console.log('✓');
        } catch (err) {
            console.log(`✗ ${err.message}`);
        }
    }
}

// ── Scrape ads for one advertiser ───────────────────────
async function scrapeAdvertiser(page, advertiserId) {
    const allPayloads = [];
    let pageToken = null;
    let pageNum = 0;

    do {
        pageNum++;
        process.stdout.write(`  Page ${pageNum}...`);

        const reqData = {
            '2': 100,
            '3': {
                '12': { '1': '', '2': true },
                '13': { '1': [advertiserId] },
            },
            '7': { '1': 1 },
        };
        if (pageToken) reqData['4'] = pageToken;

        if (pageNum > 1) await sleep(1500);
        else await sleep(500);

        const result = await page.evaluate(async (reqData, base) => {
            try {
                const body = 'f.req=' + encodeURIComponent(JSON.stringify(reqData));
                const resp = await fetch(base + '/anji/_/rpc/SearchService/SearchCreatives?authuser=0', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body, credentials: 'include',
                });
                if (!resp.ok) return { error: `HTTP ${resp.status}` };
                return { data: await resp.text() };
            } catch (e) { return { error: e.message }; }
        }, reqData, GOOGLE_BASE);

        if (result.error) { console.log(` FAILED (${result.error})`); break; }

        let data;
        try { data = JSON.parse(result.data); } catch { console.log(' invalid response'); break; }

        const ads = data['1'] || [];
        const count = Array.isArray(ads) ? ads.length : 0;
        console.log(` ${count} ads`);

        if (count > 0) allPayloads.push(result.data);
        pageToken = (data['2'] && typeof data['2'] === 'string' && data['2'] !== '') ? data['2'] : null;
    } while (pageToken !== null);

    const total = allPayloads.reduce((sum, raw) => {
        try { const d = JSON.parse(raw); return sum + (Array.isArray(d['1']) ? d['1'].length : 0); } catch { return sum; }
    }, 0);
    log(`  Total: ${total} ads`);
    return allPayloads;
}

// ── Send scraped payloads to server ─────────────────────
async function sendPayloadsToServer(advertiserId, name, region, payloads) {
    const storeUrl = `${SERVER_URL}/dashboard/api/ingest.php?action=store_payload&token=${AUTH_TOKEN}`;
    const updateUrl = `${SERVER_URL}/dashboard/api/ingest.php?action=update_advertiser&token=${AUTH_TOKEN}`;

    await httpPost(updateUrl, { advertiser_id: advertiserId, advertiser_name: name, region: region.toUpperCase() });

    let sent = 0;
    for (let i = 0; i < payloads.length; i++) {
        try {
            await httpPost(storeUrl, { advertiser_id: advertiserId, payload: payloads[i] });
            sent++;
        } catch (err) {
            log(`    Page ${i + 1} FAILED: ${err.message}`);
        }
    }
    log(`  Sent ${sent}/${payloads.length} pages to server.`);
}

// ── Get video ads from server (existing endpoint) ───────
async function getVideoAdsFromServer() {
    const allAds = [];
    let page = 1;
    const perPage = 100;

    while (true) {
        try {
            const url = `${SERVER_URL}/dashboard/api/ads.php?token=${AUTH_TOKEN}&type=video&per_page=${perPage}&page=${page}`;
            const data = JSON.parse(await httpGet(url));
            const ads = data.ads || [];
            allAds.push(...ads);

            if (ads.length < perPage) break; // Last page
            page++;

            if (page > 500) break; // Safety limit
        } catch (err) {
            log(`  Error fetching page ${page}: ${err.message}`);
            break;
        }
    }
    return allAds;
}

// ── Send YouTube data to server (using existing set_ad_text) ─
async function sendYouTubeToServer(batch) {
    process.stdout.write(`  → Sending ${batch.length} YouTube results... `);
    try {
        // Update headlines and descriptions via existing endpoint
        const texts = batch.map(v => ({
            creative_id: v.creative_id,
            headline: v.title,
            description: v.description,
        }));
        const url = `${SERVER_URL}/dashboard/api/ingest.php?action=set_ad_text&token=${AUTH_TOKEN}`;
        await httpPost(url, { texts });
        console.log('OK');
    } catch (err) {
        console.log(`FAILED: ${err.message}`);
    }
}

// ── Fetch YouTube metadata from page ────────────────────
async function fetchYouTubeMetadata(page, videoId) {
    try {
        await page.goto(`https://www.youtube.com/watch?v=${videoId}`, {
            waitUntil: 'domcontentloaded',
            timeout: 15000,
        });
        await sleep(1500);

        const meta = await page.evaluate(() => {
            let title = null, author = null, viewCount = null;

            // Title
            const titleEl = document.querySelector('h1.ytd-watch-metadata yt-formatted-string, meta[name="title"]');
            title = titleEl?.textContent?.trim() || titleEl?.content?.trim() || null;
            if (!title) title = document.title?.replace(' - YouTube', '').trim() || null;

            // Channel
            const channelEl = document.querySelector('#owner #channel-name a, ytd-channel-name a');
            author = channelEl?.textContent?.trim() || null;

            // View count from page data
            const scripts = document.querySelectorAll('script');
            for (const s of scripts) {
                const match = s.textContent?.match(/"viewCount"\s*:\s*"(\d+)"/);
                if (match) { viewCount = parseInt(match[1], 10); break; }
            }
            if (viewCount === null) {
                const mc = document.querySelector('meta[itemprop="interactionCount"]');
                if (mc) viewCount = parseInt(mc.content, 10);
            }

            return { title, author, viewCount };
        });

        if (!meta || (!meta.title && meta.viewCount === null)) return null;

        return {
            title: meta.title,
            author: meta.author,
            view_count: meta.viewCount,
        };
    } catch { return null; }
}

// ── Trigger server processing ───────────────────────────
async function triggerProcessing(label) {
    process.stdout.write(label + ' ');
    try {
        const url = `${SERVER_URL}/cron/process.php?token=${AUTH_TOKEN}`;
        const result = JSON.parse(await httpGet(url, 60000));
        const parts = [];
        if (result.processed > 0) parts.push(`${result.processed} ads`);
        if (result.text_enriched > 0) parts.push(`${result.text_enriched} text`);
        if (result.youtube > 0) parts.push(`${result.youtube} YT`);
        if (result.countries_enriched > 0) parts.push(`${result.countries_enriched} countries`);
        if (result.products_mapped > 0) parts.push(`${result.products_mapped} products`);
        console.log(parts.length > 0 ? `✅ ${parts.join(', ')}` : '✅ Up to date');
    } catch (err) {
        console.log(`⚠️ ${err.message}`);
    }
}

// ── Utility functions ───────────────────────────────────
function extractVideoId(url) {
    if (!url) return null;
    const m = url.match(/[?&]v=([a-zA-Z0-9_-]{11})/);
    return m ? m[1] : null;
}

function buildDescription(meta) {
    let desc = '';
    if (meta.view_count != null) desc = formatViews(meta.view_count) + ' views';
    if (meta.author) desc += (desc ? ' | ' : '') + 'by ' + meta.author;
    return desc;
}

function formatViews(count) {
    if (count == null) return '0';
    if (count >= 10000000) return (count / 10000000).toFixed(1) + ' Cr';
    if (count >= 100000) return (count / 100000).toFixed(1) + ' L';
    if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
    return count.toString();
}

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

function httpGet(url, timeout = 30000) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const client = urlObj.protocol === 'https:' ? https : http;
        const req = client.get(urlObj, { timeout }, (res) => {
            let d = ''; res.on('data', c => d += c); res.on('end', () => resolve(d));
        });
        req.on('error', reject);
        req.on('timeout', () => { req.destroy(); reject(new Error('Timeout')); });
    });
}
