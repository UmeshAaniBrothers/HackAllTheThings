<?php require_once 'includes/header.php'; ?>

<!-- Context Banner (shown when advertiser selected) -->
<div id="contextBanner" class="alert alert-primary d-flex justify-content-between align-items-center mb-2 py-2" style="display:none !important">
    <div>
        <i class="bi bi-building me-1"></i>Showing: <strong id="contextAdvName"></strong>
        <a href="#" id="contextProfileLink" class="ms-2 btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem"><i class="bi bi-person-lines-fill me-1"></i>Profile</a>
    </div>
    <button class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:.75rem" onclick="clearAdvertiserFilter()"><i class="bi bi-x-lg"></i></button>
</div>

<!-- KPI Cards — compact 2-row grid on mobile, single row on desktop -->
<div class="row g-2 mb-3" id="kpiRow">
    <div class="col-6 col-md-3 col-xl">
        <div class="kpi-card-v2">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-v2 bg-primary bg-opacity-10 text-primary"><i class="bi bi-collection"></i></div>
                <div class="ms-2">
                    <div class="kpi-value-v2" id="kpiTotalAds">-</div>
                    <div class="kpi-label-v2">Total Ads</div>
                </div>
            </div>
            <div class="kpi-sub-v2" id="kpiTotalAdsSub"></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="kpi-card-v2">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-v2 bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
                <div class="ms-2">
                    <div class="kpi-value-v2" id="kpiActiveAds">-</div>
                    <div class="kpi-label-v2">Active</div>
                </div>
            </div>
            <div class="kpi-sub-v2" id="kpiActiveAdsSub"></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="kpi-card-v2">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-v2 bg-danger bg-opacity-10 text-danger"><i class="bi bi-plus-circle"></i></div>
                <div class="ms-2">
                    <div class="kpi-value-v2" id="kpiNewAds">-</div>
                    <div class="kpi-label-v2">New Ads</div>
                </div>
            </div>
            <div class="kpi-sub-v2" id="kpiNewAdsSub"></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="kpi-card-v2">
            <div class="d-flex align-items-center">
                <div class="kpi-icon-v2" style="background:rgba(114,9,183,.1);color:#7209b7"><i class="bi bi-eye"></i></div>
                <div class="ms-2">
                    <div class="kpi-value-v2" id="kpiTotalViews">-</div>
                    <div class="kpi-label-v2">Views</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-3 col-md col-xl">
        <div class="kpi-card-v2 kpi-mini">
            <div class="kpi-value-v2 text-warning" id="kpiTotalApps">-</div>
            <div class="kpi-label-v2">Apps</div>
        </div>
    </div>
    <div class="col-3 col-md col-xl">
        <div class="kpi-card-v2 kpi-mini">
            <div class="kpi-value-v2 text-danger" id="kpiTotalVideos">-</div>
            <div class="kpi-label-v2">Videos</div>
        </div>
    </div>
    <div class="col-3 col-md col-xl">
        <div class="kpi-card-v2 kpi-mini">
            <div class="kpi-value-v2" style="color:#8b5cf6" id="kpiAdvertisers">-</div>
            <div class="kpi-label-v2">Advertisers</div>
        </div>
    </div>
    <div class="col-3 col-md col-xl">
        <div class="kpi-card-v2 kpi-mini">
            <div class="kpi-value-v2" style="color:#06b6d4" id="kpiCountries">-</div>
            <div class="kpi-label-v2">Countries</div>
        </div>
    </div>
</div>

<!-- Row 1: Trend + Type/Status -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="dash-card">
            <div class="dash-card-header">
                <h6 class="mb-0"><i class="bi bi-graph-up me-1"></i>Ad Trend</h6>
            </div>
            <div style="position:relative;height:180px">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6 class="mb-0"><i class="bi bi-pie-chart me-1"></i>Breakdown</h6>
            </div>
            <div class="row g-0">
                <div class="col-6">
                    <div style="position:relative;height:110px;margin:0 auto;max-width:120px">
                        <canvas id="typeDonut"></canvas>
                    </div>
                    <div id="typeDonutLegend" class="mt-1"></div>
                </div>
                <div class="col-6">
                    <div class="small fw-semibold text-muted mb-1">Status</div>
                    <div id="statusChart"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Top Apps + Top Videos + Top Countries — all compact -->
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6 class="mb-0"><i class="bi bi-app-indicator me-1"></i>Top Apps</h6>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary btn-xs active top-apps-sort" data-sort="ads" onclick="setTopAppsSort('ads')">Ads</button>
                    <button class="btn btn-outline-primary btn-xs top-apps-sort" data-sort="views" onclick="setTopAppsSort('views')">Views</button>
                </div>
            </div>
            <div id="topApps" class="entity-list"><div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6 class="mb-0"><i class="bi bi-youtube text-danger me-1"></i>Top Videos</h6>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-danger btn-xs active top-vid-sort" data-sort="views" onclick="setTopVidSort('views')">Views</button>
                    <button class="btn btn-outline-danger btn-xs top-vid-sort" data-sort="likes" onclick="setTopVidSort('likes')">Likes</button>
                </div>
            </div>
            <div id="topVideos" class="entity-list"><div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6 class="mb-0"><i class="bi bi-geo-alt me-1"></i>Top Countries</h6>
            </div>
            <div id="countryBars"></div>
        </div>
    </div>
</div>

<!-- Row 3: Top Advertisers + Recent Activity — side by side -->
<div class="row g-3 mb-3">
    <div class="col-lg-4" id="topAdvertisersCol">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6 class="mb-0"><i class="bi bi-people me-1"></i>Top Advertisers</h6>
                <a href="manage.php" class="btn btn-outline-primary btn-xs">All</a>
            </div>
            <div id="topAdvertisers" class="entity-list"><div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div></div></div>
        </div>
    </div>
    <div id="activityCol">
        <div class="dash-card h-100">
            <div class="dash-card-header">
                <h6 class="mb-0"><i class="bi bi-clock-history me-1"></i>Recent Activity</h6>
                <a href="ads_viewer.php" class="btn btn-outline-primary btn-xs">All Ads</a>
            </div>
            <div class="table-responsive" style="max-height:320px;overflow-y:auto">
                <table class="table table-sm table-hover mb-0" style="font-size:.8rem">
                    <thead class="sticky-top bg-white">
                        <tr><th>Creative</th><th>Advertiser</th><th>Headline</th><th>Type</th><th>Status</th><th>Last Seen</th></tr>
                    </thead>
                    <tbody id="activityTable">
                        <tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Pending Banner (compact) -->
<div id="pendingBanner" class="alert alert-info py-2 mb-3" style="display:none;font-size:.85rem">
    <i class="bi bi-hourglass-split me-1"></i><strong id="pendingCount">0</strong> payloads pending.
    <a href="manage.php" class="ms-2 btn btn-info btn-xs">Process</a>
</div>

<script>
(function() {
    'use strict';

    let trendChartInstance = null;
    let typeDonutInstance = null;
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
            renderCountryBars(data.top_countries || []);
            renderTopAdvertisers(data.top_advertisers || []);
            renderActivityTable(data.recent_activity || []);

            const pc = parseInt(data.stats.pending_payloads) || 0;
            document.getElementById('pendingBanner').style.display = pc > 0 ? '' : 'none';
            document.getElementById('pendingCount').textContent = formatNumber(pc);
        } catch(err) {
            console.error('Dashboard error:', err);
        }
    }

    window.addEventListener('globalfilter:change', loadDashboard);
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(loadDashboard, 100);
    });

    // ── Context Banner ─────────────────────────────────────
    function updateContextBanner() {
        const banner = document.getElementById('contextBanner');
        const advCol = document.getElementById('topAdvertisersCol');
        const actCol = document.getElementById('activityCol');
        if (GlobalFilters.advertiserId) {
            banner.style.cssText = '';
            document.getElementById('contextAdvName').textContent = GlobalFilters.advertiserName || GlobalFilters.advertiserId;
            document.getElementById('contextProfileLink').href = 'advertiser_profile.php?id=' + encodeURIComponent(GlobalFilters.advertiserId);
            if (advCol) advCol.style.display = 'none';
            if (actCol) actCol.className = 'col-12';
        } else {
            banner.style.display = 'none';
            if (advCol) advCol.style.display = '';
            if (actCol) actCol.className = 'col-lg-8';
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

        var inactPct = s.total_ads > 0 ? ((s.active_ads / s.total_ads) * 100).toFixed(0) : 0;
        document.getElementById('kpiActiveAdsSub').innerHTML = '<small class="text-muted">' + inactPct + '%</small>';
        document.getElementById('kpiTotalAdsSub').innerHTML = s.new_ads_24h > 0 ? '<small class="text-success"><i class="bi bi-arrow-up"></i>+' + formatNumber(s.new_ads_24h) + '</small>' : '';

        var pl = { '1d': '24h', '7d': '7d', '30d': '30d', '90d': '90d', 'all': 'all' };
        document.getElementById('kpiNewAdsSub').innerHTML = '<small class="text-muted">' + (pl[GlobalFilters.timePeriod] || 'all') + '</small>';
    }

    // ── Trend Chart ────────────────────────────────────────
    function renderTrendChart(timeline, granularity) {
        if (trendChartInstance) { trendChartInstance.destroy(); trendChartInstance = null; }
        const canvas = document.getElementById('trendChart');
        if (!canvas || !timeline.length) return;

        const labels = timeline.map(function(t) {
            var p = t.period || t.month;
            if (granularity === 'hour') return (p.split(' ')[1] || '').replace(':00','h');
            if (granularity === 'day') return p.substring(5);
            if (granularity === 'week') return p;
            var mn = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var parts = (p || '').split('-');
            return mn[parseInt(parts[1])] || p;
        });
        const values = timeline.map(function(t) { return parseInt(t.count) || 0; });

        trendChartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'New Ads',
                    data: values,
                    backgroundColor: 'rgba(67,97,238,0.7)',
                    borderColor: 'rgba(67,97,238,1)',
                    borderWidth: 1,
                    borderRadius: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return formatNumber(ctx.raw) + ' ads'; } } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { callback: function(v) { return formatNumber(v); }, font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { maxRotation: 45, font: { size: 9 } } }
                }
            }
        });
    }

    // ── Type Donut ─────────────────────────────────────────
    function renderTypeDonut(types) {
        if (typeDonutInstance) { typeDonutInstance.destroy(); typeDonutInstance = null; }
        const canvas = document.getElementById('typeDonut');
        if (!canvas || !types.length) return;

        var colorMap = { video: '#4361ee', image: '#f59e0b', text: '#10b981' };
        var labels = types.map(function(t) { return t.ad_type; });
        var values = types.map(function(t) { return parseInt(t.count); });
        var colors = types.map(function(t) { return colorMap[t.ad_type] || '#6c757d'; });
        var total = values.reduce(function(a,b) { return a + b; }, 0);

        typeDonutInstance = new Chart(canvas, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 4 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '60%',
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': ' + formatNumber(ctx.raw); } } } }
            }
        });

        document.getElementById('typeDonutLegend').innerHTML = types.map(function(t) {
            var pct = total > 0 ? ((parseInt(t.count) / total) * 100).toFixed(0) : 0;
            return '<div class="d-flex justify-content-between" style="font-size:.72rem">' +
                '<span><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:' + (colorMap[t.ad_type]||'#6c757d') + '"></span>' + t.ad_type + '</span>' +
                '<span class="fw-bold">' + formatNumber(t.count) + ' <span class="text-muted">(' + pct + '%)</span></span></div>';
        }).join('');
    }

    // ── Status ─────────────────────────────────────────────
    function renderStatusChart(statuses, total) {
        var c = document.getElementById('statusChart');
        if (!statuses.length) { c.innerHTML = '<small class="text-muted">No data</small>'; return; }
        c.innerHTML = statuses.map(function(s) {
            var count = parseInt(s.count);
            var pct = total > 0 ? ((count / total) * 100).toFixed(0) : 0;
            var color = s.status === 'active' ? 'var(--ai-success)' : 'var(--ai-danger)';
            return '<div class="d-flex align-items-center mb-2">' +
                '<span class="badge me-2" style="background:' + color + ';font-size:.65rem;min-width:55px">' + s.status + '</span>' +
                '<div class="flex-grow-1"><div class="progress" style="height:6px"><div class="progress-bar" style="width:' + pct + '%;background:' + color + '"></div></div></div>' +
                '<span class="ms-2" style="font-size:.7rem;min-width:60px;text-align:right"><b>' + formatNumber(count) + '</b> ' + pct + '%</span></div>';
        }).join('');
    }

    // ── Top Apps ───────────────────────────────────────────
    window.setTopAppsSort = function(sort) {
        topAppsSort = sort;
        document.querySelectorAll('.top-apps-sort').forEach(function(b) { b.classList.toggle('active', b.dataset.sort === sort); });
        if (cachedData) renderTopApps(cachedData.top_apps || []);
    };

    function renderTopApps(apps) {
        var c = document.getElementById('topApps');
        if (!apps.length) { c.innerHTML = '<div class="text-center text-muted py-3" style="font-size:.8rem">No apps yet</div>'; return; }

        var sorted = apps.slice().sort(function(a, b) {
            if (topAppsSort === 'views') return (parseInt(b.total_views)||0) - (parseInt(a.total_views)||0);
            return (parseInt(b.ad_count)||0) - (parseInt(a.ad_count)||0);
        });

        c.innerHTML = sorted.slice(0, 7).map(function(app, i) {
            var metric = topAppsSort === 'views' ? formatNumber(app.total_views || 0) : formatNumber(app.ad_count) + ' ads';
            var icon = app.icon_url ? '<img src="' + escapeHtml(app.icon_url) + '" class="rounded me-2" style="width:24px;height:24px;object-fit:cover" onerror="this.style.display=\'none\'">' : '';
            var platIcon = app.store_platform === 'ios' ? '<i class="bi bi-apple text-dark" style="font-size:.65rem"></i>' : '<i class="bi bi-google-play text-success" style="font-size:.65rem"></i>';
            return '<a href="app_profile.php?id=' + app.id + '" class="entity-row">' +
                '<div class="d-flex align-items-center overflow-hidden">' +
                '<span class="rank">' + (i+1) + '</span>' + icon + platIcon +
                '<span class="ms-1 text-truncate fw-semibold" style="max-width:130px">' + escapeHtml(app.product_name) + '</span>' +
                (app.rating ? '<span class="ms-1 text-warning" style="font-size:.6rem"><i class="bi bi-star-fill"></i>' + parseFloat(app.rating).toFixed(1) + '</span>' : '') +
                '</div><span class="metric-badge">' + metric + '</span></a>';
        }).join('');
    }

    // ── Top Videos ─────────────────────────────────────────
    window.setTopVidSort = function(sort) {
        topVidSort = sort;
        document.querySelectorAll('.top-vid-sort').forEach(function(b) { b.classList.toggle('active', b.dataset.sort === sort); });
        if (cachedData) renderTopVideos(cachedData.top_videos || []);
    };

    function renderTopVideos(videos) {
        var c = document.getElementById('topVideos');
        if (!videos.length) { c.innerHTML = '<div class="text-center text-muted py-3" style="font-size:.8rem">No videos yet</div>'; return; }

        var sorted = videos.slice().sort(function(a, b) {
            if (topVidSort === 'likes') return (parseInt(b.like_count)||0) - (parseInt(a.like_count)||0);
            return (parseInt(b.view_count)||0) - (parseInt(a.view_count)||0);
        });

        c.innerHTML = sorted.slice(0, 6).map(function(v, i) {
            var metric = topVidSort === 'likes' ? formatNumber(v.like_count||0) + ' likes' : formatNumber(v.view_count||0);
            var vid = v.video_id || '';
            var thumb = v.thumbnail_url || (vid ? 'https://i.ytimg.com/vi/' + vid + '/default.jpg' : '');
            return '<a href="youtube_profile.php?id=' + encodeURIComponent(vid) + '" class="entity-row">' +
                '<div class="d-flex align-items-center overflow-hidden">' +
                '<span class="rank">' + (i+1) + '</span>' +
                (thumb ? '<img src="' + escapeHtml(thumb) + '" class="rounded me-2" style="width:40px;height:28px;object-fit:cover">' : '') +
                '<div class="text-truncate" style="max-width:140px"><div class="fw-semibold text-truncate" style="font-size:.75rem">' + escapeHtml(v.title||'Untitled') + '</div>' +
                '<div class="text-muted" style="font-size:.65rem">' + escapeHtml(v.channel_name||'') + '</div></div>' +
                '</div><span class="metric-badge bg-danger">' + metric + '</span></a>';
        }).join('');
    }

    // ── Country Bars (pure HTML, no chart) ─────────────────
    function renderCountryBars(countries) {
        var c = document.getElementById('countryBars');
        if (!countries.length) { c.innerHTML = '<div class="text-center text-muted py-3" style="font-size:.8rem">No data</div>'; return; }
        var max = parseInt(countries[0].ad_count) || 1;
        c.innerHTML = countries.slice(0, 10).map(function(co) {
            var count = parseInt(co.ad_count) || 0;
            var pct = (count / max * 100).toFixed(0);
            var flag = countryFlag(co.country);
            var name = countryName(co.country);
            return '<div class="d-flex align-items-center mb-1" style="font-size:.78rem">' +
                '<span style="width:28px;flex-shrink:0">' + flag + '</span>' +
                '<span class="fw-semibold me-2" style="width:30px;flex-shrink:0">' + escapeHtml(co.country) + '</span>' +
                '<div class="flex-grow-1"><div class="progress" style="height:5px"><div class="progress-bar" style="width:' + pct + '%;background:rgba(6,182,212,.7);border-radius:3px"></div></div></div>' +
                '<span class="text-muted ms-2 fw-bold" style="width:50px;text-align:right;font-size:.7rem">' + formatNumber(count) + '</span></div>';
        }).join('');
    }

    // ── Top Advertisers ────────────────────────────────────
    function renderTopAdvertisers(advertisers) {
        var c = document.getElementById('topAdvertisers');
        if (!advertisers.length) { c.innerHTML = '<div class="text-center text-muted py-3" style="font-size:.8rem">No advertisers</div>'; return; }
        c.innerHTML = advertisers.slice(0, 5).map(function(a, i) {
            return '<a href="advertiser_profile.php?id=' + encodeURIComponent(a.advertiser_id) + '" class="entity-row">' +
                '<div class="d-flex align-items-center overflow-hidden">' +
                '<span class="rank">' + (i+1) + '</span>' +
                '<div class="text-truncate"><span class="fw-semibold">' + escapeHtml(a.name || a.advertiser_id) + '</span>' +
                '<br><span class="text-muted" style="font-size:.65rem">' + formatNumber(a.active_ads||0) + ' active</span></div>' +
                '</div><span class="metric-badge">' + formatNumber(a.total_ads) + '</span></a>';
        }).join('');
    }

    // ── Activity Table ─────────────────────────────────────
    function renderActivityTable(activities) {
        var tbody = document.getElementById('activityTable');
        if (!activities.length) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No recent activity</td></tr>'; return; }
        tbody.innerHTML = activities.slice(0, 15).map(function(a) {
            return '<tr>' +
                '<td class="text-truncate" style="max-width:90px"><code style="font-size:.65rem">' + escapeHtml((a.creative_id||'').substring(0,12)) + '</code></td>' +
                '<td><a href="advertiser_profile.php?id=' + encodeURIComponent(a.advertiser_id) + '" class="text-decoration-none">' + escapeHtml(a.advertiser_name||a.advertiser_id||'').substring(0,20) + '</a></td>' +
                '<td class="text-truncate" style="max-width:160px">' + escapeHtml(a.headline||'-') + '</td>' +
                '<td>' + typeBadge(a.ad_type) + '</td>' +
                '<td>' + statusBadge(a.status) + '</td>' +
                '<td><small class="text-muted">' + formatDate(a.last_seen) + '</small></td></tr>';
        }).join('');
    }

})();
</script>

<style>
/* V2 KPI Cards — compact */
.kpi-card-v2 {
    background: #fff;
    border-radius: 10px;
    padding: 12px 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    height: 100%;
    transition: transform .15s, box-shadow .15s;
}
.kpi-card-v2:hover { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(0,0,0,.1); }
.kpi-icon-v2 { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.kpi-value-v2 { font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
.kpi-label-v2 { font-size: .68rem; color: #6c757d; text-transform: uppercase; letter-spacing: .4px; font-weight: 600; }
.kpi-sub-v2 { margin-top: 2px; font-size: .68rem; }
.kpi-mini { text-align: center; padding: 10px 8px; }
.kpi-mini .kpi-value-v2 { font-size: 1.15rem; }

/* Dashboard Cards */
.dash-card {
    background: #fff;
    border-radius: 10px;
    padding: 14px 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.dash-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.dash-card-header h6 { font-weight: 700; color: var(--ai-dark); font-size: .85rem; }

/* Tiny buttons */
.btn-xs { font-size: .68rem; padding: 1px 8px; border-radius: 4px; }

/* Entity rows — compact list items */
.entity-list { max-height: 310px; overflow-y: auto; }
.entity-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 8px;
    border-radius: 6px;
    text-decoration: none;
    color: inherit;
    font-size: .78rem;
    transition: background .12s;
}
.entity-row:hover { background: rgba(67,97,238,.04); color: inherit; }
.entity-row .rank {
    width: 18px; height: 18px; border-radius: 4px; background: #f0f2f5;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .6rem; font-weight: 700; color: #6c757d; margin-right: 6px; flex-shrink: 0;
}
.metric-badge {
    font-size: .65rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;
    background: rgba(67,97,238,.1); color: var(--ai-primary); white-space: nowrap;
}
.metric-badge.bg-danger { background: rgba(231,29,54,.1) !important; color: var(--ai-danger); }

/* Context banner */
#contextBanner { border-left: 3px solid var(--ai-primary); font-size: .85rem; }

/* Scrollbar for entity lists */
.entity-list::-webkit-scrollbar { width: 4px; }
.entity-list::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 2px; }

/* Override for table */
.table-sm td, .table-sm th { padding: .35rem .5rem; font-size: .78rem; }
</style>

<?php require_once 'includes/footer.php'; ?>
