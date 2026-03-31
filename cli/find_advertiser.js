#!/usr/bin/env node
/**
 * ╔══════════════════════════════════════════════════╗
 * ║  Find Advertiser from App URL                    ║
 * ╚══════════════════════════════════════════════════╝
 *
 * Give it a Play Store / App Store URL → it finds the advertiser
 * running ads for that app on Google Ads Transparency Center.
 *
 * Usage:
 *   node cli/find_advertiser.js "https://play.google.com/store/apps/details?id=com.example.app"
 *   node cli/find_advertiser.js "https://apps.apple.com/app/id123456789"
 *   node cli/find_advertiser.js "com.example.app"          # Package name directly
 *   node cli/find_advertiser.js --add "URL_OR_PACKAGE"     # Auto-add to advertisers.txt
 *   node cli/find_advertiser.js --visible "URL_OR_PACKAGE"  # Show browser
 */

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const fs = require('fs');
const path = require('path');

puppeteer.use(StealthPlugin());

const GOOGLE_BASE = 'https://adstransparency.google.com';
const ADVERTISERS_FILE = path.join(__dirname, '..', 'advertisers.txt');

// ── Parse Args ──────────────────────────────────────────
const args = process.argv.slice(2);
const VISIBLE = args.includes('--visible');
const AUTO_ADD = args.includes('--add');
const input = args.filter(a => !a.startsWith('--'))[0];

if (!input) {
    console.log(`
Usage:
  node cli/find_advertiser.js <app_url_or_package_name>
  node cli/find_advertiser.js --add <app_url_or_package_name>    # Auto-add to advertisers.txt
  node cli/find_advertiser.js --visible <app_url_or_package_name> # Show browser

Examples:
  node cli/find_advertiser.js "https://play.google.com/store/apps/details?id=com.whatsapp"
  node cli/find_advertiser.js "com.whatsapp"
  node cli/find_advertiser.js --add "https://apps.apple.com/app/id310633997"
`);
    process.exit(0);
}

// ── Main ────────────────────────────────────────────────
(async () => {
    // Step 1: Extract package/app identifier from URL
    const appInfo = parseAppInput(input);
    console.log(`\n🔍 Looking up: ${appInfo.display}`);
    console.log(`   Package/ID: ${appInfo.id}`);
    console.log(`   Platform: ${appInfo.platform}\n`);

    // Step 2: Get the app name from store (for better search)
    let appName = null;
    try {
        appName = await fetchAppName(appInfo);
        if (appName) console.log(`   App Name: ${appName}\n`);
    } catch {}

    // Step 3: Launch Puppeteer and search Google Ads Transparency
    console.log('🚀 Launching Chrome...');
    const browser = await puppeteer.launch({
        headless: VISIBLE ? false : 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-blink-features=AutomationControlled',
            '--window-size=1440,900',
        ],
        defaultViewport: { width: 1440, height: 900 },
    });

    try {
        const page = await browser.newPage();
        await page.setExtraHTTPHeaders({ 'Accept-Language': 'en-US,en;q=0.9' });

        // Navigate to Google Ads Transparency Center
        console.log('📡 Opening Google Ads Transparency Center...');
        await page.goto(`${GOOGLE_BASE}/?region=anywhere`, {
            waitUntil: 'networkidle2',
            timeout: 45000,
        });
        await sleep(3000);

        // Check for CAPTCHA
        if (page.url().includes('google.com/sorry')) {
            if (VISIBLE) {
                console.log('  👉 Please solve the CAPTCHA in the browser window...');
                try {
                    await page.waitForFunction(
                        () => !window.location.href.includes('google.com/sorry'),
                        { timeout: 120000 }
                    );
                    console.log('  ✅ CAPTCHA solved!');
                    await page.goto(`${GOOGLE_BASE}/?region=anywhere`, {
                        waitUntil: 'networkidle2',
                        timeout: 45000,
                    });
                    await sleep(3000);
                } catch {
                    console.log('  ❌ CAPTCHA timeout.');
                    return;
                }
            } else {
                console.log('  ❌ Google is blocking. Run with --visible to solve CAPTCHA.');
                return;
            }
        }

        // Set up response interception to capture search results
        const capturedCreatives = [];
        page.on('response', async (response) => {
            const url = response.url();
            if (url.includes('SearchService/SearchCreatives') || url.includes('SearchService/ListCreatives')) {
                try {
                    const text = await response.text();
                    if (text && text.startsWith('{')) {
                        const data = JSON.parse(text);
                        const ads = data['1'] || [];
                        if (Array.isArray(ads)) {
                            capturedCreatives.push(...ads);
                        }
                    }
                } catch {}
            }
        });

        // Search using app name or package name
        const searchTerms = [appName, appInfo.id, appInfo.id.split('.').pop()].filter(Boolean);
        const allAdvertisers = new Map(); // advertiser_id -> { name, adCount, hasApp }

        for (const searchTerm of searchTerms) {
            console.log(`🔎 Searching: "${searchTerm}"...`);
            capturedCreatives.length = 0; // Reset

            // Type in the search box
            try {
                // Click on search input and clear it
                const searchInput = await page.$('input[type="text"], input[aria-label*="Search"], input[placeholder*="Search"]');
                if (searchInput) {
                    await searchInput.click({ clickCount: 3 }); // Select all
                    await searchInput.press('Backspace');
                    await sleep(500);
                    await searchInput.type(searchTerm, { delay: 50 });
                    await sleep(500);
                    await searchInput.press('Enter');
                    await sleep(4000); // Wait for results
                } else {
                    // Direct navigation as fallback
                    await page.goto(`${GOOGLE_BASE}/?region=anywhere&query=${encodeURIComponent(searchTerm)}`, {
                        waitUntil: 'networkidle2',
                        timeout: 30000,
                    });
                    await sleep(3000);
                }
            } catch (err) {
                console.log(`  ⚠ Search failed: ${err.message}`);
                continue;
            }

            // Scroll a bit to load more results
            for (let s = 0; s < 3; s++) {
                await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
                await sleep(1500);
            }

            // Process captured creatives
            console.log(`  Found ${capturedCreatives.length} ads`);

            for (const creative of capturedCreatives) {
                const advId = extractField(creative, '1');
                const advName = extractField(creative, '12');
                if (!advId) continue;

                // Check if this ad links to our target app
                const hasApp = checkCreativeForApp(creative, appInfo);

                if (allAdvertisers.has(advId)) {
                    const existing = allAdvertisers.get(advId);
                    existing.adCount++;
                    if (hasApp) existing.hasApp = true;
                } else {
                    allAdvertisers.set(advId, {
                        id: advId,
                        name: advName || 'Unknown',
                        adCount: 1,
                        hasApp: hasApp,
                    });
                }
            }
        }

        // ── Results ─────────────────────────────────────────
        if (allAdvertisers.size === 0) {
            console.log('\n❌ No advertisers found for this app.');
            console.log('   Try running with --visible to check manually.');
            return;
        }

        // Sort: advertisers with matching app first, then by ad count
        const results = Array.from(allAdvertisers.values())
            .sort((a, b) => {
                if (a.hasApp !== b.hasApp) return b.hasApp - a.hasApp;
                return b.adCount - a.adCount;
            });

        console.log('\n╔══════════════════════════════════════════════════╗');
        console.log('║  Found Advertisers                               ║');
        console.log('╚══════════════════════════════════════════════════╝\n');

        const directMatches = results.filter(r => r.hasApp);
        const otherMatches = results.filter(r => !r.hasApp);

        if (directMatches.length > 0) {
            console.log('🎯 Direct matches (ads link to this app):');
            for (const adv of directMatches) {
                console.log(`   ${adv.id} | ${adv.name} (${adv.adCount} ads)`);
            }
        }

        if (otherMatches.length > 0 && otherMatches.length <= 10) {
            console.log('\n📋 Other advertisers in search results:');
            for (const adv of otherMatches.slice(0, 10)) {
                console.log(`   ${adv.id} | ${adv.name} (${adv.adCount} ads)`);
            }
        }

        // Auto-add to advertisers.txt
        const toAdd = directMatches.length > 0 ? directMatches : results.slice(0, 1);

        if (AUTO_ADD && toAdd.length > 0) {
            console.log('\n📝 Adding to advertisers.txt:');
            const existing = fs.existsSync(ADVERTISERS_FILE) ? fs.readFileSync(ADVERTISERS_FILE, 'utf8') : '';
            let added = 0;

            for (const adv of toAdd) {
                if (existing.includes(adv.id)) {
                    console.log(`   ⏭ ${adv.name} — already exists`);
                } else {
                    const line = `${adv.id} | ${adv.name}\n`;
                    fs.appendFileSync(ADVERTISERS_FILE, line);
                    console.log(`   ✅ ${adv.id} | ${adv.name}`);
                    added++;
                }
            }

            if (added > 0) {
                console.log(`\n✅ Added ${added} advertiser(s). Run: node cli/run.js --visible`);
            }
        } else if (!AUTO_ADD && toAdd.length > 0) {
            console.log('\n💡 To auto-add to advertisers.txt, run:');
            console.log(`   node cli/find_advertiser.js --add "${input}"`);
        }

        // Print copy-paste line for manual add
        if (toAdd.length > 0) {
            console.log('\n📋 Copy-paste for advertisers.txt:');
            for (const adv of toAdd) {
                console.log(`${adv.id} | ${adv.name}`);
            }
        }

    } catch (err) {
        console.error(`\n❌ Error: ${err.message}`);
    } finally {
        await browser.close();
    }

    console.log('');
})();


// ═══════════════════════════════════════════════════════
// Helper Functions
// ═══════════════════════════════════════════════════════

function parseAppInput(input) {
    // Play Store URL
    let m = input.match(/play\.google\.com.*?[?&]id=([a-zA-Z0-9._]+)/);
    if (m) return { id: m[1], platform: 'android', display: m[1] };

    // App Store URL with numeric ID
    m = input.match(/apps\.apple\.com.*?\/id(\d+)/);
    if (m) return { id: m[1], platform: 'ios', display: `App Store ID: ${m[1]}` };

    // App Store URL with bundle name
    m = input.match(/apps\.apple\.com.*?\/app\/([^\/\?]+)/);
    if (m) return { id: m[1], platform: 'ios', display: m[1] };

    // Raw package name (com.example.app)
    if (input.match(/^[a-zA-Z][a-zA-Z0-9._]+$/)) {
        return { id: input, platform: 'android', display: input };
    }

    // Numeric ID (iOS)
    if (input.match(/^\d+$/)) {
        return { id: input, platform: 'ios', display: `App Store ID: ${input}` };
    }

    // Generic URL — extract domain
    try {
        const url = new URL(input);
        return { id: url.hostname, platform: 'web', display: url.hostname };
    } catch {}

    return { id: input, platform: 'unknown', display: input };
}

async function fetchAppName(appInfo) {
    const https = require('https');

    return new Promise((resolve) => {
        let url;
        if (appInfo.platform === 'android') {
            // Use Google Play Store page title
            url = `https://play.google.com/store/apps/details?id=${appInfo.id}&hl=en`;
        } else if (appInfo.platform === 'ios') {
            url = `https://itunes.apple.com/lookup?id=${appInfo.id}`;
        } else {
            resolve(null);
            return;
        }

        const req = https.get(url, { timeout: 10000 }, (res) => {
            let data = '';
            res.on('data', c => data += c);
            res.on('end', () => {
                if (appInfo.platform === 'ios') {
                    try {
                        const json = JSON.parse(data);
                        if (json.results && json.results[0]) {
                            resolve(json.results[0].trackName);
                            return;
                        }
                    } catch {}
                }
                // Try to extract title from HTML
                const m = data.match(/<title[^>]*>([^<]+)</);
                if (m) {
                    let title = m[1].replace(/\s*-\s*Apps on Google Play.*/, '').replace(/\s*on the App Store.*/, '').trim();
                    if (title && title.length < 100) {
                        resolve(title);
                        return;
                    }
                }
                resolve(null);
            });
        });
        req.on('error', () => resolve(null));
        req.on('timeout', () => { req.destroy(); resolve(null); });
    });
}

function extractField(obj, ...keys) {
    let current = obj;
    for (const key of keys) {
        if (current == null || typeof current !== 'object') return null;
        current = current[key];
    }
    return typeof current === 'string' ? current : null;
}

function checkCreativeForApp(creative, appInfo) {
    // Deep search the creative JSON for matching app identifiers
    const jsonStr = JSON.stringify(creative);
    const searchId = appInfo.id.toLowerCase();

    // Check for package name or app ID in the entire creative JSON
    if (jsonStr.toLowerCase().includes(searchId)) return true;

    // Check store URLs in the content
    if (appInfo.platform === 'android') {
        if (jsonStr.includes(`id=${appInfo.id}`)) return true;
        if (jsonStr.includes(appInfo.id)) return true;
    } else if (appInfo.platform === 'ios') {
        if (jsonStr.includes(`id${appInfo.id}`)) return true;
    }

    return false;
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
