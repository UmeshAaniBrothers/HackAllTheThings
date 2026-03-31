#!/usr/bin/env node

/**
 * FIXED VERSION
 * Key improvements:
 * - Extract landing URLs from creatives (assets decoding lite)
 * - Stronger matching logic
 * - Debug visibility
 */

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const https = require('https');
const path = require('path');

puppeteer.use(StealthPlugin());

const GOOGLE_BASE = 'https://adstransparency.google.com';
const CHROME_PROFILE_DIR = path.join(__dirname, '..', '.chrome-profile');

const args = process.argv.slice(2);
const VISIBLE = args.includes('--visible');
const input = args.find(a => !a.startsWith('--'));

if (!input) {
  console.log('Usage: node cli/find_advertiser.js <package_or_url>');
  console.log('       node cli/find_advertiser.js --visible <package_or_url>');
  process.exit(0);
}

(async () => {
  const appInfo = parseAppInput(input);
  console.log(`\n🔍 Looking up: ${appInfo.id}`);

  const appName = await fetchAppName(appInfo);
  if (appName) console.log(`App Name: ${appName}`);

  const browser = await puppeteer.launch({
    headless: VISIBLE ? false : 'new',
    userDataDir: CHROME_PROFILE_DIR,
    args: ['--no-sandbox', '--disable-blink-features=AutomationControlled']
  });

  const page = await browser.newPage();
  await page.goto(`${GOOGLE_BASE}/?region=anywhere`, { waitUntil: 'networkidle2' });

  const creatives = [];

  page.on('response', async (res) => {
    const url = res.url();
    if (url.includes('SearchService')) {
      try {
        const json = await res.json();
        const ads = json['1'] || [];
        creatives.push(...ads);
      } catch {}
    }
  });

  const queries = [appName, appInfo.id].filter(Boolean);

  for (const q of queries) {
    console.log(`Searching: ${q}`);
    await page.goto(`${GOOGLE_BASE}/?region=anywhere&query=${encodeURIComponent(q)}`);
    await sleep(4000);
  }

  console.log(`\nTotal creatives: ${creatives.length}`);

  const advertisers = new Map();

  for (const creative of creatives) {
    const advId = creative?.['1'];
    const advName = creative?.['12'] || 'Unknown';

    if (!advId) continue;

    const urls = extractLandingUrls(creative);

    let score = 0;

    if (urls.some(u => u.includes(appInfo.id))) score += 5;

    const blob = JSON.stringify(creative).toLowerCase();

    if (blob.includes(appInfo.id.toLowerCase())) score += 3;
    if (appName && blob.includes(appName.toLowerCase())) score += 2;

    if (!advertisers.has(advId)) {
      advertisers.set(advId, { name: advName, score, count: 1 });
    } else {
      const a = advertisers.get(advId);
      a.score += score;
      a.count++;
    }

    if (urls.length) {
      console.log('🔗 URLs:', urls);
    }
  }

  const results = [...advertisers.entries()]
    .map(([id, v]) => ({ id, ...v }))
    .sort((a, b) => b.score - a.score);

  console.log('\n=== RESULTS ===');
  results.slice(0, 10).forEach(r => {
    console.log(`${r.id} | ${r.name} | score=${r.score} | ads=${r.count}`);
  });

  await browser.close();
})();

// ---------------- HELPERS ----------------

function extractLandingUrls(creative) {
  const urls = [];
  try {
    const str = JSON.stringify(creative);

    const matches = str.match(/https:\/\/play\.google\.com\/store\/apps\/details\?id=[a-zA-Z0-9._]+/g);
    if (matches) urls.push(...matches);

  } catch {}

  return urls;
}

function parseAppInput(input) {
  const m = input.match(/id=([a-zA-Z0-9._]+)/);
  if (m) return { id: m[1], platform: 'android' };
  return { id: input, platform: 'android' };
}

function fetchAppName(appInfo) {
  return new Promise(resolve => {
    const url = `https://play.google.com/store/apps/details?id=${appInfo.id}&hl=en`;

    https.get(url, res => {
      let data = '';
      res.on('data', d => data += d);
      res.on('end', () => {
        const m = data.match(/<title>([^<]+)/);
        if (m) resolve(m[1].split('-')[0].trim());
        else resolve(null);
      });
    }).on('error', () => resolve(null));
  });
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
