#!/usr/bin/env node
/**
 * ╔══════════════════════════════════════════════════════════╗
 * ║  Ads Intelligence — Real Chrome Pipeline                  ║
 * ║  Connects to YOUR actual Chrome browser                   ║
 * ║  Zero CAPTCHA. Zero rate limits. 100% free.               ║
 * ╚══════════════════════════════════════════════════════════╝
 *
 * This connects to your real Chrome via Chrome DevTools Protocol.
 * Google sees your actual browser with real cookies, real session,
 * real browsing history — identical to a Chrome extension.
 *
 * Prerequisites:
 *   Chrome must be running with: --remote-debugging-port=9222
 *   (The scrape.sh script handles this automatically)
 *
 * Usage (via scrape.sh):
 *   bash cli/scrape.sh
 */

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');

puppeteer.use(StealthPlugin());

const SERVER_URL = 'https://phpstack-1170423-6314737.cloudwaysapps.com';
const AUTH_TOKEN = 'ads-intelligent-2024';
const GOOGLE_BASE = 'https://adstransparency.google.com';
const PROJECT_DIR = path.join(__dirname, '..');
const ADVERTISERS_FILE = path.join(PROJECT_DIR, 'advertisers.txt');
const DEBUG_PORT = 9222;

const args = process.argv.slice(2);
const ADS_ONLY = args.includes('--ads');
const YT_ONLY = args.includes('--yt');

(async () => {
    const startTime = Date.now();
    log('╔══════════════════════════════════════════════════════════╗');
    log('║   Ads Intelligence — Real Chrome Pipeline                ║');
    log('╚══════════════════════════════════════════════════════════╝');
    log(`Started: ${new Date().toLocaleString()}\n`);

    const advertisers = readAdvertisers();
    if (advertisers.length === 0) {
        log('❌ No advertisers found. Add them to advertisers.txt');
        return;
    }
    log(`📋 ${advertisers.length} advertisers loaded\n`);

    // ── Connect to real Chrome ──────────────────────────
    let browser;
    try {
        log('🔗 Connecting to your Chrome browser...');
        const resp = await httpGet(`http://localhost:${DEBUG_PORT}/json/version`);
        const { webSocketDebuggerUrl } = JSON.parse(resp);
        browser = await puppeteer.connect({
            browserWSEndpoint: webSocketDebuggerUrl,
            defaultViewport: null, // Use Chrome's actual viewport
        });
        log('✅ Connected to Chrome!\n');
    } catch (err) {
        log('❌ Cannot connect to Chrome. Make sure to run: bash cli/scrape.sh');
        log(`   Error: ${err.message}`);
        return;
    }

    // ── Step 1: Sync advertisers ────────────────────────
    log('━━━ Step 1: Syncing advertisers to server ━━━\n');
    await syncAdvertisers(advertisers);

    // Deploy
    log('\n━━━ Deploying latest code ━━━\n');
    try {
        const result = JSON.parse(await httpGet(`${SERVER_URL}/dashboard/api/deploy.php?token=${AUTH_TOKEN}`, 15000));
        log(result.success ? '  ✅ Server updated' : `  ⚠️ Deploy: ${result.output || 'skipped'}`);
    } catch (e) {
        log(`  ⚠️ Deploy skipped: ${e.message}`);
    }

    if (!YT_ONLY) {
        // ── Step 2: Scrape ads ──────────────────────────
        log('\n━━━ Step 2: Scraping ads ━━━\n');

        // Sort advertisers: new/never-fetched first, then oldest-fetched
        const sortedAdvertisers = await getSortedAdvertisers(advertisers);
        log(`📋 Scrape order (new first, then oldest):`);
        sortedAdvertisers.forEach((a, i) => {
            const tag = a.total_ads === 0 ? '🆕 NEW' : `📊 ${a.total_ads} ads, fetched: ${a.last_fetched_at || 'never'}`;
            log(`   ${i+1}. ${a.name} — ${tag}`);
        });
        log('');

        // Open a new tab for scraping (don't mess with user's existing tabs)
        const page = await browser.newPage();

        // Navigate to Google Ads Transparency in the new tab
        log('Opening Google Ads Transparency Center...');
        await page.goto(`${GOOGLE_BASE}/?region=anywhere`, {
            waitUntil: 'networkidle2',
            timeout: 30000,
        });
        await sleep(3000);

        // Handle CAPTCHA / block if IP is flagged from earlier
        if (page.url().includes('google.com/sorry') || page.url().includes('consent.google')) {
            log('⚠️ CAPTCHA detected — please solve it in the Chrome window!');
            log('   Waiting up to 3 minutes for you to complete it...\n');
            try {
                await page.waitForFunction(
                    () => !window.location.href.includes('google.com/sorry') && !window.location.href.includes('consent.google'),
                    { timeout: 180000 }
                );
                log('✅ CAPTCHA solved! Continuing...');
                await page.goto(`${GOOGLE_BASE}/?region=anywhere`, { waitUntil: 'networkidle2', timeout: 30000 });
                await sleep(3000);
            } catch {
                log('❌ CAPTCHA not solved in time. Please solve it and re-run: bash cli/scrape.sh');
                await page.close();
                browser.disconnect();
                return;
            }
        }

        // Handle consent popup
        try {
            const btn = await page.$('button[aria-label="Accept all"], form[action*="consent"] button');
            if (btn) { await btn.click(); await sleep(2000); }
        } catch {}

        if (!page.url().includes('adstransparency.google.com')) {
            log('❌ Could not reach Ads Transparency Center. Current URL: ' + page.url());
            await page.close();
            browser.disconnect();
            return;
        }

        log('Session ready (real Chrome session).\n');

        let totalSuccess = 0;
        let totalFailed = 0;
        let totalAds = 0;
        const scrapeStart = Date.now();

        for (let i = 0; i < sortedAdvertisers.length; i++) {
            const adv = sortedAdvertisers[i];
            const elapsed = formatElapsed(Date.now() - scrapeStart);
            const tag = adv.total_ads === 0 ? ' 🆕' : '';
            log(`--- [${i + 1}/${sortedAdvertisers.length}] ${adv.name}${tag} (${adv.id}) [${elapsed}] ---`);

            try {
                const payloads = await scrapeAdvertiser(page, adv.id);
                if (payloads.length > 0) {
                    let adsCount = 0;
                    for (const raw of payloads) {
                        try { const d = JSON.parse(raw); adsCount += Array.isArray(d['1']) ? d['1'].length : 0; } catch {}
                    }
                    await sendPayloadsToServer(adv.id, adv.name, 'anywhere', payloads);
                    // Update last_fetched_at on server
                    try {
                        await httpPost(`${SERVER_URL}/dashboard/api/ingest.php?action=update_advertiser&token=${AUTH_TOKEN}`, {
                            advertiser_id: adv.id, advertiser_name: adv.name, mark_fetched: true,
                        });
                    } catch {}
                    totalAds += adsCount;
                    totalSuccess++;
                } else {
                    totalFailed++;
                }
            } catch (err) {
                log(`  ERROR: ${err.message}`);
                totalFailed++;

                // If blocked mid-scrape, wait for manual CAPTCHA
                if (err.message === 'BLOCKED' || page.url().includes('google.com/sorry')) {
                    log('\n⚠️ CAPTCHA detected mid-scrape! Please solve it in Chrome...');
                    try {
                        await page.waitForFunction(
                            () => !window.location.href.includes('google.com/sorry'),
                            { timeout: 180000 }
                        );
                        log('✅ CAPTCHA solved! Resuming scraping...');
                        await page.goto(`${GOOGLE_BASE}/?region=anywhere`, { waitUntil: 'networkidle2', timeout: 30000 });
                        await sleep(3000);
                    } catch {
                        log('❌ CAPTCHA not solved. Stopping scrape, will continue with processing.');
                        break;
                    }
                }
            }

            // Human-like delay between advertisers
            if (i < sortedAdvertisers.length - 1) {
                const delay = 5000 + Math.random() * 5000; // 5-10s
                await sleep(delay);
            }
        }

        await page.close(); // Close our tab, keep Chrome open
        log(`\n✅ Scraped: ${totalSuccess} success, ${totalFailed} failed, ${totalAds} ads\n`);

        // ── Step 3: Process on server ───────────────────
        log('━━━ Step 3: Processing on server ━━━\n');
        const steps = ['process', 'text', 'youtube', 'apps', 'countries', 'products'];
        for (const step of steps) {
            let didWork = true;
            let batch = 0;
            while (didWork && batch < 30) {
                batch++;
                didWork = await triggerProcessing(`  ${step} #${batch}...`, step);
                if (didWork) await sleep(500);
            }
        }
    }

    if (!ADS_ONLY) {
        // ── Step 4: YouTube metadata ────────────────────
        log('\n━━━ Step 4: Fetching YouTube view counts ━━━\n');

        // Ask server for only videos that need fetching (new or stale)
        const pendingVideos = await getPendingYouTubeVideos();
        log(`Found ${pendingVideos.length} videos needing YouTube data\n`);

        if (pendingVideos.length > 0) {
            const ytPage = await browser.newPage();

            // Block heavy resources for speed
            await ytPage.setRequestInterception(true);
            ytPage.on('request', (req) => {
                if (['image', 'stylesheet', 'font', 'media'].includes(req.resourceType())) {
                    req.abort();
                } else {
                    req.continue();
                }
            });

            await ytPage.goto('https://www.youtube.com', { waitUntil: 'domcontentloaded', timeout: 20000 });
            await sleep(2000);

            let ytFetched = 0, ytFailed = 0, batch = [];

            for (let i = 0; i < pendingVideos.length; i++) {
                const video = pendingVideos[i];
                const reason = video.reason === 'new' ? '🆕' : '🔄';
                process.stdout.write(`  ${reason} [${i + 1}/${pendingVideos.length}] ${video.video_id}... `);

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
                        });
                        ytFetched++;
                    } else {
                        console.log('✗');
                        ytFailed++;
                    }
                } catch (err) {
                    console.log(`✗ ${err.message}`);
                    ytFailed++;
                }

                if (batch.length >= 50 || i === pendingVideos.length - 1) {
                    if (batch.length > 0) await sendYouTubeToServer(batch.splice(0));
                }

                if (i < pendingVideos.length - 1) await sleep(800 + Math.random() * 500);
            }

            await ytPage.close();
            log(`\n✅ YouTube: ${ytFetched} fetched, ${ytFailed} failed\n`);
        }

        // Final processing
        log('━━━ Final processing ━━━\n');
        for (const step of ['process', 'text', 'products']) {
            let didWork = true, batch = 0;
            while (didWork && batch < 10) {
                batch++;
                didWork = await triggerProcessing(`  ${step} #${batch}...`, step);
                if (didWork) await sleep(500);
            }
        }
    }

    // NOTE: Don't close browser — it's the user's real Chrome!
    // Just disconnect our Puppeteer connection
    browser.disconnect();

    const elapsed = ((Date.now() - startTime) / 1000 / 60).toFixed(1);
    log('\n╔══════════════════════════════════════════════════════════╗');
    log('║   ✅ Pipeline Complete                                    ║');
    log('╚══════════════════════════════════════════════════════════╝');
    log(`⏱  Time: ${elapsed} minutes`);
    log(`🌐 Dashboard: ${SERVER_URL}/dashboard/`);
})();


// ═══════════════════════════════════════════════════════
// Scraping Functions
// ═══════════════════════════════════════════════════════

async function scrapeAdvertiser(page, advertiserId) {
    const allPayloads = [];
    let totalAds = 0;
    let pageToken = null;
    let pageNum = 0;

    // Make sure we're on the right domain
    if (!page.url().includes('adstransparency.google.com')) {
        await page.goto(`${GOOGLE_BASE}/?region=anywhere`, { waitUntil: 'networkidle2', timeout: 30000 });
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
            if (pageNum === 1) log(`  API failed: ${result.error}`);
            break;
        }

        let data;
        try { data = JSON.parse(result.data); } catch { break; }

        const ads = data['1'] || [];
        const count = Array.isArray(ads) ? ads.length : 0;
        if (count > 0) {
            allPayloads.push(result.data);
            totalAds += count;
            process.stdout.write(`  Page ${pageNum}: ${count} ads (total: ${totalAds})\n`);
        }

        pageToken = (data['2'] && typeof data['2'] === 'string' && data['2'] !== '') ? data['2'] : null;
    } while (pageToken !== null);

    log(`  Total: ${totalAds} ads`);
    return allPayloads;
}


// ═══════════════════════════════════════════════════════
// Server Communication
// ═══════════════════════════════════════════════════════

async function syncAdvertisers(advertisers) {
    for (const adv of advertisers) {
        process.stdout.write(`  ${adv.name}... `);
        try {
            await httpPost(`${SERVER_URL}/dashboard/api/manage.php?action=add_advertiser&token=${AUTH_TOKEN}`, {
                advertiser_id: adv.id, advertiser_name: adv.name,
            });
            console.log('✓');
        } catch (err) { console.log(`✗ ${err.message}`); }
    }
}

async function sendPayloadsToServer(advertiserId, name, region, payloads) {
    await httpPost(`${SERVER_URL}/dashboard/api/ingest.php?action=update_advertiser&token=${AUTH_TOKEN}`, {
        advertiser_id: advertiserId, advertiser_name: name, region: region.toUpperCase(),
    });
    let sent = 0;
    for (const payload of payloads) {
        try {
            await httpPost(`${SERVER_URL}/dashboard/api/ingest.php?action=store_payload&token=${AUTH_TOKEN}`, {
                advertiser_id: advertiserId, payload,
            });
            sent++;
        } catch {}
    }
    log(`  Sent ${sent}/${payloads.length} pages to server`);
}

/**
 * Get advertiser list sorted by priority: new (0 ads) first, then oldest-fetched.
 */
async function getSortedAdvertisers(advertisers) {
    try {
        const resp = JSON.parse(await httpGet(`${SERVER_URL}/dashboard/api/overview.php`));
        const serverMap = {};
        for (const a of (resp.advertisers || [])) {
            serverMap[a.advertiser_id] = { total_ads: parseInt(a.total_ads) || 0, last_fetched_at: a.last_fetched_at };
        }
        return advertisers.map(a => ({
            ...a,
            total_ads: (serverMap[a.id] || {}).total_ads || 0,
            last_fetched_at: (serverMap[a.id] || {}).last_fetched_at || null,
        })).sort((a, b) => {
            // New advertisers (0 ads or never fetched) first
            const aIsNew = a.total_ads === 0 || !a.last_fetched_at;
            const bIsNew = b.total_ads === 0 || !b.last_fetched_at;
            if (aIsNew && !bIsNew) return -1;
            if (!aIsNew && bIsNew) return 1;
            // Both new: alphabetical
            if (aIsNew && bIsNew) return a.name.localeCompare(b.name);
            // Both old: oldest-fetched first
            return (a.last_fetched_at || '').localeCompare(b.last_fetched_at || '');
        });
    } catch (err) {
        log(`  ⚠️ Could not get priority order: ${err.message}. Using default order.`);
        return advertisers.map(a => ({ ...a, total_ads: 0, last_fetched_at: null }));
    }
}

/**
 * Get only videos that need YouTube data (new or stale >15 days).
 * Uses server-side API to avoid downloading all 97K ads.
 */
async function getPendingYouTubeVideos() {
    try {
        const data = JSON.parse(await httpGet(`${SERVER_URL}/dashboard/api/ingest.php?action=pending_youtube&token=${AUTH_TOKEN}&limit=500`));
        return data.videos || [];
    } catch (err) {
        log(`  ⚠️ Could not get pending videos: ${err.message}`);
        return [];
    }
}

async function sendYouTubeToServer(batch) {
    process.stdout.write(`  → Sending ${batch.length} YouTube results... `);
    try {
        const videos = batch.map(v => ({
            creative_id: v.creative_id, video_id: v.video_id,
            title: v.title, author: v.author, view_count: v.view_count,
        }));
        const resp = JSON.parse(await httpPost(`${SERVER_URL}/dashboard/api/ingest.php?action=update_youtube&token=${AUTH_TOKEN}`, { videos }));
        console.log(`OK (${resp.updated || 0} updated)`);
    } catch (err) { console.log(`FAILED: ${err.message}`); }
}

async function triggerProcessing(label, step = 'all') {
    process.stdout.write(label + ' ');
    try {
        const result = JSON.parse(await httpGet(`${SERVER_URL}/cron/process.php?token=${AUTH_TOKEN}&limit=5&step=${step}`, 120000));
        const parts = [];
        let didWork = false;
        if (result.processed > 0) { parts.push(`${result.processed} ads`); didWork = true; }
        if (result.text_enriched > 0) { parts.push(`${result.text_enriched} text`); didWork = true; }
        if (result.youtube > 0) { parts.push(`${result.youtube} YT`); didWork = true; }
        if (result.countries_enriched > 0) { parts.push(`${result.countries_enriched} countries`); didWork = true; }
        if (result.products_mapped > 0) { parts.push(`${result.products_mapped} products`); didWork = true; }
        console.log(parts.length > 0 ? `✅ ${parts.join(', ')}` : '✅ Up to date');
        return didWork;
    } catch (err) { console.log(`⚠️ ${err.message}`); return false; }
}


// ═══════════════════════════════════════════════════════
// YouTube Functions
// ═══════════════════════════════════════════════════════

async function fetchYouTubeMetadata(page, videoId) {
    try {
        await page.goto(`https://www.youtube.com/watch?v=${videoId}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
        await sleep(1500);
        const meta = await page.evaluate(() => {
            let title = null, author = null, viewCount = null;
            const titleEl = document.querySelector('h1.ytd-watch-metadata yt-formatted-string, meta[name="title"]');
            title = titleEl?.textContent?.trim() || titleEl?.content?.trim() || document.title?.replace(' - YouTube', '').trim() || null;
            const channelEl = document.querySelector('#owner #channel-name a, ytd-channel-name a');
            author = channelEl?.textContent?.trim() || null;
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
        return { title: meta.title, author: meta.author, view_count: meta.viewCount };
    } catch { return null; }
}


// ═══════════════════════════════════════════════════════
// Utilities
// ═══════════════════════════════════════════════════════

function log(msg) { console.log(msg); }

function readAdvertisers() {
    if (!fs.existsSync(ADVERTISERS_FILE)) return [];
    const lines = fs.readFileSync(ADVERTISERS_FILE, 'utf8').split('\n');
    const all = lines
        .map(l => l.trim()).filter(l => l && !l.startsWith('#'))
        .map(l => { const p = l.split('|').map(s => s.trim()); return { id: p[0], name: p[1] || p[0] }; })
        .filter(a => a.id);

    // Deduplicate by advertiser ID
    const seen = new Set();
    const unique = [];
    const dupes = [];
    for (const a of all) {
        if (seen.has(a.id)) { dupes.push(a.id); continue; }
        seen.add(a.id);
        unique.push(a);
    }

    // If duplicates found, rewrite the file with clean list
    if (dupes.length > 0) {
        log(`⚠️ Removed ${dupes.length} duplicate(s) from advertisers.txt`);
        const header = lines.filter(l => l.trimStart().startsWith('#')).join('\n');
        const body = unique.map(a => `${a.id} | ${a.name}`).join('\n');
        fs.writeFileSync(ADVERTISERS_FILE, header + '\n\n' + body + '\n', 'utf8');
    }

    return unique;
}

function extractVideoId(url) {
    if (!url) return null;
    const m = url.match(/[?&]v=([a-zA-Z0-9_-]{11})/);
    return m ? m[1] : null;
}

function formatViews(c) {
    if (c == null) return '0';
    if (c >= 1e7) return (c/1e7).toFixed(1) + ' Cr';
    if (c >= 1e5) return (c/1e5).toFixed(1) + ' L';
    if (c >= 1e3) return (c/1e3).toFixed(1) + 'K';
    return c.toString();
}

function formatElapsed(ms) {
    const s = Math.round(ms/1000);
    if (s < 60) return `${s}s`;
    const m = Math.floor(s/60);
    if (m < 60) return `${m}m ${s%60}s`;
    return `${Math.floor(m/60)}h ${m%60}m`;
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

function httpPost(url, data) {
    return new Promise((resolve, reject) => {
        const body = JSON.stringify(data);
        const u = new URL(url);
        const c = u.protocol === 'https:' ? https : http;
        const req = c.request(u, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) }, timeout: 30000 }, (res) => {
            let d = ''; res.on('data', ch => d += ch); res.on('end', () => resolve(d));
        });
        req.on('error', reject);
        req.on('timeout', () => { req.destroy(); reject(new Error('Timeout')); });
        req.write(body); req.end();
    });
}

function httpGet(url, timeout = 30000) {
    return new Promise((resolve, reject) => {
        const u = new URL(url);
        const c = u.protocol === 'https:' ? https : http;
        const req = c.get(u, { timeout }, (res) => {
            let d = ''; res.on('data', ch => d += ch); res.on('end', () => resolve(d));
        });
        req.on('error', reject);
        req.on('timeout', () => { req.destroy(); reject(new Error('Timeout')); });
    });
}
