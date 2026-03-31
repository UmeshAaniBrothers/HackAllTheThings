#!/usr/bin/env node
/**
 * YouTube Metadata Scraper — Uses real Chrome browser via Puppeteer
 *
 * Fetches view counts, titles, and channel names for all video ads.
 * No more 429 errors from YouTube.
 *
 * Usage:
 *   node cli/youtube.js                  # Fetch all pending videos
 *   node cli/youtube.js --refresh        # Also refresh stale (>15 days)
 *   node cli/youtube.js --visible        # Show browser window (debug)
 */

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const https = require('https');

puppeteer.use(StealthPlugin());

// ── Config ──────────────────────────────────────────────
const SERVER_URL = 'https://phpstack-1170423-6314737.cloudwaysapps.com';
const AUTH_TOKEN = 'ads-intelligent-2024';
const BATCH_SIZE = 50;          // Videos per server API call
const DELAY_BETWEEN_VIDEOS = 800; // 800ms between YouTube page loads
const MAX_CONSECUTIVE_FAILS = 10;

// ── Main ────────────────────────────────────────────────
(async () => {
    const args = process.argv.slice(2);
    const visible = args.includes('--visible');
    const refresh = args.includes('--refresh');

    console.log('=== YouTube Metadata Scraper (Puppeteer) ===');
    console.log(`Mode: ${refresh ? 'Fetch + Refresh stale' : 'Fetch new only'}`);
    console.log(`Started: ${new Date().toLocaleString()}\n`);

    // Step 1: Get list of videos that need fetching from server
    console.log('Getting video list from server...');
    const videos = await getVideosToFetch(refresh);

    if (!videos || videos.length === 0) {
        console.log('No videos to fetch. All up to date!');
        return;
    }

    console.log(`Found ${videos.length} videos to process\n`);

    // Step 2: Launch browser
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

    // Block unnecessary resources for faster loading
    await page.setRequestInterception(true);
    page.on('request', (req) => {
        const type = req.resourceType();
        if (['image', 'stylesheet', 'font', 'media'].includes(type)) {
            req.abort();
        } else {
            req.continue();
        }
    });

    try {
        // Visit YouTube first to establish session
        console.log('Initializing YouTube session...');
        await page.goto('https://www.youtube.com', {
            waitUntil: 'domcontentloaded',
            timeout: 20000,
        });
        await sleep(2000);

        // Handle consent dialog if present
        try {
            const consentBtn = await page.$('button[aria-label*="Accept"], button[aria-label*="Reject"], tp-yt-paper-button.ytd-consent-bump-v2-lightbox');
            if (consentBtn) {
                await consentBtn.click();
                await sleep(1000);
            }
        } catch (e) { /* no consent dialog */ }

        console.log('Session ready.\n');

        let totalFetched = 0;
        let totalFailed = 0;
        let consecutiveFails = 0;
        let batch = [];

        for (let i = 0; i < videos.length; i++) {
            const video = videos[i];
            const num = i + 1;

            process.stdout.write(`[${num}/${videos.length}] ${video.video_id}... `);

            try {
                const meta = await fetchVideoMetadata(page, video.video_id);

                if (meta) {
                    console.log(`✓ ${formatViews(meta.view_count)} views | ${(meta.title || '').substring(0, 40)}`);
                    batch.push({
                        creative_id: video.creative_id,
                        video_id: video.video_id,
                        title: meta.title,
                        author: meta.author,
                        view_count: meta.view_count,
                        thumbnail: meta.thumbnail,
                        status: 'ok',
                    });
                    totalFetched++;
                    consecutiveFails = 0;
                } else {
                    console.log('✗ Failed (private/deleted?)');
                    batch.push({
                        creative_id: video.creative_id,
                        video_id: video.video_id,
                        status: 'failed',
                    });
                    totalFailed++;
                    consecutiveFails++;
                }
            } catch (err) {
                console.log(`✗ Error: ${err.message}`);
                batch.push({
                    creative_id: video.creative_id,
                    video_id: video.video_id,
                    status: 'failed',
                });
                totalFailed++;
                consecutiveFails++;
            }

            // Send batch to server every BATCH_SIZE videos
            if (batch.length >= BATCH_SIZE || i === videos.length - 1) {
                if (batch.length > 0) {
                    process.stdout.write(`\n  → Sending ${batch.length} results to server... `);
                    try {
                        const result = await sendToServer(batch);
                        console.log(`OK (${result.updated || 0} updated)\n`);
                    } catch (err) {
                        console.log(`FAILED: ${err.message}\n`);
                    }
                    batch = [];
                }
            }

            // Stop if too many consecutive failures (likely blocked)
            if (consecutiveFails >= MAX_CONSECUTIVE_FAILS) {
                console.log(`\n⚠ Stopped: ${MAX_CONSECUTIVE_FAILS} consecutive failures. YouTube may be rate limiting.`);
                console.log('Try again later or use --visible flag to debug.\n');
                break;
            }

            // Delay between videos
            if (i < videos.length - 1) {
                await sleep(DELAY_BETWEEN_VIDEOS + Math.random() * 500);
            }
        }

        console.log('\n=== Complete ===');
        console.log(`Fetched: ${totalFetched}, Failed: ${totalFailed}`);
        console.log(`Finished: ${new Date().toLocaleString()}`);

    } catch (err) {
        console.error('Fatal error:', err.message);
    } finally {
        await browser.close();
    }
})();

// ── Fetch metadata for a single video ───────────────────
async function fetchVideoMetadata(page, videoId) {
    try {
        // Navigate to the video page
        await page.goto(`https://www.youtube.com/watch?v=${videoId}`, {
            waitUntil: 'domcontentloaded',
            timeout: 15000,
        });

        // Wait a bit for dynamic content
        await sleep(1500);

        // Extract metadata from the page using JavaScript
        const meta = await page.evaluate(() => {
            let title = null;
            let author = null;
            let viewCount = null;
            let thumbnail = null;

            // Title - multiple sources
            const titleEl = document.querySelector('h1.ytd-watch-metadata yt-formatted-string, h1.title yt-formatted-string, meta[name="title"]');
            if (titleEl) {
                title = titleEl.textContent?.trim() || titleEl.content?.trim();
            }
            if (!title) {
                title = document.title?.replace(' - YouTube', '').trim() || null;
            }

            // Channel name
            const channelEl = document.querySelector('#owner #channel-name a, ytd-channel-name a, #upload-info a');
            if (channelEl) {
                author = channelEl.textContent?.trim();
            }

            // View count from page source (embedded in initial data)
            const scripts = document.querySelectorAll('script');
            for (const script of scripts) {
                const text = script.textContent;
                if (!text) continue;

                // Look for viewCount in ytInitialData or ytInitialPlayerResponse
                const match = text.match(/"viewCount"\s*:\s*"(\d+)"/);
                if (match) {
                    viewCount = parseInt(match[1], 10);
                    break;
                }
            }

            // Fallback: try meta tag
            if (viewCount === null) {
                const interactionCount = document.querySelector('meta[itemprop="interactionCount"]');
                if (interactionCount) {
                    viewCount = parseInt(interactionCount.content, 10);
                }
            }

            // Thumbnail
            const ogImage = document.querySelector('meta[property="og:image"]');
            if (ogImage) {
                thumbnail = ogImage.content;
            }

            return { title, author, viewCount, thumbnail };
        });

        if (!meta || (!meta.title && meta.viewCount === null)) {
            return null;
        }

        return {
            title: meta.title,
            author: meta.author,
            view_count: meta.viewCount,
            thumbnail: meta.thumbnail || `https://i.ytimg.com/vi/${videoId}/hqdefault.jpg`,
        };

    } catch (err) {
        return null;
    }
}

// ── Get videos from server that need fetching ───────────
async function getVideosToFetch(includeStale) {
    const action = includeStale ? 'youtube_pending&include_stale=1' : 'youtube_pending';
    const url = `${SERVER_URL}/dashboard/api/youtube_list.php?token=${encodeURIComponent(AUTH_TOKEN)}&action=${action}`;

    try {
        const data = await httpGet(url);
        const result = JSON.parse(data);
        return result.videos || [];
    } catch (err) {
        console.error('Failed to get video list:', err.message);
        return [];
    }
}

// ── Send results to server ──────────────────────────────
async function sendToServer(batch) {
    const url = `${SERVER_URL}/dashboard/api/youtube_update.php?token=${encodeURIComponent(AUTH_TOKEN)}`;
    const data = await httpPost(url, { videos: batch });
    return JSON.parse(data);
}

// ── HTTP helpers ────────────────────────────────────────
function httpPost(url, data) {
    return new Promise((resolve, reject) => {
        const body = JSON.stringify(data);
        const urlObj = new URL(url);

        const req = https.request(urlObj, {
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

function formatViews(count) {
    if (count === null || count === undefined) return '0';
    if (count >= 10000000) return (count / 10000000).toFixed(1) + 'Cr';
    if (count >= 100000) return (count / 100000).toFixed(1) + 'L';
    if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
    return count.toString();
}
