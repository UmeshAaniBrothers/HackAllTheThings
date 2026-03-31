<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Dashboard Overview</h4>
    <div class="d-flex gap-2">
        <select id="advertiserFilter" class="form-select form-select-sm w-auto" onchange="loadOverview()">
            <option value="">All Advertisers</option>
        </select>
        <button class="btn btn-outline-secondary btn-sm" onclick="loadOverview()">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="row mb-4" id="kpiRow">
    <div class="col-6 col-md-4 col-xl-2 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Total Ads</div>
                    <div class="kpi-value text-primary" id="totalAds">-</div>
                </div>
                <div class="kpi-icon text-primary"><i class="bi bi-collection"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Active Ads</div>
                    <div class="kpi-value text-success" id="activeAds">-</div>
                </div>
                <div class="kpi-icon text-success"><i class="bi bi-check-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Advertisers</div>
                    <div class="kpi-value text-info" id="totalAdvertisers">-</div>
                </div>
                <div class="kpi-icon text-info"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Apps Detected</div>
                    <div class="kpi-value text-warning" id="totalApps">-</div>
                </div>
                <div class="kpi-icon text-warning"><i class="bi bi-app-indicator"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">YouTube Videos</div>
                    <div class="kpi-value text-danger" id="totalVideos">-</div>
                </div>
                <div class="kpi-icon text-danger"><i class="bi bi-youtube"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Countries</div>
                    <div class="kpi-value" style="color:var(--ai-info)" id="totalCountries">-</div>
                </div>
                <div class="kpi-icon" style="color:var(--ai-info)"><i class="bi bi-geo-alt"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Activity Timeline -->
    <div class="col-lg-8 mb-3">
        <div class="chart-container">
            <h5><i class="bi bi-graph-up me-2"></i>Ad Activity Timeline</h5>
            <div id="timelineChart" style="height:280px;display:flex;align-items:flex-end;gap:4px;padding-top:20px"></div>
        </div>
    </div>
    <!-- Ad Type & Status Breakdown -->
    <div class="col-lg-4 mb-3">
        <div class="chart-container">
            <h5><i class="bi bi-pie-chart me-2"></i>Ad Type Breakdown</h5>
            <div id="adTypeChart" class="mb-3"></div>
            <h6 class="mt-3"><i class="bi bi-toggles me-2"></i>Status</h6>
            <div id="statusChart"></div>
        </div>
    </div>
</div>

<!-- Middle Row: Top Entities -->
<div class="row mb-4">
    <!-- Top Advertisers -->
    <div class="col-lg-4 mb-3">
        <div class="chart-container">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Top Advertisers</h5>
                <a href="manage.php" class="btn btn-outline-primary btn-sm">View All</a>
            </div>
            <div id="topAdvertisers">
                <div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm" role="status"></div></div>
            </div>
        </div>
    </div>
    <!-- Top Apps -->
    <div class="col-lg-4 mb-3">
        <div class="chart-container">
            <h5><i class="bi bi-app-indicator me-2"></i>Top Apps</h5>
            <div id="topApps">
                <div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm" role="status"></div></div>
            </div>
        </div>
    </div>
    <!-- Top Countries -->
    <div class="col-lg-4 mb-3">
        <div class="chart-container">
            <h5><i class="bi bi-geo-alt me-2"></i>Top Countries</h5>
            <div id="topCountries">
                <div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Top Videos -->
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-container">
            <h5><i class="bi bi-youtube me-2"></i>Top YouTube Videos by Views</h5>
            <div id="topVideos">
                <div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Table -->
<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
        <a href="ads_viewer.php" class="btn btn-outline-primary btn-sm">View All Ads</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Creative ID</th>
                    <th>Advertiser</th>
                    <th>Headline</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Last Seen</th>
                </tr>
            </thead>
            <tbody id="activityTable">
                <tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Pending Payloads Banner -->
<div id="pendingBanner" class="alert alert-info mt-3" style="display:none">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-hourglass-split me-2"></i>
            <strong id="pendingCount">0</strong> payloads pending processing.
        </div>
        <a href="manage.php" class="btn btn-info btn-sm">Process Now</a>
    </div>
</div>

<script>
(function() {
    'use strict';

    async function loadOverview() {
        try {
            const advertiserId = document.getElementById('advertiserFilter')?.value || null;
            const data = await fetchAPI('overview.php', { advertiser_id: advertiserId });
            if (!data.success) return;

            const s = data.stats;

            // KPIs
            document.getElementById('totalAds').textContent = formatNumber(s.total_ads);
            document.getElementById('activeAds').textContent = formatNumber(s.active_ads);
            document.getElementById('totalAdvertisers').textContent = formatNumber(s.total_advertisers);
            document.getElementById('totalApps').textContent = formatNumber(s.total_apps || 0);
            document.getElementById('totalVideos').textContent = formatNumber(s.total_videos || 0);
            document.getElementById('totalCountries').textContent = formatNumber(s.total_countries || 0);

            // Pending payloads
            const pendingCount = parseInt(s.pending_payloads) || 0;
            if (pendingCount > 0) {
                document.getElementById('pendingBanner').style.display = '';
                document.getElementById('pendingCount').textContent = formatNumber(pendingCount);
            } else {
                document.getElementById('pendingBanner').style.display = 'none';
            }

            // Timeline chart
            renderTimeline(data.timeline || []);

            // Ad type breakdown
            renderAdTypeChart(data.ad_type_breakdown || [], parseInt(s.total_ads) || 0);

            // Status breakdown
            renderStatusChart(data.status_breakdown || [], parseInt(s.total_ads) || 0);

            // Top entities
            renderTopAdvertisers(data.top_advertisers || []);
            renderTopApps(data.top_apps || []);
            renderTopCountries(data.top_countries || []);
            renderTopVideos(data.top_videos || []);

            // Recent activity
            renderActivityTable(data.recent_activity || []);

            // Advertiser filter
            populateAdvertiserFilter(data.advertisers || []);

        } catch (err) {
            console.error('Overview load error:', err);
        }
    }
    window.loadOverview = loadOverview;

    // ── Timeline Bar Chart ──────────────────────────────────
    function renderTimeline(timeline) {
        const container = document.getElementById('timelineChart');
        if (!timeline.length) {
            container.innerHTML = '<div class="text-center text-muted w-100 align-self-center">No timeline data yet</div>';
            return;
        }

        const maxCount = Math.max(...timeline.map(t => parseInt(t.count)));
        container.innerHTML = timeline.map(t => {
            const count = parseInt(t.count);
            const pct = maxCount > 0 ? Math.max(4, (count / maxCount) * 100) : 4;
            const month = t.month.substring(5);
            const monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const label = monthNames[parseInt(month)] || month;
            return `<div class="d-flex flex-column align-items-center flex-grow-1" style="min-width:40px">
                <small class="text-muted mb-1" style="font-size:.7rem">${formatNumber(count)}</small>
                <div class="timeline-bar" style="height:${pct}%;width:100%;max-width:48px;background:linear-gradient(180deg, var(--ai-primary), var(--ai-info));border-radius:6px 6px 0 0;min-height:4px" title="${t.month}: ${count} ads"></div>
                <small class="text-muted mt-1" style="font-size:.7rem">${label}</small>
            </div>`;
        }).join('');
    }

    // ── Ad Type Breakdown ───────────────────────────────────
    function renderAdTypeChart(types, total) {
        const container = document.getElementById('adTypeChart');
        if (!types.length) {
            container.innerHTML = '<div class="text-center text-muted py-2">No data</div>';
            return;
        }

        const colors = { video: 'var(--ai-info)', image: 'var(--ai-warning)', text: 'var(--ai-primary)' };
        const icons = { video: 'bi-play-circle', image: 'bi-image', text: 'bi-file-text' };

        container.innerHTML = types.map(t => {
            const count = parseInt(t.count);
            const pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
            return `<div class="d-flex align-items-center mb-2">
                <div style="width:90px" class="d-flex align-items-center">
                    <i class="bi ${icons[t.ad_type] || 'bi-question-circle'} me-2"></i>
                    <span class="text-capitalize small fw-bold">${t.ad_type}</span>
                </div>
                <div class="flex-grow-1 mx-2">
                    <div class="progress" style="height:8px;border-radius:4px">
                        <div class="progress-bar" style="width:${pct}%;background:${colors[t.ad_type] || '#6c757d'};border-radius:4px"></div>
                    </div>
                </div>
                <div style="width:80px" class="text-end">
                    <small class="fw-bold">${formatNumber(count)}</small>
                    <small class="text-muted ms-1">(${pct}%)</small>
                </div>
            </div>`;
        }).join('');
    }

    // ── Status Breakdown ────────────────────────────────────
    function renderStatusChart(statuses, total) {
        const container = document.getElementById('statusChart');
        if (!statuses.length) {
            container.innerHTML = '<div class="text-center text-muted py-2">No data</div>';
            return;
        }

        container.innerHTML = statuses.map(s => {
            const count = parseInt(s.count);
            const pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
            const color = s.status === 'active' ? 'var(--ai-success)' : 'var(--ai-danger)';
            return `<div class="d-flex align-items-center mb-2">
                <div style="width:90px" class="d-flex align-items-center">
                    <span class="badge" style="background:${color}">${s.status}</span>
                </div>
                <div class="flex-grow-1 mx-2">
                    <div class="progress" style="height:8px;border-radius:4px">
                        <div class="progress-bar" style="width:${pct}%;background:${color};border-radius:4px"></div>
                    </div>
                </div>
                <div style="width:80px" class="text-end">
                    <small class="fw-bold">${formatNumber(count)}</small>
                    <small class="text-muted ms-1">(${pct}%)</small>
                </div>
            </div>`;
        }).join('');
    }

    // ── Top Advertisers ─────────────────────────────────────
    function renderTopAdvertisers(advertisers) {
        const container = document.getElementById('topAdvertisers');
        if (!advertisers.length) {
            container.innerHTML = '<div class="text-center text-muted py-3">No advertisers yet</div>';
            return;
        }

        container.innerHTML = advertisers.map((a, i) => `
            <a href="advertiser_profile.php?id=${encodeURIComponent(a.advertiser_id)}" class="d-flex justify-content-between align-items-center text-decoration-none text-dark p-2 rounded top-entity-row">
                <div class="d-flex align-items-center">
                    <span class="badge bg-light text-dark me-2" style="width:24px">${i + 1}</span>
                    <div>
                        <div class="fw-bold small">${escapeHtml(a.name || a.advertiser_id)}${parseInt(a.new_ads_24h) > 0 ? ' <span class="badge bg-danger" style="font-size:.55rem;animation:pulse 2s infinite">+' + a.new_ads_24h + ' new</span>' : ''}</div>
                        <small class="text-muted">${formatNumber(a.active_ads || 0)} active</small>
                    </div>
                </div>
                <span class="badge bg-primary">${formatNumber(a.total_ads)}</span>
            </a>
        `).join('');
    }

    // ── Top Apps ────────────────────────────────────────────
    function renderTopApps(apps) {
        const container = document.getElementById('topApps');
        if (!apps.length) {
            container.innerHTML = '<div class="text-center text-muted py-3">No apps detected yet</div>';
            return;
        }

        const platIcons = { ios: 'bi-apple', playstore: 'bi-google-play' };
        const platColors = { ios: 'bg-dark', playstore: 'bg-success' };

        container.innerHTML = apps.map((app, i) => `
            <a href="app_profile.php?id=${app.id}" class="d-flex justify-content-between align-items-center text-decoration-none text-dark p-2 rounded top-entity-row">
                <div class="d-flex align-items-center">
                    <span class="badge bg-light text-dark me-2" style="width:24px">${i + 1}</span>
                    <span class="badge ${platColors[app.store_platform] || 'bg-info'} me-2"><i class="bi ${platIcons[app.store_platform] || 'bi-globe'}"></i></span>
                    <span class="small fw-bold">${escapeHtml(app.product_name)}</span>
                </div>
                <span class="badge bg-warning text-dark">${formatNumber(app.ad_count)} ads</span>
            </a>
        `).join('');
    }

    // ── Top Countries ───────────────────────────────────────
    function renderTopCountries(countries) {
        const container = document.getElementById('topCountries');
        if (!countries.length) {
            container.innerHTML = '<div class="text-center text-muted py-3">No country data yet</div>';
            return;
        }

        const maxCount = Math.max(...countries.map(c => parseInt(c.ad_count)));
        container.innerHTML = countries.map(c => {
            const count = parseInt(c.ad_count);
            const pct = maxCount > 0 ? ((count / maxCount) * 100) : 0;
            const flag = countryFlag(c.country);
            const name = countryName(c.country);
            return `<div class="d-flex align-items-center mb-2">
                <span class="me-2" style="width:140px;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${escapeHtml(name)}">${flag} ${escapeHtml(name)}</span>
                <div class="flex-grow-1 me-2">
                    <div class="progress" style="height:6px;border-radius:3px">
                        <div class="progress-bar" style="width:${pct}%;background:var(--ai-info);border-radius:3px"></div>
                    </div>
                </div>
                <small class="text-muted" style="width:50px;text-align:right">${formatNumber(count)}</small>
            </div>`;
        }).join('');
    }

    // ── Top Videos ──────────────────────────────────────────
    function renderTopVideos(videos) {
        const container = document.getElementById('topVideos');
        if (!videos.length) {
            container.innerHTML = '<div class="text-center text-muted py-3">No YouTube videos yet</div>';
            return;
        }

        container.innerHTML = '<div class="row">' + videos.map(v => {
            const ytId = extractYouTubeId(v.youtube_url);
            const thumb = ytId ? 'https://i.ytimg.com/vi/' + ytId + '/mqdefault.jpg' : '';
            return `<div class="col-md-4 col-lg mb-2">
                <a href="${ytId ? 'youtube_profile.php?id=' + encodeURIComponent(ytId) : '#'}" class="card text-decoration-none text-dark h-100 top-entity-row" style="overflow:hidden">
                    ${thumb ? `<img src="${escapeHtml(thumb)}" class="card-img-top" alt="" style="aspect-ratio:16/9;object-fit:cover">` : ''}
                    <div class="card-body p-2">
                        <small class="fw-bold d-block text-truncate">${escapeHtml(v.headline || 'Untitled')}</small>
                        <small class="text-muted"><i class="bi bi-eye me-1"></i>${formatNumber(v.view_count)} views</small>
                    </div>
                </a>
            </div>`;
        }).join('') + '</div>';
    }

    function extractYouTubeId(url) {
        if (!url) return null;
        const m = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([\w-]{11})/);
        return m ? m[1] : null;
    }

    // ── Activity Table ──────────────────────────────────────
    function renderActivityTable(activities) {
        const tbody = document.getElementById('activityTable');
        if (!activities || !activities.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No recent activity</td></tr>';
            return;
        }

        tbody.innerHTML = activities.map(a => `
            <tr>
                <td class="text-truncate" style="max-width:140px"><small class="text-muted">${escapeHtml((a.creative_id || '').substring(0, 16))}...</small></td>
                <td><a href="advertiser_profile.php?id=${encodeURIComponent(a.advertiser_id)}" class="text-decoration-none small">${escapeHtml(a.advertiser_name || a.advertiser_id)}</a></td>
                <td>${escapeHtml(a.headline || '-')}</td>
                <td>${typeBadge(a.ad_type)}</td>
                <td>${statusBadge(a.status)}</td>
                <td><small>${formatDate(a.last_seen)}</small></td>
            </tr>
        `).join('');
    }

    // ── Advertiser Filter ───────────────────────────────────
    function populateAdvertiserFilter(advertisers) {
        const select = document.getElementById('advertiserFilter');
        if (!select || select.options.length > 1 || !advertisers) return;
        advertisers.forEach(a => {
            const option = document.createElement('option');
            option.value = a.advertiser_id;
            option.textContent = `${a.name || a.advertiser_id} (${a.total_ads || 0} ads)`;
            select.appendChild(option);
        });
    }

    // ── Init ────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', loadOverview);

})();
</script>

<style>
.top-entity-row { transition: background-color 0.15s; }
.top-entity-row:hover { background-color: rgba(67, 97, 238, 0.05); }
.timeline-bar { transition: height 0.3s ease; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
</style>

<?php require_once 'includes/footer.php'; ?>
