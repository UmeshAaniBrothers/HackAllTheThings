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
 *   node cli/run.js                # Full pipeline (headless, reuses saved session)
 *   node cli/run.js --visible      # Show browser (for first-time CAPTCHA solving)
 *   node cli/run.js --youtube-only # Only YouTube metadata
 *   node cli/run.js --ads-only     # Only ads scraping
 *
 * First run: Use --visible, solve CAPTCHA once. Cookies are saved.
 * After that: Run without --visible. No CAPTCHA needed ever again.
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
const CHROME_PROFILE_DIR = path.join(PROJECT_DIR, '.chrome-profile'); // Persistent cookies

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
        // Launch ONE browser for everything — uses persistent profile for cookies
        // After solving CAPTCHA once, cookies are saved and reused forever
        log('🚀 Launching Chrome...');
        browser = await puppeteer.launch({
            headless: VISIBLE ? false : 'new',
            userDataDir: CHROME_PROFILE_DIR,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-blink-features=AutomationControlled',
                '--window-size=1440,900',
            ],
            defaultViewport: { width: 1440, height: 900 },
        });
        log('Chrome ready (persistent session).\n');

        // ── STEP 1: Sync Advertisers ────────────────────
        log('━━━ Step 1: Syncing advertisers to server ━━━\n');
        await syncAdvertisers(advertisers);

        // ── STEP 1.5: Auto-deploy latest code ──────────
        log('\n━━━ Deploying latest code to server ━━━\n');
        try {
            const deployUrl = `${SERVER_URL}/dashboard/api/deploy.php?token=${AUTH_TOKEN}`;
            const result = JSON.parse(await httpGet(deployUrl, 15000));
            log(result.success ? '  ✅ Server updated' : `  ⚠️ Deploy: ${result.output}`);
        } catch (e) {
            log(`  ⚠️ Deploy skipped: ${e.message}`);
        }

        if (!YOUTUBE_ONLY) {
            // ── STEP 2: Scrape Ads ──────────────────────
            log('\n━━━ Step 2: Scraping ads from Google ━━━\n');
            const page = await browser.newPage();
            await page.setExtraHTTPHeaders({ 'Accept-Language': 'en-US,en;q=0.9' });

            // Init session — navigate to an advertiser page directly
            // Going to the homepage can trigger Google's bot detection more easily
            const firstAdv = advertisers[0];
            const initUrl = `${GOOGLE_BASE}/advertiser/${firstAdv.id}?region=anywhere`;
            log(`Opening Google Ads Transparency Center...`);

            let sessionReady = false;
            for (let initAttempt = 1; initAttempt <= 3; initAttempt++) {
                await page.goto(initUrl, {
                    waitUntil: 'networkidle2',
                    timeout: 45000,
                });
                await sleep(3000);

                // Check if we got blocked by Google's "unusual traffic" page
                const currentUrl = page.url();
                if (currentUrl.includes('google.com/sorry') || currentUrl.includes('consent.google')) {
                    log(`  ⚠ Google detected automation (attempt ${initAttempt}/3)`);

                    if (VISIBLE) {
                        // In visible mode, wait for user to solve CAPTCHA
                        log('  👉 Please solve the CAPTCHA in the browser window...');
                        log('  Waiting up to 120s for you to complete it...');
                        try {
                            await page.waitForFunction(
                                () => !window.location.href.includes('google.com/sorry'),
                                { timeout: 120000 }
                            );
                            log('  ✅ CAPTCHA solved! Continuing...');
                            await sleep(2000);
                            // Re-navigate to the advertiser page
                            await page.goto(initUrl, { waitUntil: 'networkidle2', timeout: 45000 });
                            await sleep(3000);
                        } catch {
                            log('  ❌ CAPTCHA timeout. Skipping ads scraping.');
                            break;
                        }
                    } else {
                        if (initAttempt < 3) {
                            log(`  Waiting ${30 * initAttempt}s before retry...`);
                            await sleep(30000 * initAttempt);
                            // Clear cookies and try fresh
                            const client = await page.target().createCDPSession();
                            await client.send('Network.clearBrowserCookies');
                            await client.detach();
                            continue;
                        }
                        log('\n  ❌ Google is blocking this IP. Try again later or use --visible mode.');
                        log('  Tip: Run with --visible, solve CAPTCHA manually, then let it continue.\n');
                        break;
                    }
                }

                // Handle Google consent screen if present
                try {
                    const consentBtn = await page.$('button[aria-label="Accept all"], form[action*="consent"] button, button[jsname="higCR"]');
                    if (consentBtn) {
                        log('  Accepting Google consent...');
                        await consentBtn.click();
                        await sleep(2000);
                    }
                } catch {}

                // Verify we're on the right domain
                const verifyUrl = page.url();
                if (verifyUrl.includes('adstransparency.google.com')) {
                    sessionReady = true;
                    break;
                }

                log(`  ⚠ Not on correct page (${verifyUrl.substring(0, 60)}...)`);
                if (initAttempt < 3) {
                    log(`  Waiting ${15 * initAttempt}s before retry...`);
                    await sleep(15000 * initAttempt);
                }
            }

            if (!sessionReady) {
                log('❌ Could not establish session with Google. Skipping ads scraping.');
                log('  Run with: node cli/run.js --visible');
                log('  Then solve the CAPTCHA in the browser window.\n');
                // Skip to YouTube step
            } else {
                log('Session ready.\n');
            }

          if (sessionReady) {
            let adsSuccess = 0;
            let adsFailed = 0;
            let totalAdsScraped = 0;
            const scrapeStartTime = Date.now();
            const advTimings = []; // track time per advertiser for ETA

            for (let i = 0; i < advertisers.length; i++) {
                const adv = advertisers[i];
                const advStartTime = Date.now();
                const elapsed = formatElapsed(Date.now() - scrapeStartTime);
                const etaStr = advTimings.length > 0
                    ? `, ETA: ${formatElapsed((advTimings.reduce((a, b) => a + b, 0) / advTimings.length) * (advertisers.length - i))}`
                    : '';
                log(`--- [${i + 1}/${advertisers.length}] ${adv.name} (${adv.id}) [${elapsed} elapsed${etaStr}] ---`);

                try {
                    const payloads = await scrapeAdvertiser(page, adv.id);
                    if (payloads.length === 0) {
                        log('  No new ads to send.\n');
                        adsFailed++;
                        advTimings.push(Date.now() - advStartTime);
                        continue;
                    }
                    await sendPayloadsToServer(adv.id, adv.name, adv.region, payloads);
                    // Count ads in sent payloads
                    for (const raw of payloads) {
                        try {
                            const d = JSON.parse(raw);
                            totalAdsScraped += Array.isArray(d['1']) ? d['1'].length : 0;
                        } catch {}
                    }
                    adsSuccess++;
                } catch (err) {
                    log(`  ERROR: ${err.message}`);
                    adsFailed++;
                }

                advTimings.push(Date.now() - advStartTime);

                if (i < advertisers.length - 1) {
                    const delay = 5000 + Math.random() * 3000;
                    log(`  Waiting ${(delay / 1000).toFixed(0)}s...\n`);
                    await sleep(delay);
                }
            }

            await page.close();
            const scrapeElapsed = formatElapsed(Date.now() - scrapeStartTime);
            log(`\n✅ Ads scraped: ${adsSuccess} success, ${adsFailed} failed, ${totalAdsScraped} total ads sent (${scrapeElapsed})\n`);

            // ── STEP 3: Process on Server ───────────────
            log('━━━ Step 3: Processing ads on server ━━━\n');
            // Call with limit param to process in small chunks (avoids PHP timeout)
            let processedAny = true;
            let batchNum = 0;
            while (processedAny && batchNum < 50) {
                batchNum++;
                processedAny = await triggerProcessing(`  Batch ${batchNum}...`);
                if (processedAny) await sleep(1000);
            }
          } // end if (sessionReady)
        }

        if (!ADS_ONLY) {
            // ── STEP 4: Extract YouTube URLs from preview ─
            log('\n━━━ Step 4: Extracting YouTube URLs from video ads ━━━\n');

            const allVideoAds = await getVideoAdsFromServer();
            const needExtraction = allVideoAds.filter(a => a.ad_type === 'video' && !a.youtube_url && a.preview_url);
            log(`Found ${allVideoAds.length} video ads, ${needExtraction.length} need YouTube URL extraction`);

            // Limit extraction batch — each takes ~2s, so cap at 500 per run
            const MAX_EXTRACT = 500;
            if (needExtraction.length > MAX_EXTRACT) {
                log(`  (Processing first ${MAX_EXTRACT} — run again for more)\n`);
                needExtraction.length = MAX_EXTRACT;
            } else {
                log('');
            }

            if (needExtraction.length > 0) {
                const extractPage = await browser.newPage();

                // Track YouTube URLs from network requests (most reliable method)
                let capturedYtId = null;
                await extractPage.setRequestInterception(true);
                extractPage.on('request', (req) => {
                    const url = req.url();
                    const type = req.resourceType();

                    // Capture YouTube IDs from any network request
                    if (!capturedYtId) {
                        let m;
                        m = url.match(/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/);
                        if (m) capturedYtId = m[1];
                        m = url.match(/ytimg\.com\/vi\/([a-zA-Z0-9_-]{11})\//);
                        if (m) capturedYtId = m[1];
                        m = url.match(/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/);
                        if (m) capturedYtId = m[1];
                    }

                    // Block heavy resources but keep scripts (needed for ad rendering)
                    if (['stylesheet', 'font', 'media'].includes(type)) {
                        req.abort();
                    } else {
                        req.continue();
                    }
                });

                let extracted = 0;
                const enrichBatch = [];

                for (let i = 0; i < needExtraction.length; i++) {
                    const ad = needExtraction[i];
                    if (i % 50 === 0 && i > 0) {
                        process.stdout.write(`  [${i}/${needExtraction.length}] Extracted ${extracted} YouTube URLs so far\n`);
                        // Send enrichment batch
                        if (enrichBatch.length > 0) {
                            await sendEnrichmentsToServer(enrichBatch.splice(0));
                        }
                    }

                    try {
                        capturedYtId = null; // Reset for each ad
                        const ytId = await extractYouTubeIdFromPreview(extractPage, ad.preview_url);
                        // Use network-captured ID as primary (most reliable), fall back to DOM extraction
                        const finalId = capturedYtId || ytId;
                        if (finalId) {
                            enrichBatch.push({
                                creative_id: ad.creative_id,
                                youtube_url: `https://www.youtube.com/watch?v=${finalId}`,
                                thumbnail: `https://i.ytimg.com/vi/${finalId}/hqdefault.jpg`,
                            });
                            extracted++;
                        }
                    } catch (e) {}

                    await sleep(200); // Small delay
                }

                // Send remaining
                if (enrichBatch.length > 0) {
                    await sendEnrichmentsToServer(enrichBatch);
                }

                await extractPage.close();
                log(`  ✅ Extracted ${extracted} YouTube URLs from ${needExtraction.length} video ads\n`);
            }

            // Refresh the list now that we have YouTube URLs
            log('\n━━━ Step 5: Fetching YouTube view counts ━━━\n');

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

            /// ── STEP 6: Final Processing ────────────────
            log('\n━━━ Step 6: Final processing ━━━\n');
            let finalBatch = 0;
            let finalDidWork = true;
            while (finalDidWork && finalBatch < 20) {
                finalBatch++;
                finalDidWork = await triggerProcessing(`  Batch ${finalBatch}...`);
                if (finalDidWork) await sleep(1000);
            }
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

// ── Fetch existing creative_ids for an advertiser from server ─
async function getExistingCreativeIds(advertiserId) {
    const ids = new Set();
    let pg = 1;
    const perPage = 100;

    while (true) {
        try {
            const url = `${SERVER_URL}/dashboard/api/ads.php?token=${AUTH_TOKEN}&advertiser_id=${advertiserId}&per_page=${perPage}&page=${pg}`;
            const data = JSON.parse(await httpGet(url));
            const ads = data.ads || [];
            for (const ad of ads) {
                if (ad.creative_id) ids.add(ad.creative_id);
            }
            if (ads.length < perPage) break;
            pg++;
            if (pg > 500) break;
        } catch {
            break;
        }
    }
    return ids;
}

// ── Extract creative_ids from raw scraped payload JSON ───────
function extractCreativeIdsFromPayload(rawJson) {
    const ids = new Set();
    try {
        const data = JSON.parse(rawJson);
        const ads = data['1'] || [];
        if (Array.isArray(ads)) {
            for (const ad of ads) {
                const creativeId = ad['1'];
                if (creativeId) ids.add(creativeId);
            }
        }
    } catch {}
    return ids;
}

// ── Scrape ads for one advertiser ───────────────────────
// Uses NETWORK INTERCEPTION — navigates to advertiser page and captures
// Uses TWO strategies:
// 1. DIRECT API (fast) — page.evaluate(fetch()) from the Google domain context
// 2. PAGE NAVIGATION (fallback) — navigate + scroll + intercept responses
// The persistent Chrome profile provides real cookies, making direct API work.
async function scrapeAdvertiser(page, advertiserId) {
    const allPayloads = [];
    let totalAds = 0;

    // Strategy 1: Direct API calls (like the Python scraper / Chrome extension)
    // This works because we're on adstransparency.google.com with real cookies
    log('  Fetching ads...');
    let pageToken = null;
    let pageNum = 0;
    let directApiWorks = true;

    do {
        pageNum++;

        const reqData = {
            '2': 100,
            '3': { '12': { '1': '', '2': true }, '13': { '1': [advertiserId] } },
            '7': { '1': 1 },
        };
        if (pageToken) reqData['4'] = pageToken;

        if (pageNum > 1) await sleep(1000 + Math.random() * 500);

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
                log(`  Direct API failed (${result.error}), trying page navigation...`);
                directApiWorks = false;
            }
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
        } else {
            if (pageNum === 1) process.stdout.write(`  Page 1: 0 ads\n`);
        }

        pageToken = (data['2'] && typeof data['2'] === 'string' && data['2'] !== '') ? data['2'] : null;
    } while (pageToken !== null && directApiWorks);

    // Strategy 2: Page navigation fallback (if direct API failed)
    if (!directApiWorks) {
        const capturedResponses = [];
        let listening = true;
        const responseHandler = async (response) => {
            if (!listening) return;
            try {
                const url = response.url();
                if (url.includes('SearchService/SearchCreatives') || url.includes('SearchService/ListCreatives')) {
                    const text = await response.text().catch(() => null);
                    if (text && text.startsWith('{')) {
                        capturedResponses.push(text);
                    }
                }
            } catch {}
        };
        page.on('response', responseHandler);

        try {
            const advUrl = `${GOOGLE_BASE}/advertiser/${advertiserId}?region=anywhere`;
            await page.goto(advUrl, { waitUntil: 'networkidle2', timeout: 45000 }).catch(() => {});
            await sleep(3000);

            if (page.url().includes('google.com/sorry')) {
                log('  ❌ BLOCKED. Run with --visible to solve CAPTCHA.');
                return [];
            }

            log(`  Initial: ${capturedResponses.length} responses`);

            // Scroll to load more
            let lastCount = capturedResponses.length;
            let noNew = 0;
            for (let s = 0; s < 200 && noNew < 3; s++) {
                try { await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight)); } catch { break; }
                await sleep(1500);
                try {
                    const btn = await page.$('button[aria-label*="more"], .load-more-button, [data-load-more]');
                    if (btn) { await btn.click(); await sleep(2000); }
                } catch {}

                if (capturedResponses.length > lastCount) {
                    process.stdout.write(`  Scroll ${s + 1}: +${capturedResponses.length - lastCount} (total: ${capturedResponses.length})\n`);
                    lastCount = capturedResponses.length;
                    noNew = 0;
                } else {
                    noNew++;
                }
            }

            for (const raw of capturedResponses) {
                try {
                    const data = JSON.parse(raw);
                    const ads = data['1'] || [];
                    totalAds += Array.isArray(ads) ? ads.length : 0;
                    allPayloads.push(raw);
                } catch {}
            }
        } finally {
            listening = false;
            page.off('response', responseHandler);
        }
    }

    log(`  Total: ${allPayloads.length} pages, ${totalAds} ads`);
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

// ── Send YouTube data to server (using youtube_update.php) ──
async function sendYouTubeToServer(batch) {
    process.stdout.write(`  → Sending ${batch.length} YouTube results... `);
    try {
        // Use ingest.php update_youtube action which properly updates ads.view_count,
        // ad_details (headline/description), and youtube_metadata
        const videos = batch.map(v => ({
            creative_id: v.creative_id,
            video_id: v.video_id,
            title: v.title,
            author: v.author,
            view_count: v.view_count,
            thumbnail: v.video_id ? `https://i.ytimg.com/vi/${v.video_id}/hqdefault.jpg` : null,
        }));
        const url = `${SERVER_URL}/dashboard/api/ingest.php?action=update_youtube&token=${AUTH_TOKEN}`;
        const resp = JSON.parse(await httpPost(url, { videos }));
        console.log(`OK (${resp.updated || 0} updated)`);
    } catch (err) {
        console.log(`FAILED: ${err.message}`);
    }
}

// ── Extract YouTube ID from Google preview URL ──────────
async function extractYouTubeIdFromPreview(page, previewUrl) {
    try {
        // Navigate to the preview URL — it's JavaScript that renders an ad
        await page.goto(previewUrl, {
            waitUntil: 'domcontentloaded',
            timeout: 8000,
        });
        await sleep(2000); // Wait for JS to render + safeframe to load

        // Strategy 1: Check ALL frames (including cross-origin safeframes)
        // Puppeteer can access cross-origin iframe content via page.frames()
        const frames = page.frames();
        for (const frame of frames) {
            try {
                const url = frame.url() || '';
                // Check frame URL itself for YouTube embed
                let m = url.match(/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/);
                if (m) return m[1];

                // Check frame content for YouTube references
                const ytId = await frame.evaluate(() => {
                    const html = document.documentElement?.innerHTML || '';
                    let m;
                    // YouTube embed iframes
                    m = html.match(/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/);
                    if (m) return m[1];
                    // YouTube thumbnail images
                    m = html.match(/ytimg\.com\/vi\/([a-zA-Z0-9_-]{11})\//);
                    if (m) return m[1];
                    // YouTube watch URL
                    m = html.match(/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/);
                    if (m) return m[1];
                    // Video ID in data attributes or JS variables
                    m = html.match(/video[_-]?[Ii]d['":\s]+['"]([a-zA-Z0-9_-]{11})['"]/);
                    if (m) return m[1];
                    return null;
                }).catch(() => null);
                if (ytId) return ytId;
            } catch {}
        }

        // Strategy 2: Intercept network requests — YouTube embeds trigger requests
        // Check main page content as fallback
        const videoId = await page.evaluate(() => {
            const html = document.documentElement.innerHTML;
            let m;
            m = html.match(/ytimg\.com\/vi\/([a-zA-Z0-9_-]{11})\//);
            if (m) return m[1];
            m = html.match(/youtube\.com\/(?:embed\/|watch\?v=|v\/)([a-zA-Z0-9_-]{11})/);
            if (m) return m[1];
            m = html.match(/video[_-]?[Ii]d['":\s]+['"]([a-zA-Z0-9_-]{11})['"]/);
            if (m) return m[1];
            return null;
        });

        return videoId;
    } catch { return null; }
}

// ── Send YouTube URL enrichments to server ──────────────
async function sendEnrichmentsToServer(batch) {
    try {
        const url = `${SERVER_URL}/dashboard/api/ingest.php?action=enrich_ads&token=${AUTH_TOKEN}`;
        await httpPost(url, { enrichments: batch });
    } catch (err) {
        log(`  ⚠ Enrichment send failed: ${err.message}`);
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
        // Add limit=5 to process only 5 payloads per call (avoids PHP timeout)
        const url = `${SERVER_URL}/cron/process.php?token=${AUTH_TOKEN}&limit=5`;
        const result = JSON.parse(await httpGet(url, 120000)); // 2 min timeout
        const parts = [];
        let didWork = false;
        if (result.processed > 0) { parts.push(`${result.processed} ads`); didWork = true; }
        if (result.text_enriched > 0) { parts.push(`${result.text_enriched} text`); didWork = true; }
        if (result.youtube > 0) { parts.push(`${result.youtube} YT`); didWork = true; }
        if (result.countries_enriched > 0) { parts.push(`${result.countries_enriched} countries`); didWork = true; }
        if (result.products_mapped > 0) { parts.push(`${result.products_mapped} products`); didWork = true; }
        console.log(parts.length > 0 ? `✅ ${parts.join(', ')}` : '✅ Up to date');
        return didWork;
    } catch (err) {
        console.log(`⚠️ ${err.message}`);
        return false;
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

function formatElapsed(ms) {
    const totalSec = Math.round(ms / 1000);
    if (totalSec < 60) return `${totalSec}s`;
    const min = Math.floor(totalSec / 60);
    const sec = totalSec % 60;
    if (min < 60) return `${min}m ${sec}s`;
    const hr = Math.floor(min / 60);
    const remMin = min % 60;
    return `${hr}h ${remMin}m`;
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
