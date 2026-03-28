/**
 * Ad Intelligence Dashboard - Frontend JavaScript
 *
 * Handles AJAX data loading, Chart.js rendering, and UI interactions.
 */

const API_BASE = 'api/';

// ============================================================
// Utility Functions
// ============================================================

async function fetchAPI(endpoint, params = {}) {
    const url = new URL(API_BASE + endpoint, window.location.href);
    Object.entries(params).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') {
            url.searchParams.set(k, v);
        }
    });

    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`API error: ${response.status}`);
    }
    return response.json();
}

function showLoading(containerId) {
    const el = document.getElementById(containerId);
    if (el) {
        el.innerHTML = `<div class="loading-overlay">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>`;
    }
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatNumber(num) {
    return new Intl.NumberFormat().format(num || 0);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function statusBadge(status) {
    const cls = status === 'active' ? 'badge-active' : 'badge-inactive';
    return `<span class="badge ${cls}">${status}</span>`;
}

function typeBadge(type) {
    const cls = `badge-${type || 'text'}`;
    return `<span class="badge ${cls}">${type || 'text'}</span>`;
}

// ============================================================
// Overview Page
// ============================================================

async function loadOverview() {
    try {
        const advertiserId = document.getElementById('advertiserFilter')?.value || null;
        const data = await fetchAPI('overview.php', { advertiser_id: advertiserId });

        if (!data.success) return;

        // Update KPI cards
        document.getElementById('totalAds').textContent = formatNumber(data.stats.total_ads);
        document.getElementById('activeAds').textContent = formatNumber(data.stats.active_ads);
        document.getElementById('newToday').textContent = formatNumber(data.stats.new_today);
        document.getElementById('avgDuration').textContent = data.stats.avg_campaign_days + 'd';

        // Velocity chart
        renderVelocityChart(data.velocity);

        // Ad type distribution
        renderTypeChart(data.stats.ad_types);

        // Recent activity table
        renderActivityTable(data.recent_activity);

        // Populate advertiser filter
        populateAdvertiserFilter(data.advertisers);

    } catch (err) {
        console.error('Overview load error:', err);
    }
}

function renderVelocityChart(velocity) {
    const ctx = document.getElementById('velocityChart');
    if (!ctx) return;

    if (window.velocityChartInstance) {
        window.velocityChartInstance.destroy();
    }

    window.velocityChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: velocity.map(v => v.date),
            datasets: [{
                label: 'New Ads',
                data: velocity.map(v => v.count),
                borderColor: '#4361ee',
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                fill: true,
                tension: 0.4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { ticks: { maxTicksAuto: true, maxRotation: 45 } }
            }
        }
    });
}

function renderTypeChart(adTypes) {
    const ctx = document.getElementById('typeChart');
    if (!ctx) return;

    if (window.typeChartInstance) {
        window.typeChartInstance.destroy();
    }

    const colors = { text: '#4361ee', image: '#ff9f1c', video: '#7209b7' };

    window.typeChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: adTypes.map(t => t.ad_type),
            datasets: [{
                data: adTypes.map(t => t.count),
                backgroundColor: adTypes.map(t => colors[t.ad_type] || '#6c757d'),
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function renderActivityTable(activities) {
    const tbody = document.getElementById('activityTable');
    if (!tbody) return;

    tbody.innerHTML = activities.map(a => `
        <tr>
            <td><a href="creative.php?id=${encodeURIComponent(a.creative_id)}">${escapeHtml(a.creative_id.substring(0, 12))}...</a></td>
            <td>${escapeHtml(a.headline || 'N/A')}</td>
            <td>${typeBadge(a.ad_type)}</td>
            <td>${statusBadge(a.status)}</td>
            <td>${formatDate(a.created_at)}</td>
        </tr>
    `).join('');
}

function populateAdvertiserFilter(advertisers) {
    const select = document.getElementById('advertiserFilter');
    if (!select || select.options.length > 1) return;

    advertisers.forEach(a => {
        const option = document.createElement('option');
        option.value = a.advertiser_id;
        option.textContent = `${a.advertiser_id} (${a.total_ads} ads)`;
        select.appendChild(option);
    });
}

// ============================================================
// Ad Explorer Page
// ============================================================

async function loadExplorer(page = 1) {
    try {
        const params = {
            page: page,
            per_page: 20,
            advertiser_id: document.getElementById('filterAdvertiser')?.value || null,
            country: document.getElementById('filterCountry')?.value || null,
            platform: document.getElementById('filterPlatform')?.value || null,
            ad_type: document.getElementById('filterType')?.value || null,
            status: document.getElementById('filterStatus')?.value || null,
            date_from: document.getElementById('filterDateFrom')?.value || null,
            date_to: document.getElementById('filterDateTo')?.value || null,
            search: document.getElementById('filterSearch')?.value || null,
        };

        const data = await fetchAPI('ads.php', params);
        if (!data.success) return;

        renderAdGrid(data.ads);
        renderPagination(data.page, data.total_pages, data.total);
        populateFilterOptions(data.filter_options);

    } catch (err) {
        console.error('Explorer load error:', err);
    }
}

function renderAdGrid(ads) {
    const container = document.getElementById('adGrid');
    if (!container) return;

    if (ads.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted py-5"><h5>No ads found</h5></div>';
        return;
    }

    container.innerHTML = ads.map(ad => `
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <div class="ad-card">
                <div class="ad-media">
                    ${ad.preview_image
                        ? `<img src="${escapeHtml(ad.preview_image)}" alt="Ad preview" loading="lazy">`
                        : `<i class="bi bi-image no-media"></i>`
                    }
                </div>
                <div class="ad-body">
                    <div class="ad-headline">${escapeHtml(ad.headline || 'No headline')}</div>
                    <div class="ad-description">${escapeHtml(ad.description || 'No description')}</div>
                    ${ad.cta ? `<span class="badge bg-primary mt-2">${escapeHtml(ad.cta)}</span>` : ''}
                </div>
                <div class="ad-meta d-flex justify-content-between align-items-center">
                    <div>
                        ${typeBadge(ad.ad_type)} ${statusBadge(ad.status)}
                    </div>
                    <a href="creative.php?id=${encodeURIComponent(ad.creative_id)}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> View
                    </a>
                </div>
            </div>
        </div>
    `).join('');
}

function renderPagination(currentPage, totalPages, total) {
    const container = document.getElementById('pagination');
    if (!container) return;

    let html = `<div class="d-flex justify-content-between align-items-center">
        <span class="text-muted">${formatNumber(total)} results</span>
        <nav><ul class="pagination mb-0">`;

    if (currentPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadExplorer(${currentPage - 1}); return false;">Prev</a></li>`;
    }

    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="loadExplorer(${i}); return false;">${i}</a>
        </li>`;
    }

    if (currentPage < totalPages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadExplorer(${currentPage + 1}); return false;">Next</a></li>`;
    }

    html += `</ul></nav></div>`;
    container.innerHTML = html;
}

function populateFilterOptions(options) {
    populateSelect('filterAdvertiser', options.advertisers, 'advertiser_id', 'advertiser_id');
    populateSelect('filterCountry', options.countries, 'country', 'country');
    populateSelect('filterPlatform', options.platforms, 'platform', 'platform');
}

function populateSelect(id, items, valueKey, labelKey) {
    const select = document.getElementById(id);
    if (!select || select.options.length > 1) return;

    items.forEach(item => {
        const option = document.createElement('option');
        option.value = item[valueKey];
        option.textContent = item[labelKey];
        select.appendChild(option);
    });
}

// ============================================================
// Timeline Page
// ============================================================

async function loadTimeline() {
    try {
        const advertiserId = document.getElementById('timelineAdvertiser')?.value || null;
        const from = document.getElementById('timelineFrom')?.value || null;
        const to = document.getElementById('timelineTo')?.value || null;

        const data = await fetchAPI('timeline.php', { advertiser_id: advertiserId, from, to });
        if (!data.success) return;

        renderTimelineChart(data.velocity);
        renderTimelineList(data.timeline);

        const statsEl = document.getElementById('timelineStats');
        if (statsEl) {
            statsEl.innerHTML = `
                <span class="badge bg-primary me-2">Avg: ${data.avg_per_day} ads/day</span>
                <span class="badge bg-warning me-2">${data.spikes.length} activity spikes</span>
                <span class="badge bg-info">${data.timeline.length} total ads</span>
            `;
        }

    } catch (err) {
        console.error('Timeline load error:', err);
    }
}

function renderTimelineChart(velocity) {
    const ctx = document.getElementById('timelineChart');
    if (!ctx) return;

    if (window.timelineChartInstance) {
        window.timelineChartInstance.destroy();
    }

    window.timelineChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: velocity.map(v => v.date),
            datasets: [{
                label: 'Ads Created',
                data: velocity.map(v => v.count),
                backgroundColor: 'rgba(67, 97, 238, 0.7)',
                borderColor: '#4361ee',
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { ticks: { maxRotation: 45 } }
            }
        }
    });
}

function renderTimelineList(timeline) {
    const container = document.getElementById('timelineList');
    if (!container) return;

    const recent = timeline.slice(-50).reverse();

    container.innerHTML = recent.map(ad => `
        <div class="timeline-item">
            <div class="d-flex justify-content-between">
                <strong><a href="creative.php?id=${encodeURIComponent(ad.creative_id)}">${escapeHtml(ad.creative_id.substring(0, 16))}...</a></strong>
                ${statusBadge(ad.status)} ${typeBadge(ad.ad_type)}
            </div>
            <small class="text-muted">
                ${formatDate(ad.first_seen)} &mdash; ${formatDate(ad.last_seen)}
            </small>
        </div>
    `).join('');
}

// ============================================================
// Creative Detail Page
// ============================================================

async function loadCreative(creativeId) {
    try {
        const data = await fetchAPI('creative.php', { id: creativeId });
        if (!data.success) return;

        renderCreativeDetail(data);

    } catch (err) {
        console.error('Creative load error:', err);
    }
}

function renderCreativeDetail(data) {
    const ad = data.ad;
    const detail = data.details[0] || {};

    // Header info
    setHtml('creativeId', ad.creative_id);
    setHtml('creativeAdvertiser', ad.advertiser_id);
    setHtml('creativeStatus', statusBadge(ad.status));
    setHtml('creativeType', typeBadge(ad.ad_type));
    setHtml('creativeDuration', `${data.duration_days || 0} days`);
    setHtml('creativeVersions', data.version_count);
    setHtml('creativeFirstSeen', formatDate(ad.first_seen));
    setHtml('creativeLastSeen', formatDate(ad.last_seen));

    // Content
    setHtml('creativeHeadline', escapeHtml(detail.headline || 'N/A'));
    setHtml('creativeDescription', escapeHtml(detail.description || 'N/A'));
    setHtml('creativeCta', detail.cta ? `<span class="badge bg-primary">${escapeHtml(detail.cta)}</span>` : 'N/A');
    setHtml('creativeLanding', detail.landing_url
        ? `<a href="${escapeHtml(detail.landing_url)}" target="_blank" rel="noopener">${escapeHtml(detail.landing_url)}</a>`
        : 'N/A');

    // Assets
    const assetsEl = document.getElementById('creativeAssets');
    if (assetsEl) {
        assetsEl.innerHTML = data.assets.map(asset => {
            if (asset.type === 'image') {
                const src = asset.local_path || asset.original_url;
                return `<div class="col-md-4 mb-3">
                    <img src="${escapeHtml(src)}" class="img-fluid rounded" alt="Ad asset" loading="lazy">
                    <small class="text-muted d-block mt-1">${escapeHtml(asset.type)}</small>
                </div>`;
            }
            if (asset.type === 'video') {
                return `<div class="col-md-6 mb-3">
                    <video controls class="w-100 rounded">
                        <source src="${escapeHtml(asset.original_url)}" type="video/mp4">
                    </video>
                </div>`;
            }
            return '';
        }).join('') || '<div class="col-12 text-muted">No assets available</div>';
    }

    // Targeting
    const targetEl = document.getElementById('creativeTargeting');
    if (targetEl) {
        targetEl.innerHTML = data.targeting.map(t =>
            `<span class="badge bg-secondary me-1 mb-1">${escapeHtml(t.country)} / ${escapeHtml(t.platform)}</span>`
        ).join('') || '<span class="text-muted">No targeting data</span>';
    }

    // Version history
    const historyEl = document.getElementById('creativeHistory');
    if (historyEl) {
        historyEl.innerHTML = `<table class="table table-sm">
            <thead><tr><th>Date</th><th>Headline</th><th>CTA</th></tr></thead>
            <tbody>
                ${data.details.map(d => `
                    <tr>
                        <td>${formatDate(d.snapshot_date)}</td>
                        <td>${escapeHtml(d.headline || 'N/A')}</td>
                        <td>${escapeHtml(d.cta || 'N/A')}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>`;
    }
}

function setHtml(id, content) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = content;
}

// ============================================================
// Geo Dashboard Page
// ============================================================

async function loadGeo() {
    try {
        const advertiserId = document.getElementById('geoAdvertiser')?.value || null;
        const data = await fetchAPI('geo.php', { advertiser_id: advertiserId });
        if (!data.success) return;

        renderGeoTable(data.distribution);
        renderPlatformChart(data.platforms);
        renderExpansionTimeline(data.expansion);

        if (typeof L !== 'undefined') {
            renderGeoMap(data.distribution);
        }

    } catch (err) {
        console.error('Geo load error:', err);
    }
}

function renderGeoMap(distribution) {
    const mapEl = document.getElementById('geoMap');
    if (!mapEl) return;

    if (window.geoMapInstance) {
        window.geoMapInstance.remove();
    }

    const map = L.map('geoMap').setView([20, 0], 2);
    window.geoMapInstance = map;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Add markers for each country (using approximate coordinates)
    distribution.forEach(d => {
        const coords = getCountryCoords(d.country);
        if (coords) {
            const radius = Math.min(Math.max(d.ad_count * 2, 5), 40);
            L.circleMarker(coords, {
                radius: radius,
                fillColor: '#4361ee',
                color: '#fff',
                weight: 1,
                fillOpacity: 0.6,
            }).addTo(map).bindPopup(`<b>${d.country}</b><br>${d.ad_count} ads`);
        }
    });
}

function renderGeoTable(distribution) {
    const tbody = document.getElementById('geoTable');
    if (!tbody) return;

    const total = distribution.reduce((sum, d) => sum + parseInt(d.ad_count), 0);

    tbody.innerHTML = distribution.map((d, i) => {
        const pct = total > 0 ? ((d.ad_count / total) * 100).toFixed(1) : 0;
        return `<tr>
            <td>${i + 1}</td>
            <td><strong>${escapeHtml(d.country)}</strong></td>
            <td>${formatNumber(d.ad_count)}</td>
            <td>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar" style="width: ${pct}%"></div>
                </div>
            </td>
            <td>${pct}%</td>
        </tr>`;
    }).join('');
}

function renderPlatformChart(platforms) {
    const ctx = document.getElementById('platformChart');
    if (!ctx) return;

    if (window.platformChartInstance) {
        window.platformChartInstance.destroy();
    }

    const colors = ['#4361ee', '#2ec4b6', '#ff9f1c', '#e71d36', '#7209b7'];

    window.platformChartInstance = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: platforms.map(p => p.platform),
            datasets: [{
                data: platforms.map(p => p.ad_count),
                backgroundColor: colors.slice(0, platforms.length),
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function renderExpansionTimeline(expansion) {
    const container = document.getElementById('expansionTimeline');
    if (!container) return;

    container.innerHTML = expansion.map(e => `
        <div class="timeline-item">
            <strong>${escapeHtml(e.country)}</strong>
            <span class="badge bg-info ms-2">${e.ad_count} ads</span>
            <br><small class="text-muted">First targeted: ${formatDate(e.first_targeted)}</small>
        </div>
    `).join('') || '<p class="text-muted">No expansion data available</p>';
}

// ============================================================
// Alerts Page
// ============================================================

async function loadAlerts() {
    try {
        const data = await fetchAPI('alerts.php', { action: 'dashboard' });
        if (!data.success) return;

        document.getElementById('alertsToday').textContent = formatNumber(data.today_count || 0);
        document.getElementById('activeRules').textContent = formatNumber(data.rules?.length || 0);
        document.getElementById('newAdsDetected').textContent = formatNumber(data.new_ads_today || 0);
        document.getElementById('channelsCount').textContent = formatNumber(data.channels_count || 0);

        renderAlertRules(data.rules || []);
        renderAlertLog(data.recent_log || []);
    } catch (err) {
        console.error('Alerts load error:', err);
    }
}

function renderAlertRules(rules) {
    const tbody = document.getElementById('alertRulesTable');
    if (!tbody) return;

    if (rules.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No alert rules configured</td></tr>';
        return;
    }

    tbody.innerHTML = rules.map(r => `
        <tr>
            <td>${escapeHtml(r.rule_name)}</td>
            <td><span class="badge bg-info">${escapeHtml(r.rule_type)}</span></td>
            <td>${escapeHtml(r.advertiser_id || 'All')}</td>
            <td><span class="badge bg-secondary">${escapeHtml(r.channel)}</span></td>
            <td>${r.enabled ? '<span class="badge badge-active">Active</span>' : '<span class="badge badge-inactive">Disabled</span>'}</td>
            <td>${formatDate(r.last_triggered_at)}</td>
            <td>
                <button class="btn btn-sm btn-outline-danger" onclick="toggleAlertRule(${r.id}, ${r.enabled ? 0 : 1})">
                    ${r.enabled ? 'Disable' : 'Enable'}
                </button>
            </td>
        </tr>
    `).join('');
}

function renderAlertLog(logs) {
    const tbody = document.getElementById('alertLogTable');
    if (!tbody) return;

    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No recent alerts</td></tr>';
        return;
    }

    tbody.innerHTML = logs.map(l => `
        <tr>
            <td>${formatDate(l.sent_at)}</td>
            <td>${escapeHtml(l.rule_name || 'Rule #' + l.alert_rule_id)}</td>
            <td><span class="badge bg-secondary">${escapeHtml(l.channel)}</span></td>
            <td>${l.status === 'sent' ? '<span class="badge badge-active">Sent</span>' : '<span class="badge badge-inactive">Failed</span>'}</td>
            <td class="text-truncate" style="max-width: 300px;">${escapeHtml(l.message || '')}</td>
        </tr>
    `).join('');
}

async function createAlertRule() {
    try {
        const data = await fetchAPI('alerts.php', {
            action: 'create_rule',
            rule_name: document.getElementById('alertRuleName').value,
            rule_type: document.getElementById('alertRuleType').value,
            advertiser_id: document.getElementById('alertAdvertiserId').value,
            channel: document.getElementById('alertChannel').value,
            threshold: document.getElementById('alertThreshold').value,
        });
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('createAlertModal')).hide();
            loadAlerts();
        }
    } catch (err) {
        console.error('Create alert rule error:', err);
    }
}

async function toggleAlertRule(ruleId, enabled) {
    try {
        await fetchAPI('alerts.php', { action: 'toggle_rule', rule_id: ruleId, enabled: enabled });
        loadAlerts();
    } catch (err) {
        console.error('Toggle alert error:', err);
    }
}

// ============================================================
// Watchlists Page
// ============================================================

async function loadWatchlists() {
    try {
        const data = await fetchAPI('watchlists.php', { action: 'list' });
        if (!data.success) return;

        renderWatchlistTable(data.watchlists || []);
    } catch (err) {
        console.error('Watchlists load error:', err);
    }
}

function renderWatchlistTable(watchlists) {
    const tbody = document.getElementById('watchlistTable');
    if (!tbody) return;

    if (watchlists.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No watchlists created</td></tr>';
        return;
    }

    tbody.innerHTML = watchlists.map(w => `
        <tr>
            <td><strong>${escapeHtml(w.name)}</strong></td>
            <td><span class="badge bg-primary">${w.advertiser_count || 0} advertisers</span></td>
            <td>${formatDate(w.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" onclick="viewWatchlist(${w.id})">
                    <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteWatchlist(${w.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

async function viewWatchlist(watchlistId) {
    try {
        const [summary, changes] = await Promise.all([
            fetchAPI('watchlists.php', { action: 'summary', watchlist_id: watchlistId }),
            fetchAPI('watchlists.php', { action: 'changes', watchlist_id: watchlistId }),
        ]);

        const summaryEl = document.getElementById('dailySummary');
        if (summaryEl && summary.success) {
            const s = summary.summary || {};
            summaryEl.innerHTML = `
                <div class="row text-center">
                    <div class="col-4"><div class="kpi-label">New Ads</div><div class="fw-bold fs-5">${s.new_ads || 0}</div></div>
                    <div class="col-4"><div class="kpi-label">Stopped</div><div class="fw-bold fs-5">${s.stopped_ads || 0}</div></div>
                    <div class="col-4"><div class="kpi-label">Updated</div><div class="fw-bold fs-5">${s.updated_ads || 0}</div></div>
                </div>
            `;
        }

        const logEl = document.getElementById('changeLog');
        if (logEl && changes.success) {
            const logs = changes.changes || [];
            logEl.innerHTML = logs.length === 0
                ? '<p class="text-muted">No recent changes</p>'
                : logs.map(c => `
                    <div class="timeline-item">
                        <strong>${escapeHtml(c.advertiser_id)}</strong>
                        <span class="badge bg-info ms-1">${escapeHtml(c.change_type)}</span>
                        <br><small class="text-muted">${formatDate(c.detected_at)}: ${escapeHtml(c.details || '')}</small>
                    </div>
                `).join('');
        }
    } catch (err) {
        console.error('View watchlist error:', err);
    }
}

async function createWatchlist() {
    try {
        const name = document.getElementById('watchlistName').value;
        const advertisers = document.getElementById('watchlistAdvertisers').value;

        const data = await fetchAPI('watchlists.php', {
            action: 'create',
            name: name,
            advertisers: advertisers,
        });

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('createWatchlistModal')).hide();
            loadWatchlists();
        }
    } catch (err) {
        console.error('Create watchlist error:', err);
    }
}

async function deleteWatchlist(id) {
    if (!confirm('Delete this watchlist?')) return;
    try {
        await fetchAPI('watchlists.php', { action: 'delete', watchlist_id: id });
        loadWatchlists();
    } catch (err) {
        console.error('Delete watchlist error:', err);
    }
}

// ============================================================
// Comparison Page
// ============================================================

async function loadCompareAdvertisers() {
    try {
        const data = await fetchAPI('overview.php');
        if (!data.success) return;

        const advertisers = data.advertisers || [];
        ['compareA', 'compareB'].forEach(id => {
            const select = document.getElementById(id);
            if (select && select.options.length <= 1) {
                advertisers.forEach(a => {
                    const option = document.createElement('option');
                    option.value = a.advertiser_id;
                    option.textContent = `${a.advertiser_id} (${a.total_ads} ads)`;
                    select.appendChild(option);
                });
            }
        });
    } catch (err) {
        console.error('Load compare advertisers error:', err);
    }
}

async function runComparison() {
    const a = document.getElementById('compareA').value;
    const b = document.getElementById('compareB').value;
    if (!a || !b) { alert('Please select two advertisers'); return; }

    try {
        const data = await fetchAPI('compare.php', { advertiser_a: a, advertiser_b: b });
        if (!data.success) return;

        document.getElementById('comparisonResults').style.display = '';

        const cmp = data.comparison || {};
        document.getElementById('compareAName').textContent = a;
        document.getElementById('compareBName').textContent = b;
        document.getElementById('compareATotalAds').textContent = formatNumber(cmp.a?.total_ads);
        document.getElementById('compareAActive').textContent = formatNumber(cmp.a?.active_ads);
        document.getElementById('compareACountries').textContent = formatNumber(cmp.a?.countries);
        document.getElementById('compareBTotalAds').textContent = formatNumber(cmp.b?.total_ads);
        document.getElementById('compareBActive').textContent = formatNumber(cmp.b?.active_ads);
        document.getElementById('compareBCountries').textContent = formatNumber(cmp.b?.countries);

        renderCompareVolumeChart(cmp);
        renderCompareTypeChart(cmp);
        renderSharedCountries(cmp.shared_countries || []);
        renderStrategyDiffs(cmp.differences || {});
        renderRankings(data.rankings || []);
    } catch (err) {
        console.error('Comparison error:', err);
    }
}

function renderCompareVolumeChart(cmp) {
    const ctx = document.getElementById('compareVolumeChart');
    if (!ctx) return;
    if (window.compareVolumeChartInstance) window.compareVolumeChartInstance.destroy();

    window.compareVolumeChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Total Ads', 'Active', 'Inactive'],
            datasets: [
                { label: cmp.a?.advertiser_id || 'A', data: [cmp.a?.total_ads || 0, cmp.a?.active_ads || 0, (cmp.a?.total_ads || 0) - (cmp.a?.active_ads || 0)], backgroundColor: 'rgba(67, 97, 238, 0.7)' },
                { label: cmp.b?.advertiser_id || 'B', data: [cmp.b?.total_ads || 0, cmp.b?.active_ads || 0, (cmp.b?.total_ads || 0) - (cmp.b?.active_ads || 0)], backgroundColor: 'rgba(231, 29, 54, 0.7)' },
            ]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
}

function renderCompareTypeChart(cmp) {
    const ctx = document.getElementById('compareTypeChart');
    if (!ctx) return;
    if (window.compareTypeChartInstance) window.compareTypeChartInstance.destroy();

    const types = ['text', 'image', 'video'];
    window.compareTypeChartInstance = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: types,
            datasets: [
                { label: cmp.a?.advertiser_id || 'A', data: types.map(t => cmp.a?.ad_types?.[t] || 0), borderColor: '#4361ee', backgroundColor: 'rgba(67, 97, 238, 0.2)' },
                { label: cmp.b?.advertiser_id || 'B', data: types.map(t => cmp.b?.ad_types?.[t] || 0), borderColor: '#e71d36', backgroundColor: 'rgba(231, 29, 54, 0.2)' },
            ]
        },
        options: { responsive: true }
    });
}

function renderSharedCountries(countries) {
    const el = document.getElementById('sharedCountries');
    if (!el) return;
    el.innerHTML = countries.length === 0
        ? '<p class="text-muted">No shared countries</p>'
        : countries.map(c => `<span class="badge bg-primary me-1 mb-1">${escapeHtml(c)}</span>`).join('');
}

function renderStrategyDiffs(diffs) {
    const el = document.getElementById('strategyDiffs');
    if (!el) return;
    const entries = Object.entries(diffs);
    el.innerHTML = entries.length === 0
        ? '<p class="text-muted">No major differences</p>'
        : `<ul class="list-unstyled">${entries.map(([k, v]) => `<li class="mb-2"><strong>${escapeHtml(k)}:</strong> ${escapeHtml(String(v))}</li>`).join('')}</ul>`;
}

function renderRankings(rankings) {
    const tbody = document.getElementById('rankingsTable');
    if (!tbody) return;
    tbody.innerHTML = rankings.map((r, i) => `
        <tr>
            <td>${i + 1}</td>
            <td><strong>${escapeHtml(r.advertiser_id)}</strong></td>
            <td>${formatNumber(r.total_ads)}</td>
            <td>${formatNumber(r.active_ads)}</td>
            <td>${formatNumber(r.countries)}</td>
            <td>
                <div class="score-circle-sm ${r.score >= 70 ? 'score-high' : r.score >= 40 ? 'score-medium' : 'score-low'}">
                    ${Math.round(r.score || 0)}
                </div>
            </td>
        </tr>
    `).join('');
}

// ============================================================
// Intelligence Page
// ============================================================

async function loadIntelligence() {
    try {
        const advertiserId = document.getElementById('intelAdvertiser')?.value || null;
        const data = await fetchAPI('intelligence.php', { advertiser_id: advertiserId });
        if (!data.success) return;

        document.getElementById('intelAnalyzed').textContent = formatNumber(data.analyzed_count);
        document.getElementById('intelAbTests').textContent = formatNumber(data.ab_tests_count);
        document.getElementById('intelClusters').textContent = formatNumber(data.clusters_count);
        document.getElementById('intelAvgPerf').textContent = (data.avg_performance || 0).toFixed(1);

        renderSentimentChart(data.sentiment_distribution || {});
        renderHooksChart(data.hooks_distribution || {});
        renderPatternsTable(data.patterns || []);
        renderKeywordsCloud(data.top_keywords || []);
        renderAbTestsTable(data.ab_tests || []);
        renderPerformanceTable(data.top_performers || []);

        // Populate advertiser filter
        if (data.advertisers) {
            const select = document.getElementById('intelAdvertiser');
            if (select && select.options.length <= 1) {
                data.advertisers.forEach(a => {
                    const option = document.createElement('option');
                    option.value = a.advertiser_id;
                    option.textContent = a.advertiser_id;
                    select.appendChild(option);
                });
            }
        }
    } catch (err) {
        console.error('Intelligence load error:', err);
    }
}

function renderSentimentChart(dist) {
    const ctx = document.getElementById('sentimentChart');
    if (!ctx) return;
    if (window.sentimentChartInstance) window.sentimentChartInstance.destroy();

    const labels = Object.keys(dist);
    const values = Object.values(dist);
    const colors = { aggressive: '#e71d36', moderate: '#ff9f1c', soft: '#2ec4b6', neutral: '#6c757d' };

    window.sentimentChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{ data: values, backgroundColor: labels.map(l => colors[l] || '#4361ee') }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
}

function renderHooksChart(dist) {
    const ctx = document.getElementById('hooksChart');
    if (!ctx) return;
    if (window.hooksChartInstance) window.hooksChartInstance.destroy();

    const labels = Object.keys(dist);
    const values = Object.values(dist);

    window.hooksChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{ label: 'Count', data: values, backgroundColor: 'rgba(114, 9, 183, 0.7)', borderColor: '#7209b7', borderWidth: 1 }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

function renderPatternsTable(patterns) {
    const tbody = document.getElementById('patternsTable');
    if (!tbody) return;
    if (patterns.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No patterns detected</td></tr>';
        return;
    }
    tbody.innerHTML = patterns.map(p => `
        <tr>
            <td>${escapeHtml(p.advertiser_id)}</td>
            <td><span class="badge bg-info">${escapeHtml(p.pattern_type)}</span></td>
            <td>
                <div class="progress" style="height: 6px; width: 80px;">
                    <div class="progress-bar" style="width: ${(p.confidence * 100).toFixed(0)}%"></div>
                </div>
                <small>${(p.confidence * 100).toFixed(0)}%</small>
            </td>
            <td>${formatDate(p.detected_at)}</td>
        </tr>
    `).join('');
}

function renderKeywordsCloud(keywords) {
    const el = document.getElementById('keywordsCloud');
    if (!el) return;
    if (keywords.length === 0) {
        el.innerHTML = '<p class="text-muted">No keywords extracted</p>';
        return;
    }
    const maxCount = Math.max(...keywords.map(k => k.count || 1));
    el.innerHTML = keywords.map(k => {
        const size = 0.7 + (k.count / maxCount) * 1.5;
        return `<span class="badge bg-secondary me-1 mb-1" style="font-size: ${size}rem;">${escapeHtml(k.keyword)} (${k.count})</span>`;
    }).join('');
}

function renderAbTestsTable(tests) {
    const tbody = document.getElementById('abTestsTable');
    if (!tbody) return;
    if (tests.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No A/B tests detected</td></tr>';
        return;
    }
    tbody.innerHTML = tests.map(t => `
        <tr>
            <td><a href="creative.php?id=${encodeURIComponent(t.creative_a)}">${escapeHtml((t.creative_a || '').substring(0, 12))}...</a></td>
            <td><a href="creative.php?id=${encodeURIComponent(t.creative_b)}">${escapeHtml((t.creative_b || '').substring(0, 12))}...</a></td>
            <td>${((1 - (t.distance || 0) / 64) * 100).toFixed(0)}%</td>
            <td><span class="badge bg-warning">Detected</span></td>
        </tr>
    `).join('');
}

function renderPerformanceTable(performers) {
    const tbody = document.getElementById('performanceTable');
    if (!tbody) return;
    if (performers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No performance data</td></tr>';
        return;
    }
    tbody.innerHTML = performers.map(p => `
        <tr>
            <td><a href="creative.php?id=${encodeURIComponent(p.creative_id)}">${escapeHtml((p.creative_id || '').substring(0, 12))}...</a></td>
            <td class="text-truncate" style="max-width: 200px;">${escapeHtml(p.headline || 'N/A')}</td>
            <td><span class="badge ${p.estimated_score >= 70 ? 'bg-success' : p.estimated_score >= 40 ? 'bg-warning' : 'bg-danger'}">${(p.estimated_score || 0).toFixed(0)}</span></td>
            <td>${p.longevity_days || 0}d</td>
        </tr>
    `).join('');
}

// ============================================================
// Landing Pages
// ============================================================

async function loadLandingPages() {
    try {
        const advertiserId = document.getElementById('landingAdvertiser')?.value || null;
        const data = await fetchAPI('landing.php', { advertiser_id: advertiserId });
        if (!data.success) return;

        const pages = data.pages || [];
        const changes = data.recent_changes || [];

        document.getElementById('lpTotalPages').textContent = formatNumber(pages.length);
        document.getElementById('lpRecentChanges').textContent = formatNumber(changes.length);

        const domains = new Set(pages.map(p => p.domain).filter(Boolean));
        document.getElementById('lpUniqueDomains').textContent = formatNumber(domains.size);
        document.getElementById('lpHasForms').textContent = formatNumber(pages.filter(p => p.has_form).length);

        renderFunnelChart(data.funnel_distribution || {});
        renderTechChart(data.technologies || {});
        renderLandingPagesTable(pages);
        renderLpChangesTable(changes);
    } catch (err) {
        console.error('Landing pages load error:', err);
    }
}

function renderFunnelChart(dist) {
    const ctx = document.getElementById('funnelChart');
    if (!ctx) return;
    if (window.funnelChartInstance) window.funnelChartInstance.destroy();

    const labels = Object.keys(dist);
    const values = Object.values(dist);
    const colors = ['#4361ee', '#2ec4b6', '#ff9f1c', '#e71d36', '#7209b7', '#6c757d'];

    window.funnelChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: { labels, datasets: [{ data: values, backgroundColor: colors.slice(0, labels.length) }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
}

function renderTechChart(techs) {
    const ctx = document.getElementById('techChart');
    if (!ctx) return;
    if (window.techChartInstance) window.techChartInstance.destroy();

    const entries = Object.entries(techs).slice(0, 10);
    window.techChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: entries.map(e => e[0]),
            datasets: [{ label: 'Pages', data: entries.map(e => e[1]), backgroundColor: 'rgba(46, 196, 182, 0.7)' }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}

function renderLandingPagesTable(pages) {
    const tbody = document.getElementById('landingPagesTable');
    if (!tbody) return;
    if (pages.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No landing pages tracked</td></tr>';
        return;
    }
    tbody.innerHTML = pages.slice(0, 50).map(p => {
        const techs = JSON.parse(p.technologies || '[]');
        return `<tr>
            <td class="text-truncate" style="max-width: 250px;">
                <a href="${escapeHtml(p.url || '#')}" target="_blank" rel="noopener">${escapeHtml(p.url || 'N/A')}</a>
            </td>
            <td>${escapeHtml(p.domain || 'N/A')}</td>
            <td><span class="badge bg-info">${escapeHtml(p.funnel_type || 'unknown')}</span></td>
            <td>${techs.slice(0, 3).map(t => `<span class="badge bg-secondary me-1">${escapeHtml(t)}</span>`).join('')}</td>
            <td>${formatDate(p.last_scraped_at)}</td>
            <td><span class="badge bg-warning">${p.change_count || 0}</span></td>
        </tr>`;
    }).join('');
}

function renderLpChangesTable(changes) {
    const tbody = document.getElementById('lpChangesTable');
    if (!tbody) return;
    if (changes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No recent changes</td></tr>';
        return;
    }
    tbody.innerHTML = changes.map(c => `
        <tr>
            <td class="text-truncate" style="max-width: 200px;">${escapeHtml(c.url || c.domain || 'N/A')}</td>
            <td><span class="badge bg-info">${escapeHtml(c.change_type || 'update')}</span></td>
            <td class="text-truncate" style="max-width: 150px;">${escapeHtml(c.old_value || '-')}</td>
            <td class="text-truncate" style="max-width: 150px;">${escapeHtml(c.new_value || '-')}</td>
            <td>${formatDate(c.detected_at)}</td>
        </tr>
    `).join('');
}

// ============================================================
// Advanced Search Page
// ============================================================

let searchCurrentPage = 1;

async function runSearch(page = 1) {
    searchCurrentPage = page;
    try {
        const params = {
            q: document.getElementById('searchKeyword')?.value || '',
            domain: document.getElementById('searchDomain')?.value || '',
            cta: document.getElementById('searchCta')?.value || '',
            country: document.getElementById('searchCountry')?.value || '',
            platform: document.getElementById('searchPlatform')?.value || '',
            ad_type: document.getElementById('searchAdType')?.value || '',
            sentiment: document.getElementById('searchSentiment')?.value || '',
            hook: document.getElementById('searchHook')?.value || '',
            tag: document.getElementById('searchTag')?.value || '',
            page: page,
            per_page: 20,
        };

        const data = await fetchAPI('search.php', params);
        if (!data.success) return;

        document.getElementById('searchResultsInfo').style.display = '';
        document.getElementById('searchResultCount').textContent = `${formatNumber(data.total)} results found (page ${data.page} of ${data.total_pages})`;

        renderSearchResults(data.results || []);
        renderSearchPagination(data.page, data.total_pages);
    } catch (err) {
        console.error('Search error:', err);
    }
}

function renderSearchResults(results) {
    const tbody = document.getElementById('searchResultsTable');
    if (!tbody) return;
    if (results.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No results found</td></tr>';
        return;
    }
    tbody.innerHTML = results.map(r => `
        <tr>
            <td><a href="creative.php?id=${encodeURIComponent(r.creative_id)}">${escapeHtml((r.creative_id || '').substring(0, 12))}...</a></td>
            <td class="text-truncate" style="max-width: 200px;">${escapeHtml(r.headline || 'N/A')}</td>
            <td>${escapeHtml(r.cta || '-')}</td>
            <td>${typeBadge(r.ad_type)}</td>
            <td>${statusBadge(r.status)}</td>
            <td>${escapeHtml(r.countries || '-')}</td>
            <td>${escapeHtml(r.platforms || '-')}</td>
            <td>${formatDate(r.last_seen)}</td>
        </tr>
    `).join('');
}

function renderSearchPagination(current, total) {
    const container = document.getElementById('searchPagination');
    if (!container || total <= 1) { if (container) container.innerHTML = ''; return; }

    let html = '<nav class="mt-3"><ul class="pagination justify-content-center mb-0">';
    if (current > 1) html += `<li class="page-item"><a class="page-link" href="#" onclick="runSearch(${current - 1}); return false;">Prev</a></li>`;

    const startPage = Math.max(1, current - 2);
    const endPage = Math.min(total, current + 2);
    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="#" onclick="runSearch(${i}); return false;">${i}</a></li>`;
    }

    if (current < total) html += `<li class="page-item"><a class="page-link" href="#" onclick="runSearch(${current + 1}); return false;">Next</a></li>`;
    html += '</ul></nav>';
    container.innerHTML = html;
}

function clearSearch() {
    ['searchKeyword', 'searchDomain', 'searchCta', 'searchCountry'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    ['searchPlatform', 'searchAdType', 'searchSentiment', 'searchHook', 'searchTag'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('searchResultsInfo').style.display = 'none';
    document.getElementById('searchResultsTable').innerHTML = '<tr><td colspan="8" class="text-center text-muted">Enter search criteria and click Search</td></tr>';
    document.getElementById('searchPagination').innerHTML = '';
}

async function loadSearchTags() {
    try {
        const data = await fetchAPI('tags.php', { action: 'list' });
        if (!data.success) return;
        const select = document.getElementById('searchTag');
        if (!select) return;
        (data.tags || []).forEach(t => {
            const option = document.createElement('option');
            option.value = t.name;
            option.textContent = t.name;
            select.appendChild(option);
        });
    } catch (err) {
        console.error('Load tags error:', err);
    }
}

// Country coordinates lookup (ISO 2-letter codes)
function getCountryCoords(code) {
    const coords = {
        'US': [39.8, -98.5], 'GB': [55.3, -3.4], 'CA': [56.1, -106.3],
        'AU': [-25.2, 133.7], 'DE': [51.1, 10.4], 'FR': [46.2, 2.2],
        'IN': [20.5, 78.9], 'BR': [-14.2, -51.9], 'JP': [36.2, 138.2],
        'MX': [23.6, -102.5], 'IT': [41.8, 12.5], 'ES': [40.4, -3.7],
        'KR': [35.9, 127.7], 'RU': [61.5, 105.3], 'NL': [52.1, 5.2],
        'SE': [60.1, 18.6], 'NO': [60.4, 8.4], 'DK': [56.2, 9.5],
        'FI': [61.9, 25.7], 'PL': [51.9, 19.1], 'BE': [50.5, 4.4],
        'AT': [47.5, 14.5], 'CH': [46.8, 8.2], 'PT': [39.3, -8.2],
        'IE': [53.4, -8.2], 'NZ': [-40.9, 174.8], 'SG': [1.3, 103.8],
        'HK': [22.3, 114.1], 'TW': [23.6, 120.9], 'TH': [15.8, 100.9],
        'PH': [12.8, 121.7], 'MY': [4.2, 101.9], 'ID': [-0.7, 113.9],
        'VN': [14.0, 108.2], 'ZA': [-30.5, 22.9], 'NG': [9.0, 8.6],
        'EG': [26.8, 30.8], 'KE': [-0.02, 37.9], 'SA': [23.8, 45.0],
        'AE': [23.4, 53.8], 'IL': [31.0, 34.8], 'TR': [38.9, 35.2],
        'AR': [-38.4, -63.6], 'CL': [-35.6, -71.5], 'CO': [4.5, -74.2],
        'PE': [-9.1, -75.0], 'PK': [30.3, 69.3], 'BD': [23.6, 90.3],
        'UA': [48.3, 31.1], 'RO': [45.9, 24.9], 'CZ': [49.8, 15.4],
        'HU': [47.1, 19.5], 'GR': [39.0, 21.8],
    };
    return coords[code?.toUpperCase()] || null;
}
