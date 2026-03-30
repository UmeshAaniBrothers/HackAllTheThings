<?php require_once 'includes/header.php'; ?>

<h3>Ads Viewer Diagnostic</h3>
<div id="status" class="alert alert-info">Testing...</div>
<div id="output" style="font-family:monospace;white-space:pre-wrap;font-size:12px;max-height:600px;overflow:auto;background:#f8f9fa;padding:15px;border-radius:8px;"></div>

<script>
(async function() {
    const statusEl = document.getElementById('status');
    const outputEl = document.getElementById('output');
    let log = '';

    function addLog(msg) {
        log += msg + '\n';
        outputEl.textContent = log;
    }

    // Step 1: Check if shared functions exist
    addLog('=== Step 1: Check shared functions ===');
    const funcs = ['fetchAPI', 'escapeHtml', 'formatDate', 'formatNumber', 'countryBadge', 'countryFlag', 'countryName', 'statusBadge', 'typeBadge'];
    funcs.forEach(f => {
        addLog('  ' + f + ': ' + (typeof window[f] === 'function' ? 'OK' : 'MISSING'));
    });
    addLog('  COUNTRY_MAP: ' + (typeof COUNTRY_MAP === 'object' ? 'OK (' + Object.keys(COUNTRY_MAP).length + ' entries)' : 'MISSING'));
    addLog('  COUNTRY_FLAGS: ' + (typeof COUNTRY_FLAGS === 'object' ? 'OK (' + Object.keys(COUNTRY_FLAGS).length + ' entries)' : 'MISSING'));

    // Step 2: Test API fetch
    addLog('\n=== Step 2: Fetch API ===');
    try {
        const url = 'api/ads.php?page=1&per_page=5&sort=newest';
        addLog('  URL: ' + url);
        const resp = await fetch(url);
        addLog('  Status: ' + resp.status + ' ' + resp.statusText);
        const text = await resp.text();
        addLog('  Response length: ' + text.length + ' chars');
        addLog('  First 200 chars: ' + text.substring(0, 200));

        let data;
        try {
            data = JSON.parse(text);
            addLog('  JSON parse: OK');
        } catch(e) {
            addLog('  JSON parse ERROR: ' + e.message);
            addLog('  Raw response:\n' + text.substring(0, 500));
            statusEl.className = 'alert alert-danger';
            statusEl.textContent = 'FAILED: JSON parse error - server may be outputting PHP errors before JSON';
            return;
        }

        addLog('  success: ' + data.success);
        addLog('  total: ' + data.total);
        addLog('  ads count: ' + (data.ads ? data.ads.length : 'N/A'));

        if (!data.ads || data.ads.length === 0) {
            statusEl.className = 'alert alert-warning';
            statusEl.textContent = 'No ads returned from API';
            return;
        }

        // Step 3: Show actual ad data
        addLog('\n=== Step 3: Ad Data ===');
        data.ads.forEach((ad, i) => {
            addLog('\n--- Ad ' + (i+1) + ' ---');
            addLog('  creative_id: ' + ad.creative_id);
            addLog('  headline: ' + JSON.stringify(ad.headline));
            addLog('  description: ' + JSON.stringify(ad.description));
            addLog('  cta: ' + JSON.stringify(ad.cta));
            addLog('  countries: ' + JSON.stringify(ad.countries));
            addLog('  platforms: ' + JSON.stringify(ad.platforms));
            addLog('  ad_type: ' + ad.ad_type);
            addLog('  landing_url: ' + JSON.stringify(ad.landing_url ? ad.landing_url.substring(0, 80) : null));
            addLog('  product_names: ' + JSON.stringify(ad.product_names));
            addLog('  youtube_url: ' + JSON.stringify(ad.youtube_url));
        });

        // Step 4: Test rendering functions
        addLog('\n=== Step 4: Test rendering ===');
        const testAd = data.ads[0];
        try {
            const countries = (testAd.countries || '').split(',').map(c => c.trim()).filter(Boolean);
            addLog('  Countries parsed: ' + JSON.stringify(countries));

            if (typeof countryBadge === 'function') {
                countries.forEach(c => {
                    addLog('  countryBadge("' + c + '"): ' + countryBadge(c));
                });
            }
            if (typeof countryFlag === 'function') {
                countries.forEach(c => {
                    addLog('  countryFlag("' + c + '"): ' + countryFlag(c));
                });
            }
            if (typeof countryName === 'function') {
                countries.forEach(c => {
                    addLog('  countryName("' + c + '"): ' + countryName(c));
                });
            }
            if (typeof escapeHtml === 'function') {
                addLog('  escapeHtml("test"): ' + escapeHtml('test'));
            }
            if (typeof formatDate === 'function') {
                addLog('  formatDate("2026-03-30"): ' + formatDate('2026-03-30'));
            }
        } catch(e) {
            addLog('  RENDER ERROR: ' + e.message);
            addLog('  Stack: ' + e.stack);
        }

        // Step 5: Test full card render (same logic as ads_viewer.php)
        addLog('\n=== Step 5: Test card template ===');
        try {
            const ad = testAd;
            const countries = (ad.countries || '').split(',').map(c => c.trim()).filter(Boolean);
            const headline = ad.headline || ad.advertiser_name || ad.advertiser_id;
            const advName = ad.advertiser_name || ad.advertiser_id;
            const isVideo = ad.ad_type === 'video';

            // Test the extractYouTubeId equivalent
            let ytId = null;
            if (ad.youtube_url) {
                const m = ad.youtube_url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([\w-]{11})/);
                ytId = m ? m[1] : null;
            }
            addLog('  YouTube ID: ' + ytId);

            // Test URL parsing
            if (ad.landing_url && ad.landing_url.indexOf('displayads-formats') === -1) {
                try {
                    const h = new URL(ad.landing_url).hostname;
                    addLog('  Landing domain: ' + h);
                } catch(e) {
                    addLog('  URL parse error: ' + e.message + ' for: ' + ad.landing_url);
                }
            }

            // Build a test card HTML
            const cardHtml = '<div class="card p-3 mb-3">' +
                '<h5>' + escapeHtml(headline) + '</h5>' +
                (ad.description ? '<p class="text-muted">' + escapeHtml(ad.description) + '</p>' : '') +
                '<div class="d-flex flex-wrap gap-1">' +
                countries.map(c => {
                    const flag = countryFlag(c);
                    const name = countryName(c);
                    return '<span class="badge bg-secondary">' + flag + ' ' + escapeHtml(name) + ' (' + escapeHtml(c) + ')</span>';
                }).join('') +
                '</div>' +
                '<small class="text-muted mt-2 d-block">' + ad.ad_type + ' | ' + ad.status + ' | ' + formatDate(ad.last_seen) + '</small>' +
                '</div>';

            addLog('  Card HTML generated: OK (' + cardHtml.length + ' chars)');
            addLog('\n  RENDERED CARD:');

            // Actually render it
            const renderDiv = document.createElement('div');
            renderDiv.innerHTML = '<h4 class="mt-3">Sample Rendered Card:</h4>' + cardHtml;
            outputEl.parentNode.insertBefore(renderDiv, outputEl.nextSibling);

        } catch(e) {
            addLog('  CARD RENDER ERROR: ' + e.message);
            addLog('  Stack: ' + e.stack);
        }

        // Step 6: Test the fetchAPI function specifically
        addLog('\n=== Step 6: Test fetchAPI function ===');
        if (typeof fetchAPI === 'function') {
            try {
                const apiData = await fetchAPI('ads.php', { page: 1, per_page: 2, sort: 'newest' });
                addLog('  fetchAPI returned: success=' + apiData.success + ', ads=' + (apiData.ads ? apiData.ads.length : 'N/A'));
            } catch(e) {
                addLog('  fetchAPI ERROR: ' + e.message);
                addLog('  Stack: ' + e.stack);
            }
        } else {
            addLog('  fetchAPI NOT AVAILABLE');
        }

        statusEl.className = 'alert alert-success';
        statusEl.textContent = 'All tests passed! Check output below for details.';

    } catch(e) {
        addLog('\nFATAL ERROR: ' + e.message);
        addLog('Stack: ' + e.stack);
        statusEl.className = 'alert alert-danger';
        statusEl.textContent = 'FAILED: ' + e.message;
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>
