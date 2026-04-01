<?php require_once 'includes/header.php'; ?>

<!-- Context Banner (shown when advertiser selected) -->
<div id="contextBanner" class="alert alert-primary d-flex justify-content-between align-items-center mb-3" style="display:none !important">
    <div>
        <i class="bi bi-building me-2"></i>Showing data for: <strong id="contextAdvName"></strong>
        <a href="#" id="contextProfileLink" class="ms-2 btn btn-sm btn-outline-primary py-0"><i class="bi bi-person-lines-fill me-1"></i>View Profile</a>
    </div>
    <button class="btn btn-sm btn-outline-danger py-0" onclick="clearAdvertiserFilter()"><i class="bi bi-x-lg me-1"></i>Clear</button>
</div>

<!-- KPI Cards Row -->
<div class="row mb-4" id="kpiRow">
    <div class="col-6 col-md-3 col-xl mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Total Ads</div>
                    <div class="kpi-value text-primary" id="kpiTotalAds">-</div>
                    <div class="kpi-sub" id="kpiTotalAdsSub"></div>
                </div>
                <div class="kpi-icon text-primary"><i class="bi bi-collection"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Active Ads</div>
                    <div class="kpi-value text-success" id="kpiActiveAds">-</div>
                    <div class="kpi-sub" id="kpiActiveAdsSub"></div>
                </div>
                <div class="kpi-icon text-success"><i class="bi bi-check-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">New Ads</div>
                    <div class="kpi-value text-danger" id="kpiNewAds">-</div>
                    <div class="kpi-sub text-muted" id="kpiNewAdsSub">in period</div>
                </div>
                <div class="kpi-icon text-danger"><i class="bi bi-plus-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Total Views</div>
                    <div class="kpi-value" style="color:var(--ai-info)" id="kpiTotalViews">-</div>
                </div>
                <div class="kpi-icon" style="color:var(--ai-info)"><i class="bi bi-eye"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Apps</div>
                    <div class="kpi-value text-warning" id="kpiTotalApps">-</div>
                </div>
                <div class="kpi-icon text-warning"><i class="bi bi-app-indicator"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Videos</div>
                    <div class="kpi-value text-danger" id="kpiTotalVideos">-</div>
                </div>
                <div class="kpi-icon text-danger"><i class="bi bi-youtube"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Advertisers</div>
                    <div class="kpi-value" style="color:#8b5cf6" id="kpiAdvertisers">-</div>
                </div>
                <div class="kpi-icon" style="color:#8b5cf6"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Countries</div>
                    <div class="kpi-value" style="color:#06b6d4" id="kpiCountries">-</div>
                </div>
                <div class="kpi-icon" style="color:#06b6d4"><i class="bi bi-geo-alt"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-lg-8 mb-3">
        <div class="chart-container" style="position:relative">
            <h5><i class="bi bi-graph-up me-2"></i>Ad Trend</h5>
            <canvas id="trendChart" height="280"></canvas>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="chart-container">
            <h5><i class="bi bi-pie-chart me-2"></i>Ad Type Distribution</h5>
            <div style="position:relative;height:200px;margin:0 auto;max-width:200px">
                <canvas id="typeDonut"></canvas>
            </div>
            <div id="typeDonutLegend" class="mt-2"></div>
            <hr class="my-2">
            <h6><i class="bi bi-toggles me-2"></i>Status</h6>
            <div id="statusChart"></div>
        </div>
    </div>
</div>

<!-- Top Entities Row -->
<div class="row mb-4">
    <!-- Top Apps -->
    <div class="col-lg-4 mb-3">
        <div class="chart-container">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0"><i class="bi bi-app-indicator me-2"></i>Top Apps</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-primary btn-sm active top-apps-sort" data-sort="ads" onclick="setTopAppsSort('ads')">By Ads</button>
                    <button class="btn btn-outline-primary btn-sm top-apps-sort" data-sort="views" onclick="setTopAppsSort('views')">By Views</button>
                </div>
            </div>
            <div id="topApps"><div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div></div></div>
        </div>
    </div>
    <!-- Top YouTube Videos -->
    <div class="col-lg-4 mb-3">
        <div class="chart-container">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0"><i class="bi bi-youtube me-2"></i>Top Videos</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-danger btn-sm active top-vid-sort" data-sort="views" onclick="setTopVidSort('views')">Views</button>
                    <button class="btn btn-outline-danger btn-sm top-vid-sort" data-sort="likes" onclick="setTopVidSort('likes')">Likes</button>
                </div>
            </div>
            <div id="topVideos"><div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div></div></div>
        </div>
    </div>
    <!-- Top Countries -->
    <div class="col-lg-4 mb-3">
        <div class="chart-container">
            <h5><i class="bi bi-geo-alt me-2"></i>Top Countries</h5>
            <canvas id="countryChart" height="300"></canvas>
        </div>
    </div>
</div>

<!-- Top Videos Visual Row -->
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-play-btn me-2"></i>Trending YouTube Ads</h5>
                <a href="ads_viewer.php#ad_type=video" class="btn btn-outline-danger btn-sm">View All Video Ads</a>
            </div>
            <div class="row" id="videoCards">
                <div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Top Advertisers (hidden when one is selected) -->
<div class="row mb-4" id="topAdvertisersRow">
    <div class="col-12">
        <div class="chart-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Top Advertisers</h5>
                <a href="manage.php" class="btn btn-outline-primary btn-sm">View All</a>
            </div>
            <div id="topAdvertisers"><div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div></div></div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="table-container mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
        <a href="ads_viewer.php" class="btn btn-outline-primary btn-sm">View All Ads</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Creative ID</th><th>Advertiser</th><th>Headline</th><th>Type</th><th>Status</th><th>Last Seen</th></tr>
            </thead>
            <tbody id="activityTable">
                <tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Pending -->
<div id="pendingBanner" class="alert alert-info" style="display:none">
    <div class="d-flex justify-content-between align-items-center">
        <div><i class="bi bi-hourglass-split me-2"></i><strong id="pendingCount">0</strong> payloads pending processing.</div>
        <a href="manage.php" class="btn btn-info btn-sm">Process Now</a>
    </div>
</div>

<script>
(function() {
    'use strict';

    // Chart instances (destroy before re-create)
    let trendChartInstance = null;
    let typeDonutInstance = null;
    let countryChartInstance = null;

    // Sorting state
    let topAppsSort = 'ads';
    let topVidSort = 'views';
    let cachedData = null;

    // ── Main Load ──────────────────────────────────────────
    async function loadDashboard() {
        try {
            const params = GlobalFilters.getApiParams();
            const data = await fetchAPI('overview.php', params);
            if (!data.success) return;
            cachedData = data;

            updateKPIs(data.stats);
            updateContextBanner();
            renderTrendChart(data.timeline || [], data.timeline_granularity || 'month');
            renderTypeDonut(data.ad_type_breakdown || []);
            renderStatusChart(data.status_breakdown || [], parseInt(data.stats.total_ads) || 0);
            renderTopApps(data.top_apps || []);
            renderTopVideos(data.top_videos || []);
            renderCountryChart(data.top_countries || []);
            renderVideoCards(data.top_videos || []);
            renderTopAdvertisers(data.top_advertisers || []);
            renderActivityTable(data.recent_activity || []);

            // Pending
            const pc = parseInt(data.stats.pending_payloads) || 0;
            document.getElementById('pendingBanner').style.display = pc > 0 ? '' : 'none';
            document.getElementById('pendingCount').textContent = formatNumber(pc);
        } catch(err) {
            console.error('Dashboard error:', err);
        }
    }

    // Listen for global filter changes
    window.addEventListener('globalfilter:change', loadDashboard);
    document.addEventListener('DOMContentLoaded', function() {
        // Small delay to let GlobalFilters init first
        setTimeout(loadDashboard, 100);
    });

    // ── Context Banner ─────────────────────────────────────
    function updateContextBanner() {
        const banner = document.getElementById('contextBanner');
        const advRow = document.getElementById('topAdvertisersRow');
        if (GlobalFilters.advertiserId) {
            banner.style.cssText = '';
            document.getElementById('contextAdvName').textContent = GlobalFilters.advertiserName || GlobalFilters.advertiserId;
            document.getElementById('contextProfileLink').href = 'advertiser_profile.php?id=' + encodeURIComponent(GlobalFilters.advertiserId);
            if (advRow) advRow.style.display = 'none';
        } else {
            banner.style.display = 'none';
            if (advRow) advRow.style.display = '';
        }
    }

    window.clearAdvertiserFilter = function() {
        const sel = document.getElementById('globalAdvertiser');
        if (sel) sel.value = '';
        GlobalFilters.setAdvertiser('', '');
    };

    // ── KPIs ───────────────────────────────────────────────
    function updateKPIs(s) {
        document.getElementById('kpiTotalAds').textContent = formatNumber(s.total_ads);
        document.getElementById('kpiActiveAds').textContent = formatNumber(s.active_ads);
        document.getElementById('kpiNewAds').textContent = formatNumber(s.new_ads_period || s.new_ads_7d || 0);
        document.getElementById('kpiTotalViews').textContent = formatNumber(s.total_views || 0);
        document.getElementById('kpiTotalApps').textContent = formatNumber(s.total_apps || 0);
        document.getElementById('kpiTotalVideos').textContent = formatNumber(s.total_videos || 0);
        document.getElementById('kpiAdvertisers').textContent = formatNumber(s.total_advertisers || 0);
        document.getElementById('kpiCountries').textContent = formatNumber(s.total_countries || 0);

        // Sub-labels
        var inactPct = s.total_ads > 0 ? ((s.active_ads / s.total_ads) * 100).toFixed(0) : 0;
        document.getElementById('kpiActiveAdsSub').innerHTML = '<small class="text-muted">' + inactPct + '% of total</small>';
        document.getElementById('kpiTotalAdsSub').innerHTML = s.new_ads_24h > 0 ? '<small class="text-success"><i class="bi bi-arrow-up"></i> +' + formatNumber(s.new_ads_24h) + ' today</small>' : '';

        var periodLabels = { '1d': 'in 24h', '7d': 'in 7 days', '30d': 'in 30 days', '90d': 'in 90 days', 'all': 'all time' };
        document.getElementById('kpiNewAdsSub').innerHTML = '<small class="text-muted">' + (periodLabels[GlobalFilters.timePeriod] || 'all time') + '</small>';
    }

    // ── Trend Chart (Chart.js) ─────────────────────────────
    function renderTrendChart(timeline, granularity) {
        if (trendChartInstance) { trendChartInstance.destroy(); trendChartInstance = null; }
        const canvas = document.getElementById('trendChart');
        if (!canvas || !timeline.length) return;

        const labels = timeline.map(t => {
            var p = t.period || t.month;
            if (granularity === 'hour') {
                var h = p.split(' ')[1] || '';
                return h.replace(':00','h');
            }
            if (granularity === 'day') return p.substring(5); // MM-DD
            if (granularity === 'week') return p; // YYYY-Wxx
            // month
            var mn = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var parts = (p || '').split('-');
            return mn[parseInt(parts[1])] || p;
        });
        const values = timeline.map(t => parseInt(t.count) || 0);

        trendChartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'New Ads',
                    data: values,
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    hoverBackgroundColor: 'rgba(67, 97, 238, 0.9)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) { return formatNumber(ctx.raw) + ' ads'; }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { callback: v => formatNumber(v) }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 45, font: { size: 10 } }
                    }
                }
            }
        });
    }

    // ── Type Donut (Chart.js) ──────────────────────────────
    function renderTypeDonut(types) {
        if (typeDonutInstance) { typeDonutInstance.destroy(); typeDonutInstance = null; }
        const canvas = document.getElementById('typeDonut');
        if (!canvas || !types.length) return;

        const colorMap = { video: '#4361ee', image: '#f59e0b', text: '#10b981' };
        const labels = types.map(t => t.ad_type);
        const values = types.map(t => parseInt(t.count));
        const colors = types.map(t => colorMap[t.ad_type] || '#6c757d');
        const total = values.reduce((a,b) => a + b, 0);

        typeDonutInstance = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                                return ctx.label + ': ' + formatNumber(ctx.raw) + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Custom legend
        document.getElementById('typeDonutLegend').innerHTML = types.map(t => {
            var pct = total > 0 ? ((parseInt(t.count) / total) * 100).toFixed(1) : 0;
            return '<div class="d-flex justify-content-between align-items-center mb-1">' +
                '<div><span class="d-inline-block rounded-circle me-2" style="width:10px;height:10px;background:' + (colorMap[t.ad_type]||'#6c757d') + '"></span>' +
                '<span class="text-capitalize small">' + t.ad_type + '</span></div>' +
                '<span class="small fw-bold">' + formatNumber(t.count) + ' <span class="text-muted">(' + pct + '%)</span></span></div>';
        }).join('');
    }

    // ── Status Chart ───────────────────────────────────────
    function renderStatusChart(statuses, total) {
        const container = document.getElementById('statusChart');
        if (!statuses.length) { container.innerHTML = '<small class="text-muted">No data</small>'; return; }
        container.innerHTML = statuses.map(s => {
            var count = parseInt(s.count);
            var pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
            var color = s.status === 'active' ? 'var(--ai-success)' : 'var(--ai-danger)';
            return '<div class="d-flex align-items-center mb-2">' +
                '<div style="width:80px"><span class="badge" style="background:' + color + '">' + s.status + '</span></div>' +
                '<div class="flex-grow-1 mx-2"><div class="progress" style="height:8px;border-radius:4px">' +
                '<div class="progress-bar" style="width:' + pct + '%;background:' + color + ';border-radius:4px"></div></div></div>' +
                '<div style="width:80px" class="text-end"><small class="fw-bold">' + formatNumber(count) + '</small> <small class="text-muted">(' + pct + '%)</small></div></div>';
        }).join('');
    }

    // ── Top Apps ───────────────────────────────────────────
    window.setTopAppsSort = function(sort) {
        topAppsSort = sort;
        document.querySelectorAll('.top-apps-sort').forEach(b => b.classList.toggle('active', b.dataset.sort === sort));
        if (cachedData) renderTopApps(cachedData.top_apps || []);
    };

    function renderTopApps(apps) {
        const container = document.getElementById('topApps');
        if (!apps.length) { container.innerHTML = '<div class="text-center text-muted py-3">No apps yet</div>'; return; }

        var sorted = [...apps].sort((a, b) => {
            if (topAppsSort === 'views') return (parseInt(b.total_views)||0) - (parseInt(a.total_views)||0);
            return (parseInt(b.ad_count)||0) - (parseInt(a.ad_count)||0);
        });

        const platIcons = { ios: 'bi-apple', playstore: 'bi-google-play' };
        const platColors = { ios: 'bg-dark', playstore: 'bg-success' };

        container.innerHTML = sorted.slice(0, 10).map((app, i) => {
            var metric = topAppsSort === 'views' ? formatNumber(app.total_views || 0) + ' views' : formatNumber(app.ad_count) + ' ads';
            var iconHtml = app.icon_url ? '<img src="' + escapeHtml(app.icon_url) + '" class="rounded me-2" style="width:28px;height:28px;object-fit:cover" onerror="this.style.display=\'none\'">' : '';
            var storeLink = app.store_url && app.store_url !== 'not_found' ? '<a href="' + escapeHtml(app.store_url) + '" target="_blank" class="ms-1 text-muted" onclick="event.stopPropagation();event.preventDefault();window.open(this.href)" title="Open in Store"><i class="bi bi-box-arrow-up-right" style="font-size:.7rem"></i></a>' : '';
            return '<a href="app_profile.php?id=' + app.id + '" class="d-flex justify-content-between align-items-center text-decoration-none text-dark p-2 rounded top-entity-row">' +
                '<div class="d-flex align-items-center">' +
                '<span class="badge bg-light text-dark me-2" style="width:24px">' + (i+1) + '</span>' +
                iconHtml +
                '<span class="badge ' + (platColors[app.store_platform]||'bg-info') + ' me-2"><i class="bi ' + (platIcons[app.store_platform]||'bi-globe') + '"></i></span>' +
                '<div><span class="small fw-bold">' + escapeHtml(app.product_name) + '</span>' + storeLink +
                (app.rating ? '<br><small class="text-warning"><i class="bi bi-star-fill"></i> ' + parseFloat(app.rating).toFixed(1) + '</small>' : '') +
                '</div></div>' +
                '<span class="badge bg-warning text-dark">' + metric + '</span></a>';
        }).join('');
    }

    // ── Top Videos ─────────────────────────────────────────
    window.setTopVidSort = function(sort) {
        topVidSort = sort;
        document.querySelectorAll('.top-vid-sort').forEach(b => b.classList.toggle('active', b.dataset.sort === sort));
        if (cachedData) renderTopVideos(cachedData.top_videos || []);
    };

    function renderTopVideos(videos) {
        const container = document.getElementById('topVideos');
        if (!videos.length) { container.innerHTML = '<div class="text-center text-muted py-3">No videos yet</div>'; return; }

        var sorted = [...videos].sort((a, b) => {
            if (topVidSort === 'likes') return (parseInt(b.like_count)||0) - (parseInt(a.like_count)||0);
            return (parseInt(b.view_count)||0) - (parseInt(a.view_count)||0);
        });

        container.innerHTML = sorted.map((v, i) => {
            var metric = topVidSort === 'likes' ? formatNumber(v.like_count||0) + ' likes' : formatNumber(v.view_count||0) + ' views';
            var vid = v.video_id || '';
            var thumb = v.thumbnail_url || (vid ? 'https://i.ytimg.com/vi/' + vid + '/default.jpg' : '');
            return '<a href="youtube_profile.php?id=' + encodeURIComponent(vid) + '" class="d-flex align-items-center text-decoration-none text-dark p-2 rounded top-entity-row">' +
                '<span class="badge bg-light text-dark me-2" style="width:24px">' + (i+1) + '</span>' +
                (thumb ? '<img src="' + escapeHtml(thumb) + '" class="rounded me-2" style="width:48px;height:36px;object-fit:cover">' : '') +
                '<div class="flex-grow-1 me-2"><div class="small fw-bold text-truncate" style="max-width:180px">' + escapeHtml(v.title||'Untitled') + '</div>' +
                '<small class="text-muted">' + escapeHtml(v.channel_name||'') + '</small></div>' +
                '<span class="badge bg-danger">' + metric + '</span></a>';
        }).join('');
    }

    // ── Country Bar Chart (Chart.js) ──────────────────────
    function renderCountryChart(countries) {
        if (countryChartInstance) { countryChartInstance.destroy(); countryChartInstance = null; }
        const canvas = document.getElementById('countryChart');
        if (!canvas || !countries.length) return;

        const labels = countries.map(c => {
            var flag = countryFlag(c.country);
            var name = countryName(c.country);
            return flag + ' ' + name;
        });
        const values = countries.map(c => parseInt(c.ad_count) || 0);

        countryChartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: 'rgba(6, 182, 212, 0.7)',
                    borderColor: 'rgba(6, 182, 212, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => formatNumber(ctx.raw) + ' ads' } }
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: v => formatNumber(v) } },
                    y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                }
            }
        });
    }

    // ── Video Cards ────────────────────────────────────────
    function renderVideoCards(videos) {
        const container = document.getElementById('videoCards');
        if (!videos.length) { container.innerHTML = '<div class="text-center text-muted py-3 w-100">No YouTube videos yet</div>'; return; }

        container.innerHTML = videos.slice(0, 6).map(v => {
            var vid = v.video_id || '';
            var thumb = v.thumbnail_url || (vid ? 'https://i.ytimg.com/vi/' + vid + '/mqdefault.jpg' : '');
            var dur = v.duration || '';
            return '<div class="col-md-4 col-lg-2 mb-2">' +
                '<a href="youtube_profile.php?id=' + encodeURIComponent(vid) + '" class="card text-decoration-none text-dark h-100 top-entity-row" style="overflow:hidden">' +
                '<div style="position:relative">' +
                (thumb ? '<img src="' + escapeHtml(thumb) + '" class="card-img-top" style="aspect-ratio:16/9;object-fit:cover">' : '') +
                (dur ? '<span class="position-absolute bottom-0 end-0 badge bg-dark m-1" style="font-size:.65rem">' + escapeHtml(dur) + '</span>' : '') +
                '<span class="position-absolute top-0 start-0 badge bg-danger m-1" style="font-size:.65rem"><i class="bi bi-eye me-1"></i>' + formatNumber(v.view_count||0) + '</span>' +
                '</div>' +
                '<div class="card-body p-2">' +
                '<div class="small fw-bold text-truncate">' + escapeHtml(v.title||'Untitled') + '</div>' +
                '<small class="text-muted text-truncate d-block">' + escapeHtml(v.channel_name||'') + '</small>' +
                '<div class="d-flex gap-2 mt-1">' +
                '<small class="text-muted"><i class="bi bi-hand-thumbs-up"></i> ' + formatNumber(v.like_count||0) + '</small>' +
                '<small class="text-muted"><i class="bi bi-chat"></i> ' + formatNumber(v.comment_count||0) + '</small>' +
                '</div></div></a></div>';
        }).join('');
    }

    // ── Top Advertisers ────────────────────────────────────
    function renderTopAdvertisers(advertisers) {
        const container = document.getElementById('topAdvertisers');
        if (!advertisers.length) { container.innerHTML = '<div class="text-center text-muted py-3">No advertisers</div>'; return; }
        container.innerHTML = advertisers.map((a, i) =>
            '<a href="advertiser_profile.php?id=' + encodeURIComponent(a.advertiser_id) + '" class="d-flex justify-content-between align-items-center text-decoration-none text-dark p-2 rounded top-entity-row">' +
            '<div class="d-flex align-items-center">' +
            '<span class="badge bg-light text-dark me-2" style="width:24px">' + (i+1) + '</span>' +
            '<div><div class="fw-bold small">' + escapeHtml(a.name || a.advertiser_id) + '</div>' +
            '<small class="text-muted">' + formatNumber(a.active_ads||0) + ' active</small></div></div>' +
            '<span class="badge bg-primary">' + formatNumber(a.total_ads) + ' ads</span></a>'
        ).join('');
    }

    // ── Activity Table ─────────────────────────────────────
    function renderActivityTable(activities) {
        const tbody = document.getElementById('activityTable');
        if (!activities.length) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No recent activity</td></tr>'; return; }
        tbody.innerHTML = activities.map(a =>
            '<tr><td class="text-truncate" style="max-width:120px"><small class="text-muted">' + escapeHtml((a.creative_id||'').substring(0,16)) + '...</small></td>' +
            '<td><a href="advertiser_profile.php?id=' + encodeURIComponent(a.advertiser_id) + '" class="text-decoration-none small">' + escapeHtml(a.advertiser_name||a.advertiser_id) + '</a></td>' +
            '<td class="text-truncate" style="max-width:200px">' + escapeHtml(a.headline||'-') + '</td>' +
            '<td>' + typeBadge(a.ad_type) + '</td>' +
            '<td>' + statusBadge(a.status) + '</td>' +
            '<td><small>' + formatDate(a.last_seen) + '</small></td></tr>'
        ).join('');
    }

})();
</script>

<style>
.top-entity-row { transition: background-color 0.15s; }
.top-entity-row:hover { background-color: rgba(67, 97, 238, 0.05); }
.kpi-sub { font-size: .75rem; margin-top: 2px; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
#contextBanner { border-left: 4px solid var(--ai-primary); }
</style>

<?php require_once 'includes/footer.php'; ?>
