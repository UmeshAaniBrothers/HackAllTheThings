#!/usr/bin/env node
/**
 * ╔══════════════════════════════════════════════════════════╗
 * ║  Ads Intelligence — Enterprise Orchestrator              ║
 * ║  Parallel scraping with proxy rotation                   ║
 * ╚══════════════════════════════════════════════════════════╝
 *
 * Manages multiple scraping workers in parallel, each with its
 * own proxy and Chrome session. Handles 1000+ advertisers and
 * 1M+ ads without rate limiting or CAPTCHA issues.
 *
 * Usage:
 *   node cli/orchestrator.js                  # Full pipeline
 *   node cli/orchestrator.js --scrape-only    # Only scrape ads
 *   node cli/orchestrator.js --process-only   # Only server processing
 *   node cli/orchestrator.js --visible        # Show browsers (debug)
 *
 * Setup:
 *   1. Get residential proxies (smartproxy.com — free trial available)
 *   2. Edit cli/proxy-config.json with your proxy credentials
 *   3. Run: node cli/orchestrator.js
 *
 * Without proxies:
 *   Falls back to single-worker mode with your local IP.
 *   Works fine for <50 advertisers. For 1000+, proxies are required.
 */

const { fork } = require('child_process');
const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');

const SERVER_URL = 'https://phpstack-1170423-6314737.cloudwaysapps.com';
const AUTH_TOKEN = 'ads-intelligent-2024';
const PROJECT_DIR = path.join(__dirname, '..');
const ADVERTISERS_FILE = path.join(PROJECT_DIR, 'advertisers.txt');
const PROXY_CONFIG_FILE = path.join(__dirname, 'proxy-config.json');
const STATE_FILE = path.join(PROJECT_DIR, '.scrape-state.json');

const args = process.argv.slice(2);
const VISIBLE = args.includes('--visible');
const SCRAPE_ONLY = args.includes('--scrape-only');
const PROCESS_ONLY = args.includes('--process-only');

// ── Main ────────────────────────────────────────────────
(async () => {
    const startTime = Date.now();
    log('╔══════════════════════════════════════════════════════════╗');
    log('║   Ads Intelligence — Enterprise Pipeline                 ║');
    log('╚══════════════════════════════════════════════════════════╝');
    log(`Started: ${new Date().toLocaleString()}\n`);

    // Load config
    const proxyConfig = loadProxyConfig();
    const advertisers = readAdvertisers();
    if (advertisers.length === 0) {
        log('❌ No advertisers found. Add them to advertisers.txt');
        return;
    }
    log(`📋 ${advertisers.length} advertisers loaded`);

    if (proxyConfig.enabled) {
        log(`🌐 Proxies: ${proxyConfig.proxies.length} endpoints, ${proxyConfig.workers} workers`);
    } else {
        log(`⚠️  No proxies configured — using single worker (local IP)`);
        log(`   For 1000+ advertisers, set up proxies in cli/proxy-config.json\n`);
    }

    // ── Step 1: Sync advertisers ────────────────────────
    if (!PROCESS_ONLY) {
        log('\n━━━ Step 1: Syncing advertisers to server ━━━\n');
        await syncAdvertisers(advertisers);

        // ── Step 1.5: Auto-deploy ───────────────────────
        log('\n━━━ Deploying latest code to server ━━━\n');
        try {
            const deployUrl = `${SERVER_URL}/dashboard/api/deploy.php?token=${AUTH_TOKEN}`;
            const result = JSON.parse(await httpGet(deployUrl, 15000));
            log(result.success ? '  ✅ Server updated' : `  ⚠️ Deploy: ${result.output || 'unknown'}`);
        } catch (e) {
            log(`  ⚠️ Deploy skipped: ${e.message}`);
        }

        // ── Step 2: Scrape ads ──────────────────────────
        log('\n━━━ Step 2: Scraping ads (parallel workers) ━━━\n');
        await scrapeWithWorkers(advertisers, proxyConfig);
    }

    // ── Step 3: Server processing ───────────────────────
    if (!SCRAPE_ONLY) {
        log('\n━━━ Step 3: Processing on server ━━━\n');
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

    // ── Summary ─────────────────────────────────────────
    const elapsed = ((Date.now() - startTime) / 1000 / 60).toFixed(1);
    log('\n╔══════════════════════════════════════════════════════════╗');
    log('║   ✅ Pipeline Complete                                    ║');
    log('╚══════════════════════════════════════════════════════════╝');
    log(`⏱  Time: ${elapsed} minutes`);
    log(`🌐 Dashboard: ${SERVER_URL}/dashboard/`);
    log(`Finished: ${new Date().toLocaleString()}`);
})();


// ═══════════════════════════════════════════════════════
// Worker Management
// ═══════════════════════════════════════════════════════

async function scrapeWithWorkers(advertisers, config) {
    const numWorkers = config.enabled ? Math.min(config.workers, config.proxies.length, advertisers.length) : 1;
    const chunks = splitArray(advertisers, numWorkers);

    log(`  Splitting ${advertisers.length} advertisers across ${numWorkers} worker(s)\n`);

    const workerPromises = [];

    for (let i = 0; i < numWorkers; i++) {
        const proxy = config.enabled ? config.proxies[i % config.proxies.length] : null;
        const chunk = chunks[i];
        if (!chunk || chunk.length === 0) continue;

        const proxyLabel = proxy ? proxy.replace(/\/\/.*@/, '//***@') : 'direct';
        log(`  Worker ${i + 1}: ${chunk.length} advertisers via ${proxyLabel}`);

        workerPromises.push(runWorker(i + 1, proxy, chunk));
    }

    const results = await Promise.allSettled(workerPromises);

    // Collect results
    let totalAds = 0;
    let totalSuccess = 0;
    let totalFailed = 0;
    let blocked = [];

    for (let i = 0; i < results.length; i++) {
        const r = results[i];
        if (r.status === 'fulfilled' && r.value) {
            totalAds += r.value.total_ads || 0;
            totalSuccess += r.value.success || 0;
            totalFailed += r.value.failed || 0;
            if (r.value.blocked_advertisers) {
                blocked.push(...r.value.blocked_advertisers);
            }
        } else if (r.status === 'rejected') {
            log(`  Worker ${i + 1} crashed: ${r.reason?.message || r.reason}`);
        }
    }

    log(`\n  ✅ Scraping complete: ${totalSuccess} success, ${totalFailed} failed, ${totalAds} total ads`);

    // Retry blocked advertisers with different proxies
    if (blocked.length > 0 && config.enabled && config.retry_on_block) {
        log(`\n  🔄 Retrying ${blocked.length} blocked advertisers with fresh proxies...\n`);
        // Use different proxies for retry
        const retryProxies = config.proxies.slice(numWorkers);
        if (retryProxies.length > 0) {
            const retryChunks = splitArray(blocked, Math.min(retryProxies.length, blocked.length));
            const retryPromises = [];
            for (let i = 0; i < retryChunks.length; i++) {
                retryPromises.push(runWorker(100 + i, retryProxies[i % retryProxies.length], retryChunks[i]));
            }
            await Promise.allSettled(retryPromises);
        }
    }
}

function runWorker(id, proxy, advertisers) {
    return new Promise((resolve, reject) => {
        const workerPath = path.join(__dirname, 'worker.js');
        const workerArgs = [
            '--id', String(id),
            '--advertisers', JSON.stringify(advertisers),
        ];
        if (proxy) workerArgs.push('--proxy', proxy);
        if (VISIBLE) workerArgs.push('--visible');

        const child = fork(workerPath, workerArgs, {
            stdio: ['pipe', 'pipe', 'pipe', 'ipc'],
            env: { ...process.env, NODE_OPTIONS: '' },
        });

        let result = null;
        let blockedAdvertisers = [];

        child.stdout.on('data', (data) => {
            const lines = data.toString().split('\n').filter(Boolean);
            for (const line of lines) {
                try {
                    const msg = JSON.parse(line);
                    if (msg.type === 'done') {
                        result = msg;
                    } else if (msg.type === 'blocked') {
                        // Collect remaining advertisers for retry
                        const remaining = advertisers.slice(advertisers.length - (msg.advertisers_remaining || 0));
                        blockedAdvertisers = remaining;
                    } else if (msg.type === 'progress') {
                        process.stdout.write(`  [W${id}] ${msg.advertiser}: ${msg.ads} ads (${msg.done}/${msg.total})\n`);
                    } else if (msg.msg) {
                        // Regular log
                        process.stdout.write(`  [W${id}] ${msg.msg}\n`);
                    }
                } catch {
                    // Non-JSON output — print as-is
                    process.stdout.write(`  [W${id}] ${line}\n`);
                }
            }
        });

        child.stderr.on('data', (data) => {
            // Ignore noisy Puppeteer warnings
            const text = data.toString();
            if (!text.includes('DevTools') && !text.includes('ExperimentalWarning')) {
                process.stderr.write(`  [W${id}] ${text}`);
            }
        });

        child.on('exit', (code) => {
            if (result) {
                result.blocked_advertisers = blockedAdvertisers;
                resolve(result);
            } else if (code !== 0) {
                reject(new Error(`Worker ${id} exited with code ${code}`));
            } else {
                resolve({ success: 0, failed: 0, total_ads: 0, blocked_advertisers: blockedAdvertisers });
            }
        });

        child.on('error', reject);
    });
}


// ═══════════════════════════════════════════════════════
// Helper Functions
// ═══════════════════════════════════════════════════════

function log(msg) { console.log(msg); }

function loadProxyConfig() {
    try {
        if (fs.existsSync(PROXY_CONFIG_FILE)) {
            const raw = fs.readFileSync(PROXY_CONFIG_FILE, 'utf8');
            // Strip comment keys
            const cleaned = raw.replace(/^\s*"\/\/.*$/gm, '');
            const config = JSON.parse(raw);
            return {
                enabled: config.enabled === true && Array.isArray(config.proxies) && config.proxies.length > 0,
                proxies: (config.proxies || []).filter(p => !p.startsWith('//')),
                workers: config.workers || 4,
                retry_on_block: config.retry_on_block !== false,
            };
        }
    } catch (e) {
        log(`⚠️ Error loading proxy config: ${e.message}`);
    }
    return { enabled: false, proxies: [], workers: 1, retry_on_block: false };
}

function readAdvertisers() {
    if (!fs.existsSync(ADVERTISERS_FILE)) return [];
    const lines = fs.readFileSync(ADVERTISERS_FILE, 'utf8').split('\n');
    const advertisers = [];
    for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) continue;
        const parts = trimmed.split('|').map(p => p.trim());
        if (parts[0]) advertisers.push({ id: parts[0], name: parts[1] || parts[0] });
    }
    return advertisers;
}

async function syncAdvertisers(advertisers) {
    for (const adv of advertisers) {
        process.stdout.write(`  ${adv.name}... `);
        try {
            const url = `${SERVER_URL}/dashboard/api/manage.php?action=add_advertiser&token=${AUTH_TOKEN}`;
            await httpPost(url, { advertiser_id: adv.id, advertiser_name: adv.name });
            console.log('✓');
        } catch (err) {
            console.log(`✗ ${err.message}`);
        }
    }
}

async function triggerProcessing(label, step = 'all') {
    process.stdout.write(label + ' ');
    try {
        const url = `${SERVER_URL}/cron/process.php?token=${AUTH_TOKEN}&limit=5&step=${step}`;
        const result = JSON.parse(await httpGet(url, 120000));
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

function splitArray(arr, n) {
    const chunks = Array.from({ length: n }, () => []);
    arr.forEach((item, i) => chunks[i % n].push(item));
    return chunks;
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
