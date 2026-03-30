<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Manage Advertisers</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-warning btn-sm" onclick="testApi()">
            <i class="bi bi-plug me-1"></i>Test API
        </button>
        <button class="btn btn-outline-info btn-sm" onclick="loadStatus()">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>
</div>

<!-- Search & Add Advertiser -->
<div class="filter-bar mb-4">
    <h6 class="mb-3"><i class="bi bi-search me-1"></i>Search & Add Advertiser</h6>

    <!-- Search by name -->
    <div class="row g-3 align-items-end mb-3">
        <div class="col-md-8">
            <label class="form-label">Search by Company Name</label>
            <div class="input-group">
                <input type="text" id="searchKeyword" class="form-control" placeholder="e.g. Nike, Google, Amazon..." onkeydown="if(event.key==='Enter')searchAds()">
                <button class="btn btn-primary" onclick="searchAds()" id="btnSearch">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
            <small class="text-muted">Find advertiser IDs from Google Ads Transparency Center</small>
        </div>
    </div>

    <!-- Search results -->
    <div id="searchResults" class="mb-3" style="display:none">
        <div class="card p-3">
            <h6 class="mb-2"><i class="bi bi-list-ul me-1"></i>Search Results <small class="text-muted" id="searchCount"></small></h6>
            <div id="searchResultsList" class="list-group list-group-flush" style="max-height:250px;overflow-y:auto"></div>
        </div>
    </div>

    <hr class="my-3">

    <!-- Manual add -->
    <h6 class="mb-3"><i class="bi bi-plus-circle me-1"></i>Add Advertiser (Manual or from Search)</h6>
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Advertiser ID <span class="text-danger">*</span></label>
            <input type="text" id="newAdvId" class="form-control" placeholder="e.g. AR00744063166605950977">
            <small class="text-muted">Google Ads Transparency Center advertiser ID</small>
        </div>
        <div class="col-md-4">
            <label class="form-label">Advertiser Name</label>
            <input type="text" id="newAdvName" class="form-control" placeholder="e.g. Company Name">
        </div>
        <div class="col-md-4">
            <div class="d-flex gap-2">
                <button class="btn btn-success flex-grow-1" onclick="addAndScrape()" id="btnAddScrape">
                    <i class="bi bi-lightning me-1"></i>Add & Fetch All Ads
                </button>
                <button class="btn btn-outline-primary" onclick="addOnly()" id="btnAddOnly">
                    <i class="bi bi-plus me-1"></i>Add Only
                </button>
            </div>
        </div>
    </div>

    <!-- Progress area -->
    <div id="pipelineProgress" class="mt-3" style="display:none">
        <div class="card p-3">
            <div class="d-flex align-items-center mb-2">
                <div class="spinner-border spinner-border-sm text-primary me-2" id="pipelineSpinner"></div>
                <strong id="pipelineStepLabel">Starting pipeline...</strong>
            </div>
            <div class="progress mb-2" style="height:8px">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="pipelineBar" style="width:0%"></div>
            </div>
            <div id="pipelineLog" class="small text-muted" style="max-height:150px;overflow-y:auto"></div>
        </div>
    </div>
</div>

<!-- Global Stats -->
<div class="row mb-4" id="globalStats">
    <div class="col-6 col-md-3 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="kpi-label">Total Ads</div><div class="kpi-value text-primary" id="gStatTotal">-</div></div>
                <i class="bi bi-collection kpi-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="kpi-label">Active Ads</div><div class="kpi-value text-success" id="gStatActive">-</div></div>
                <i class="bi bi-check-circle kpi-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="kpi-label">Advertisers</div><div class="kpi-value text-info" id="gStatAdvertisers">-</div></div>
                <i class="bi bi-people kpi-icon text-info"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="kpi-label">Pending</div><div class="kpi-value text-warning" id="gStatPending">-</div></div>
                <i class="bi bi-hourglass kpi-icon text-warning"></i>
            </div>
        </div>
    </div>
</div>

<!-- Tracked Advertisers Table -->
<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Tracked Advertisers</h5>
        <div>
            <button class="btn btn-outline-success btn-sm me-1" onclick="processAll()">
                <i class="bi bi-arrow-repeat me-1"></i>Process Pending
            </button>
            <button class="btn btn-outline-info btn-sm" onclick="analyzeAll()">
                <i class="bi bi-cpu me-1"></i>Run Analysis
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Advertiser</th>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Total Ads</th>
                    <th>Active</th>
                    <th>Pending</th>
                    <th>Last Fetched</th>
                    <th>Fetches</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="advertisersTable">
                <tr><td colspan="9" class="text-center text-muted">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Logs -->
<div class="table-container mt-4">
    <h5 class="mb-3">Recent Scrape Logs</h5>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Advertiser</th>
                    <th>Ads Found</th>
                    <th>New</th>
                    <th>Updated</th>
                    <th>Removed</th>
                    <th>Status</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody id="logsTable">
                <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    'use strict';

    // ── Status load ────────────────────────────────────────
    async function loadStatus() {
        try {
            const data = await fetchAPI('manage.php', { action: 'status' });
            if (!data.success) return;

            // Global stats
            const g = data.global_stats || {};
            document.getElementById('gStatTotal').textContent = formatNumber(g.total_ads || 0);
            document.getElementById('gStatActive').textContent = formatNumber(g.active_ads || 0);
            document.getElementById('gStatAdvertisers').textContent = formatNumber(g.total_advertisers || 0);
            document.getElementById('gStatPending').textContent = formatNumber(g.pending_payloads || 0);

            // Advertisers table
            renderAdvertisers(data.advertisers || []);

            // Logs table
            renderLogs(data.recent_logs || []);
        } catch (err) {
            console.error('Status load error:', err);
        }
    }
    window.loadStatus = loadStatus;

    function renderAdvertisers(advertisers) {
        const tbody = document.getElementById('advertisersTable');
        if (advertisers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No advertisers tracked yet. Add one above!</td></tr>';
            return;
        }
        const statusColors = { active: 'badge-active', new: 'bg-info', fetching: 'bg-warning', paused: 'bg-secondary', error: 'badge-inactive' };
        tbody.innerHTML = advertisers.map(a => `
            <tr>
                <td><strong>${escapeHtml(a.name)}</strong>${a.error_message ? `<br><small class="text-danger">${escapeHtml(a.error_message)}</small>` : ''}</td>
                <td><code class="small">${escapeHtml(a.advertiser_id)}</code></td>
                <td><span class="badge ${statusColors[a.status] || 'bg-secondary'}">${a.status}</span></td>
                <td><span class="badge bg-primary">${formatNumber(a.db_ads_count || a.total_ads || 0)}</span></td>
                <td><span class="badge badge-active">${formatNumber(a.db_active_ads || a.active_ads || 0)}</span></td>
                <td>${(a.pending_payloads > 0) ? `<span class="badge bg-warning">${a.pending_payloads}</span>` : '<span class="text-muted">0</span>'}</td>
                <td><small>${a.last_fetched_at ? formatDate(a.last_fetched_at) : '<span class="text-muted">Never</span>'}</small></td>
                <td><small>${a.fetch_count || 0}</small></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="reScrape('${escapeHtml(a.advertiser_id)}')" title="Re-scrape">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                        <a href="ads_viewer.php#advertiser_id=${encodeURIComponent(a.advertiser_id)}" class="btn btn-outline-success" title="View ads">
                            <i class="bi bi-eye"></i>
                        </a>
                        <button class="btn btn-outline-danger" onclick="removeAdv('${escapeHtml(a.advertiser_id)}')" title="Pause">
                            <i class="bi bi-pause-circle"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderLogs(logs) {
        const tbody = document.getElementById('logsTable');
        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No logs yet</td></tr>';
            return;
        }
        tbody.innerHTML = logs.map(l => `
            <tr>
                <td><small>${formatDate(l.created_at)}</small></td>
                <td><code class="small">${escapeHtml((l.advertiser_id || '').substring(0, 16))}...</code></td>
                <td>${formatNumber(l.ads_found || 0)}</td>
                <td>${formatNumber(l.new_ads || 0)}</td>
                <td>${formatNumber(l.updated_ads || 0)}</td>
                <td>${formatNumber(l.removed_ads || 0)}</td>
                <td><span class="badge ${l.status === 'success' ? 'badge-active' : l.status === 'partial' ? 'bg-warning' : 'badge-inactive'}">${l.status}</span></td>
                <td class="text-truncate" style="max-width:200px"><small class="text-muted">${escapeHtml(l.error_message || '-')}</small></td>
            </tr>
        `).join('');
    }

    // ── Pipeline progress helpers ──────────────────────────
    function showProgress(show) {
        document.getElementById('pipelineProgress').style.display = show ? '' : 'none';
    }

    function setProgress(pct, label, log) {
        document.getElementById('pipelineBar').style.width = pct + '%';
        document.getElementById('pipelineStepLabel').textContent = label;
        if (log) {
            const logEl = document.getElementById('pipelineLog');
            logEl.innerHTML += log + '<br>';
            logEl.scrollTop = logEl.scrollHeight;
        }
    }

    function resetProgress() {
        document.getElementById('pipelineBar').style.width = '0%';
        document.getElementById('pipelineLog').innerHTML = '';
        document.getElementById('pipelineSpinner').style.display = '';
    }

    function doneProgress(label) {
        document.getElementById('pipelineStepLabel').textContent = label;
        document.getElementById('pipelineBar').style.width = '100%';
        document.getElementById('pipelineBar').classList.remove('progress-bar-animated');
        document.getElementById('pipelineSpinner').style.display = 'none';
        setTimeout(() => {
            document.getElementById('pipelineBar').classList.add('progress-bar-animated');
        }, 2000);
    }

    // ── Add & Scrape (full pipeline) ──────────────────────
    async function addAndScrape() {
        const advId = document.getElementById('newAdvId').value.trim();
        const advName = document.getElementById('newAdvName').value.trim();

        if (!advId) { alert('Please enter an Advertiser ID'); return; }

        const btn = document.getElementById('btnAddScrape');
        btn.disabled = true;
        showProgress(true);
        resetProgress();

        try {
            setProgress(10, 'Running full pipeline: scrape → process → analyze...', 'Starting pipeline for ' + advId);

            const data = await fetchAPI('manage.php', {
                action: 'run_all',
                advertiser_id: advId,
                advertiser_name: advName || advId,
            });

            if (data.success) {
                const stats = data.stats || {};
                setProgress(100, '', `Pipeline complete! ${data.message}`);
                setProgress(100, '', `DB stats: ${stats.total || 0} total, ${stats.active || 0} active, ${stats.text_ads || 0} text, ${stats.image_ads || 0} image, ${stats.video_ads || 0} video`);
                doneProgress(`Done! ${data.message}`);
            } else {
                setProgress(100, 'Error: ' + (data.error || 'Unknown error'), null);
            }

            // Clear inputs
            document.getElementById('newAdvId').value = '';
            document.getElementById('newAdvName').value = '';

            // Refresh status
            loadStatus();

        } catch (err) {
            console.error('Pipeline error:', err);
            setProgress(100, 'Error: ' + err.message, null);
        } finally {
            btn.disabled = false;
        }
    }
    window.addAndScrape = addAndScrape;

    // ── Add Only ──────────────────────────────────────────
    async function addOnly() {
        const advId = document.getElementById('newAdvId').value.trim();
        const advName = document.getElementById('newAdvName').value.trim();
        if (!advId) { alert('Please enter an Advertiser ID'); return; }

        try {
            const data = await fetchAPI('manage.php', {
                action: 'add_advertiser',
                advertiser_id: advId,
                advertiser_name: advName || advId,
            });
            if (data.success) {
                document.getElementById('newAdvId').value = '';
                document.getElementById('newAdvName').value = '';
                loadStatus();
            }
            alert(data.message || 'Done');
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }
    window.addOnly = addOnly;

    // ── Re-scrape single advertiser ───────────────────────
    async function reScrape(advId) {
        if (!confirm('Re-scrape all ads for ' + advId + '?\nThis may take a few minutes.')) return;

        showProgress(true);
        resetProgress();
        setProgress(10, 'Scraping ' + advId + '...', 'Starting scrape...');

        try {
            // Scrape
            const scrapeData = await fetchAPI('manage.php', { action: 'scrape', advertiser_id: advId });
            setProgress(40, 'Processing payloads...', 'Scrape: ' + (scrapeData.message || 'done'));

            // Process
            const processData = await fetchAPI('manage.php', { action: 'process' });
            setProgress(70, 'Running analysis...', 'Process: ' + (processData.message || 'done'));

            // Analyze
            const analyzeData = await fetchAPI('manage.php', { action: 'analyze' });
            setProgress(100, '', 'Analysis: complete');
            doneProgress('Pipeline complete for ' + advId);

            loadStatus();
        } catch (err) {
            console.error('Re-scrape error:', err);
            setProgress(100, 'Error: ' + err.message, null);
        }
    }
    window.reScrape = reScrape;

    // ── Process pending payloads ──────────────────────────
    async function processAll() {
        try {
            const data = await fetchAPI('manage.php', { action: 'process' });
            alert(data.message || 'Done');
            loadStatus();
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }
    window.processAll = processAll;

    // ── Run analysis on all ───────────────────────────────
    async function analyzeAll() {
        try {
            const data = await fetchAPI('manage.php', { action: 'analyze' });
            alert('Analysis complete: ' + JSON.stringify(data.results || {}));
            loadStatus();
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }
    window.analyzeAll = analyzeAll;

    // ── Remove advertiser ────────────────────────────────
    async function removeAdv(advId) {
        if (!confirm('Stop tracking ' + advId + '?\nExisting data will be preserved.')) return;
        try {
            await fetchAPI('manage.php', { action: 'remove_advertiser', advertiser_id: advId });
            loadStatus();
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }
    window.removeAdv = removeAdv;

    // ── Search advertisers ─────────────────────────────
    async function searchAds() {
        const keyword = document.getElementById('searchKeyword').value.trim();
        if (!keyword) { alert('Enter a company name to search'); return; }

        const btn = document.getElementById('btnSearch');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Searching...';

        try {
            const data = await fetchAPI('manage.php', { action: 'search_advertisers', keyword: keyword });
            const container = document.getElementById('searchResults');
            const list = document.getElementById('searchResultsList');
            const count = document.getElementById('searchCount');

            if (data.success && data.results && data.results.length > 0) {
                count.textContent = `(${data.results.length} found)`;
                list.innerHTML = data.results.map(r => `
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                       onclick="selectAdvertiser('${escapeHtml(r.advertiser_id)}', '${escapeHtml(r.name || '')}'); return false;">
                        <div>
                            <strong>${escapeHtml(r.name || 'Unknown')}</strong>
                            <br><code class="small text-muted">${escapeHtml(r.advertiser_id)}</code>
                        </div>
                        <span class="badge bg-primary">Select</span>
                    </a>
                `).join('');
                container.style.display = '';
            } else {
                count.textContent = '(0 found)';
                list.innerHTML = '<div class="text-muted text-center py-3">No advertisers found for "' + escapeHtml(keyword) + '"</div>';
                container.style.display = '';
            }
        } catch (err) {
            alert('Search error: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i>Search';
        }
    }
    window.searchAds = searchAds;

    function selectAdvertiser(id, name) {
        document.getElementById('newAdvId').value = id;
        document.getElementById('newAdvName').value = name;
        document.getElementById('searchResults').style.display = 'none';
    }
    window.selectAdvertiser = selectAdvertiser;

    // ── Test API connection ─────────────────────────────
    async function testApi() {
        try {
            const data = await fetchAPI('manage.php', { action: 'test_connection' });
            if (data.success) {
                alert('API OK: ' + data.message);
            } else {
                alert('API FAILED: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            alert('API test error: ' + err.message);
        }
    }
    window.testApi = testApi;

    // ── Init ─────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', loadStatus);

})();
</script>

<?php require_once 'includes/footer.php'; ?>
