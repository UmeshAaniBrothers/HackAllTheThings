<?php require_once 'includes/header.php'; ?>

<style>
    .adv-header-card { border-left: 4px solid var(--ai-primary); }
    .adv-header-card .adv-id { font-family: 'SFMono-Regular', Consolas, monospace; font-size: .82rem; color: #6c757d; letter-spacing: .02em; }
    .adv-header-card .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .adv-header-card .status-dot.active { background: var(--ai-success); }
    .adv-header-card .status-dot.inactive { background: var(--ai-danger); }

    .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
    @media (max-width: 991px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
    .kpi-card { transition: transform .15s, box-shadow .15s; }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.08); }

    .type-progress-row { display: flex; align-items: center; gap: .75rem; margin-bottom: .65rem; }
    .type-progress-row .type-label { min-width: 60px; }
    .type-progress-row .progress { height: 22px; flex: 1; background: #e9ecef; border-radius: 4px; overflow: hidden; position: relative; }
    .type-progress-row .progress .bar-active { background: var(--ai-success); height: 100%; display: inline-block; }
    .type-progress-row .progress .bar-total { background: var(--ai-primary); opacity: .35; height: 100%; display: inline-block; }
    .type-progress-row .progress .bar-label { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); font-size: .72rem; font-weight: 600; color: #333; }

    .timeline-chart { display: flex; align-items: flex-end; gap: 2px; height: 160px; padding-top: 20px; }
    .timeline-bar-group { flex: 1; display: flex; flex-direction: column; align-items: center; }
    .timeline-bar-group .bar-stack { width: 100%; display: flex; flex-direction: column-reverse; }
    .timeline-bar-group .bar-segment { min-height: 0; border-radius: 2px 2px 0 0; transition: opacity .2s; }
    .timeline-bar-group .bar-segment:hover { opacity: .8; }
    .timeline-bar-group .bar-segment.video { background: var(--ai-danger); }
    .timeline-bar-group .bar-segment.image { background: var(--ai-success); }
    .timeline-bar-group .bar-segment.text { background: var(--ai-primary); }
    .timeline-bar-group .month-label { font-size: .6rem; color: #999; margin-top: 4px; white-space: nowrap; }
    .timeline-bar-group .count-label { font-size: .6rem; color: #666; margin-bottom: 2px; }
    .timeline-legend { display: flex; gap: 1rem; margin-top: .5rem; }
    .timeline-legend span { font-size: .75rem; display: flex; align-items: center; gap: 4px; }
    .timeline-legend .dot { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }

    .top-ad-row { display: flex; align-items: center; gap: .75rem; padding: .65rem 0; border-bottom: 1px solid #f0f0f0; }
    .top-ad-row:last-child { border-bottom: none; }
    .top-ad-row .rank { width: 26px; height: 26px; border-radius: 50%; background: var(--ai-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; flex-shrink: 0; }
    .top-ad-row .thumb { width: 80px; height: 45px; border-radius: 4px; object-fit: cover; background: #1a1a2e; flex-shrink: 0; }
    .top-ad-row .meta { flex: 1; min-width: 0; }
    .top-ad-row .meta .headline { font-size: .85rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .top-ad-row .views { font-size: .8rem; color: var(--ai-info); font-weight: 600; white-space: nowrap; }

    .app-sidebar-card { display: flex; align-items: center; gap: .65rem; padding: .6rem; border-radius: 6px; transition: background .15s; text-decoration: none; color: inherit; }
    .app-sidebar-card:hover { background: #f8f9fa; text-decoration: none; color: inherit; }
    .app-sidebar-card .app-icon { width: 44px; height: 44px; border-radius: 10px; object-fit: cover; flex-shrink: 0; }
    .app-sidebar-card .app-icon-placeholder { width: 44px; height: 44px; border-radius: 10px; background: #e9ecef; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .app-sidebar-card .app-meta { min-width: 0; flex: 1; }
    .app-sidebar-card .app-meta .name { font-weight: 600; font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .app-sidebar-card .app-meta .sub { font-size: .72rem; color: #999; }
    .app-sidebar-card .ad-count-badge { background: var(--ai-primary); color: #fff; border-radius: 12px; padding: 2px 8px; font-size: .7rem; font-weight: 600; flex-shrink: 0; }

    .dev-ecosystem-card { background: #fafbfc; transition: box-shadow .15s; }
    .dev-ecosystem-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .dev-icon-circle { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--ai-primary), #6366f1); color: #fff; display: flex; align-items: center; justify-content: center; font-size: .9rem; flex-shrink: 0; }

    .platform-bar { display: flex; align-items: center; gap: .5rem; margin-bottom: .5rem; }
    .platform-bar .plat-label { min-width: 80px; font-size: .8rem; font-weight: 500; }
    .platform-bar .plat-fill { height: 18px; border-radius: 3px; background: var(--ai-info); transition: width .3s; }
    .platform-bar .plat-count { font-size: .75rem; color: #666; margin-left: .25rem; }

    .competitor-row { display: flex; align-items: center; justify-content: space-between; padding: .5rem 0; border-bottom: 1px solid #f5f5f5; }
    .competitor-row:last-child { border-bottom: none; }
    .competitor-row a { font-size: .85rem; font-weight: 500; text-decoration: none; }
    .competitor-row .comp-count { font-size: .75rem; color: #999; }

    .country-hbar { display: flex; align-items: center; gap: .5rem; margin-bottom: .4rem; }
    .country-hbar .country-code { min-width: 30px; font-size: .8rem; font-weight: 600; }
    .country-hbar .country-fill { height: 16px; border-radius: 3px; background: var(--ai-warning); transition: width .3s; }
    .country-hbar .country-count { font-size: .72rem; color: #666; margin-left: .25rem; }

    .yt-video-card { position: relative; border-radius: 8px; overflow: hidden; transition: transform .15s, box-shadow .15s; }
    .yt-video-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,.12); }
    .yt-video-card .thumb-wrap { position: relative; aspect-ratio: 16/9; background: #000; overflow: hidden; }
    .yt-video-card .thumb-wrap img { width: 100%; height: 100%; object-fit: cover; }
    .yt-video-card .play-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); font-size: 2.5rem; color: rgba(255,255,255,.85); text-shadow: 0 2px 8px rgba(0,0,0,.4); opacity: 0; transition: opacity .2s; }
    .yt-video-card:hover .play-overlay { opacity: 1; }
    .yt-video-card .duration-badge { position: absolute; bottom: 6px; right: 6px; background: rgba(0,0,0,.8); color: #fff; font-size: .7rem; padding: 1px 6px; border-radius: 3px; }
    .yt-video-card .yt-meta { padding: .5rem .65rem; }
    .yt-video-card .yt-title { font-size: .82rem; font-weight: 500; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .yt-video-card .yt-sub { font-size: .72rem; color: #999; margin-top: .25rem; }

    .ad-card { border-radius: 8px; overflow: hidden; transition: transform .15s, box-shadow .15s; }
    .ad-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,.1); }
    .ad-card .ad-thumb { position: relative; aspect-ratio: 16/9; background: #1a1a2e; overflow: hidden; }
    .ad-card .ad-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .ad-card .ad-thumb .play-icon { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); font-size: 2rem; color: rgba(255,255,255,.8); }
    .ad-card .ad-thumb .views-badge { position: absolute; bottom: 5px; right: 5px; }
    .ad-card .ad-body { padding: .6rem; }
    .ad-card .ad-headline { font-size: .82rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ad-card .ad-desc { font-size: .72rem; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ad-card .ad-links { display: flex; gap: .35rem; flex-wrap: wrap; margin-top: .35rem; }
    .ad-card .ad-dates { font-size: .7rem; color: #aaa; margin-top: .25rem; }
    .ad-card .ad-countries { font-size: .68rem; color: #999; margin-top: .2rem; }

    .section-title { font-size: 1rem; font-weight: 600; margin-bottom: .75rem; }
    .section-title i { color: var(--ai-primary); }
</style>

<div id="advProfileContent">
    <div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>
</div>

<script>
(function() {
    'use strict';

    const params = new URLSearchParams(window.location.search);
    const advertiserId = params.get('id');

    if (!advertiserId) {
        document.getElementById('advProfileContent').innerHTML = '<div class="text-center py-5 text-danger">Missing advertiser ID parameter.</div>';
        return;
    }

    let currentPage = 1;

    async function load(page) {
        currentPage = page || 1;
        try {
            const data = await fetchAPI('advertiser_profile.php', { id: advertiserId, page: currentPage });
            if (!data.success) throw new Error(data.error || 'Failed to load advertiser profile');
            render(data);
        } catch (err) {
            document.getElementById('advProfileContent').innerHTML =
                '<div class="text-center py-5 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>' + escapeHtml(err.message) + '</div>';
        }
    }

    function render(data) {
        const adv = data.advertiser;
        const stats = data.stats;
        const container = document.getElementById('advProfileContent');
        const transparencyUrl = 'https://adstransparency.google.com/advertiser/' + encodeURIComponent(adv.advertiser_id);

        let html = '';

        // ── Breadcrumb ──
        html += `<nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door me-1"></i>Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">${escapeHtml(adv.name || adv.advertiser_id)}</li>
            </ol>
        </nav>`;

        // ── Header Card ──
        html += `<div class="card adv-header-card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h3 class="mb-0"><i class="bi bi-person-badge me-2"></i>${escapeHtml(adv.name || adv.advertiser_id)}</h3>
                            <span class="badge ${adv.status === 'active' ? 'badge-active' : 'badge-inactive'} ms-1">
                                <span class="status-dot ${adv.status === 'active' ? 'active' : 'inactive'} me-1"></span>${escapeHtml(adv.status || 'unknown')}
                            </span>
                        </div>
                        <div class="adv-id mb-2"><code>${escapeHtml(adv.advertiser_id)}</code></div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="${escapeHtml(transparencyUrl)}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-google me-1"></i>Google Ads Transparency
                            </a>
                            <a href="ads_viewer.php#advertiser_id=${encodeURIComponent(adv.advertiser_id)}" class="btn btn-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>View Ads
                            </a>
                        </div>
                    </div>
                    <div class="text-end text-muted small">
                        ${adv.last_fetched_at ? `<div><i class="bi bi-clock-history me-1"></i>Last fetched: <strong>${formatDate(adv.last_fetched_at)}</strong></div>` : ''}
                        ${adv.fetch_count ? `<div><i class="bi bi-arrow-repeat me-1"></i>${formatNumber(adv.fetch_count)} fetches</div>` : ''}
                    </div>
                </div>
            </div>
        </div>`;

        // ── 8 KPI Cards ──
        const kpis = [
            { label: 'Total Ads', value: formatNumber(stats.total), color: 'text-primary', icon: 'bi-collection' },
            { label: 'Active', value: formatNumber(stats.active), color: 'text-success', icon: 'bi-check-circle' },
            { label: 'Inactive', value: formatNumber(stats.inactive), color: 'text-danger', icon: 'bi-x-circle' },
            { label: 'Apps', value: formatNumber(data.apps ? data.apps.length : 0), color: 'text-warning', icon: 'bi-app-indicator' },
            { label: 'Videos', value: formatNumber(stats.video_count || 0), color: 'text-danger', icon: 'bi-play-btn' },
            { label: 'Total Views', value: formatNumber(stats.total_views || 0), color: 'text-info', icon: 'bi-eye' },
            { label: 'Countries', value: formatNumber(data.countries ? data.countries.length : 0), color: 'text-primary', icon: 'bi-geo-alt' },
            { label: 'Avg Views', value: formatNumber(stats.avg_views || 0), color: 'text-secondary', icon: 'bi-bar-chart-line' }
        ];
        html += `<div class="kpi-grid mb-4">`;
        kpis.forEach(function(k) {
            html += `<div class="card kpi-card p-3">
                <div class="kpi-label"><i class="bi ${k.icon} me-1"></i>${k.label}</div>
                <div class="kpi-value ${k.color}">${k.value}</div>
            </div>`;
        });
        html += `</div>`;

        // ── Two-Column Layout ──
        html += `<div class="row mb-4">`;

        // ── LEFT COLUMN (col-8) ──
        html += `<div class="col-lg-8 mb-3">`;

        // Ad Type Breakdown with progress bars (active vs total)
        if (data.ad_types && data.ad_types.length > 0) {
            const maxTypeCount = Math.max(...data.ad_types.map(function(t) { return parseInt(t.count) || 0; }));
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="section-title"><i class="bi bi-pie-chart me-2"></i>Ad Type Breakdown</h5>`;
            data.ad_types.forEach(function(t) {
                const total = parseInt(t.count) || 0;
                const active = parseInt(t.active_count) || 0;
                const pctTotal = maxTypeCount > 0 ? (total / maxTypeCount * 100) : 0;
                const pctActive = total > 0 ? (active / total * 100) : 0;
                html += `<div class="type-progress-row">
                    <span class="type-label"><span class="badge badge-${escapeHtml(t.ad_type)}">${escapeHtml(t.ad_type)}</span></span>
                    <div class="progress" style="position:relative">
                        <span class="bar-total" style="width:${pctTotal}%"></span>
                        <span class="bar-active" style="width:${pctTotal * pctActive / 100}%;position:absolute;left:0;top:0"></span>
                        <span class="bar-label">${active} active / ${total} total</span>
                    </div>
                </div>`;
            });
            html += `</div></div>`;
        }

        // Activity Timeline - Stacked bar chart
        if (data.timeline && data.timeline.length > 0) {
            const maxCount = Math.max(...data.timeline.map(function(t) { return parseInt(t.count) || 0; }));
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="section-title"><i class="bi bi-graph-up me-2"></i>Activity Timeline</h5>
                <div class="timeline-chart">`;
            data.timeline.forEach(function(t) {
                const total = parseInt(t.count) || 0;
                const videos = parseInt(t.videos) || 0;
                const images = parseInt(t.images) || 0;
                const texts = parseInt(t.texts) || 0;
                const scale = maxCount > 0 ? 130 / maxCount : 0;
                html += `<div class="timeline-bar-group" title="${escapeHtml(t.month)}: ${total} ads (${videos}V / ${images}I / ${texts}T)">
                    <span class="count-label">${total}</span>
                    <div class="bar-stack" style="height:${Math.max(total * scale, 3)}px">
                        ${videos > 0 ? '<div class="bar-segment video" style="height:' + (videos * scale) + 'px"></div>' : ''}
                        ${images > 0 ? '<div class="bar-segment image" style="height:' + (images * scale) + 'px"></div>' : ''}
                        ${texts > 0 ? '<div class="bar-segment text" style="height:' + (texts * scale) + 'px"></div>' : ''}
                    </div>
                    <span class="month-label">${escapeHtml(t.month.substring(5))}</span>
                </div>`;
            });
            html += `</div>
                <div class="timeline-legend">
                    <span><span class="dot" style="background:var(--ai-danger)"></span>Video</span>
                    <span><span class="dot" style="background:var(--ai-success)"></span>Image</span>
                    <span><span class="dot" style="background:var(--ai-primary)"></span>Text</span>
                </div>
            </div></div>`;
        }

        // Top 5 Performing Ads
        if (data.top_ads && data.top_ads.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="section-title"><i class="bi bi-trophy me-2"></i>Top Performing Ads</h5>`;
            data.top_ads.slice(0, 5).forEach(function(ad, idx) {
                const ytId = ad.youtube_url ? extractYouTubeId(ad.youtube_url) : null;
                const thumbSrc = ad.preview_image || (ytId ? 'https://i.ytimg.com/vi/' + ytId + '/hqdefault.jpg' : '');
                html += `<div class="top-ad-row">
                    <span class="rank">${idx + 1}</span>
                    ${thumbSrc
                        ? '<img src="' + escapeHtml(thumbSrc) + '" class="thumb" loading="lazy" alt="">'
                        : '<div class="thumb d-flex align-items-center justify-content-center" style="background:#e9ecef"><i class="bi bi-image text-muted"></i></div>'}
                    <div class="meta">
                        <div class="headline">${escapeHtml(ad.headline || 'Untitled')}${ad.headline_source === 'youtube' ? ' <span class="badge bg-danger bg-opacity-75" style="font-size:.55rem;vertical-align:middle"><i class="bi bi-youtube"></i> YT</span>' : ''}</div>
                        <div class="d-flex gap-1 mt-1">
                            <span class="badge badge-${escapeHtml(ad.ad_type || 'text')}">${escapeHtml(ad.ad_type || 'text')}</span>
                            <span class="badge ${ad.status === 'active' ? 'badge-active' : 'badge-inactive'}">${escapeHtml(ad.status || 'unknown')}</span>
                        </div>
                    </div>
                    <span class="views"><i class="bi bi-eye me-1"></i>${formatNumber(ad.view_count || 0)}</span>
                </div>`;
            });
            html += `</div></div>`;
        }

        html += `</div>`; // end left col

        // ── RIGHT COLUMN (col-4) ──
        html += `<div class="col-lg-4 mb-3">`;

        // Apps cards
        if (data.apps && data.apps.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="section-title"><i class="bi bi-app-indicator me-2"></i>Apps (${data.apps.length})</h5>`;
            data.apps.forEach(function(app) {
                const platIcon = app.store_platform === 'ios' ? 'bi-apple' : 'bi-google-play';
                const platLabel = app.store_platform === 'ios' ? 'iOS' : 'Play';
                const ratingStars = app.rating ? parseFloat(app.rating).toFixed(1) : null;
                html += `<a href="app_profile.php?id=${encodeURIComponent(app.product_id)}" class="app-sidebar-card">
                    ${app.icon_url
                        ? '<img src="' + escapeHtml(app.icon_url) + '" class="app-icon" loading="lazy" alt="">'
                        : '<div class="app-icon-placeholder"><i class="bi bi-app" style="font-size:1.2rem;color:#aaa"></i></div>'}
                    <div class="app-meta">
                        <div class="name">${escapeHtml(app.product_name)}</div>
                        <div class="sub">
                            <i class="bi ${platIcon} me-1"></i>${platLabel}
                            ${ratingStars ? ' &middot; <i class="bi bi-star-fill text-warning"></i> ' + ratingStars : ''}
                        </div>
                    </div>
                    <span class="ad-count-badge">${formatNumber(app.ad_count)} ads</span>
                </a>`;
            });
            html += `</div></div>`;
        }

        // Developer Ecosystem
        if (data.developers && data.developers.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="section-title"><i class="bi bi-people-fill me-2"></i>Developer Accounts (${data.developers.length})</h5>
                <p class="text-muted small mb-3">Apps are published under these developer accounts on the App Store</p>`;
            data.developers.forEach(function(dev) {
                var appNames = (dev.app_names || '').split('||').filter(Boolean);
                var iconUrls = (dev.icon_urls || '').split('||').filter(Boolean);
                var devUrl = dev.developer_url || '';
                html += `<div class="dev-ecosystem-card mb-3 p-3 border rounded">
                    <div class="d-flex align-items-center mb-2">
                        <div class="dev-icon-circle me-2"><i class="bi bi-person-badge-fill"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-bold">${escapeHtml(dev.developer_name)}</div>
                            <small class="text-muted">${dev.app_count} app${dev.app_count > 1 ? 's' : ''}</small>
                        </div>
                        ${devUrl ? '<a href="' + escapeHtml(devUrl) + '" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm py-0 px-2" title="View on App Store"><i class="bi bi-box-arrow-up-right"></i></a>' : ''}
                    </div>
                    <div class="d-flex flex-wrap gap-1">`;
                appNames.forEach(function(name, idx) {
                    var icon = iconUrls[idx] || '';
                    html += `<span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1" style="font-size:.75rem">
                        ${icon ? '<img src="' + escapeHtml(icon) + '" style="width:16px;height:16px;border-radius:3px" onerror="this.style.display=\'none\'">' : '<i class="bi bi-app"></i>'}
                        ${escapeHtml(name)}
                    </span>`;
                });
                html += `</div></div>`;
            });
            html += `</div></div>`;
        }

        // Platform Distribution
        if (data.platforms && data.platforms.length > 0) {
            const maxPlat = Math.max(...data.platforms.map(function(p) { return parseInt(p.ad_count) || 0; }));
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="section-title"><i class="bi bi-display me-2"></i>Platforms</h5>`;
            data.platforms.forEach(function(p) {
                const cnt = parseInt(p.ad_count) || 0;
                const pct = maxPlat > 0 ? (cnt / maxPlat * 100) : 0;
                html += `<div class="platform-bar">
                    <span class="plat-label">${escapeHtml(p.platform)}</span>
                    <div class="flex-grow-1"><div class="plat-fill" style="width:${pct}%"></div></div>
                    <span class="plat-count">${formatNumber(cnt)}</span>
                </div>`;
            });
            html += `</div></div>`;
        }

        // Competitors
        if (data.competitors && data.competitors.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="section-title"><i class="bi bi-people me-2"></i>Competitors (${data.competitors.length})</h5>`;
            data.competitors.forEach(function(c) {
                html += `<div class="competitor-row">
                    <a href="advertiser_profile.php?id=${encodeURIComponent(c.advertiser_id)}"><i class="bi bi-person-badge me-1"></i>${escapeHtml(c.name || c.advertiser_id)}</a>
                    <span class="comp-count">${formatNumber(c.ad_count)} ads</span>
                </div>`;
            });
            html += `</div></div>`;
        }

        // Countries horizontal bars
        if (data.countries && data.countries.length > 0) {
            const maxCountry = Math.max(...data.countries.map(function(c) { return parseInt(c.ad_count) || 0; }));
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="section-title"><i class="bi bi-geo-alt me-2"></i>Countries (${data.countries.length})</h5>`;
            data.countries.forEach(function(c) {
                const cnt = parseInt(c.ad_count) || 0;
                const pct = maxCountry > 0 ? (cnt / maxCountry * 100) : 0;
                const flag = countryFlag(c.country);
                const name = countryName(c.country);
                html += `<div class="country-hbar">
                    <span class="country-code" title="${escapeHtml(name)}">${flag} ${escapeHtml(c.country)}</span>
                    <span style="font-size:.75rem;color:#666;min-width:100px">${escapeHtml(name)}</span>
                    <div class="flex-grow-1"><div class="country-fill" style="width:${pct}%"></div></div>
                    <span class="country-count">${formatNumber(cnt)} ads</span>
                </div>`;
            });
            html += `</div></div>`;
        }

        html += `</div>`; // end right col
        html += `</div>`; // end row

        // ── YouTube Videos Grid ──
        if (data.videos && data.videos.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="section-title"><i class="bi bi-youtube me-2 text-danger"></i>YouTube Videos (${data.videos.length})</h5>
                <div class="row">`;
            data.videos.forEach(function(v) {
                const thumbUrl = 'https://i.ytimg.com/vi/' + encodeURIComponent(v.video_id) + '/hqdefault.jpg';
                html += `<div class="col-md-4 col-lg-3 mb-3">
                    <a href="youtube_profile.php?id=${encodeURIComponent(v.video_id)}" class="text-decoration-none text-dark">
                        <div class="card yt-video-card h-100">
                            <div class="thumb-wrap">
                                <img src="${escapeHtml(thumbUrl)}" loading="lazy" alt="">
                                <span class="play-overlay"><i class="bi bi-play-circle-fill"></i></span>
                                ${v.duration ? '<span class="duration-badge">' + escapeHtml(v.duration) + '</span>' : ''}
                            </div>
                            <div class="yt-meta">
                                <div class="yt-title">${escapeHtml(v.title || 'Untitled')}</div>
                                <div class="yt-sub">
                                    ${v.channel_name ? '<i class="bi bi-person-circle me-1"></i>' + escapeHtml(v.channel_name) : ''}
                                </div>
                                <div class="yt-sub">
                                    ${parseInt(v.view_count) > 0 ? '<i class="bi bi-eye me-1"></i>' + formatNumber(v.view_count) + ' views' : ''}
                                    ${parseInt(v.like_count) > 0 ? ' &middot; <i class="bi bi-hand-thumbs-up me-1"></i>' + formatNumber(v.like_count) : ''}
                                </div>
                            </div>
                        </div>
                    </a>
                </div>`;
            });
            html += `</div></div></div>`;
        }

        // ── Ads Grid with Pagination ──
        if (data.ads && data.ads.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="section-title mb-0"><i class="bi bi-collection me-2"></i>Ads (${formatNumber(data.total_ads)})</h5>
                    <a href="ads_viewer.php#advertiser_id=${encodeURIComponent(adv.advertiser_id)}" class="btn btn-primary btn-sm">
                        <i class="bi bi-eye me-1"></i>View All Ads
                    </a>
                </div>
                <div class="row">`;
            data.ads.forEach(function(ad) {
                html += renderAdCard(ad);
            });
            html += `</div>`;
            html += renderPagination(data.page, data.total_pages);
            html += `</div></div>`;
        }

        container.innerHTML = html;

        // Bind pagination clicks
        container.querySelectorAll('[data-page]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                load(parseInt(this.dataset.page));
                window.scrollTo({ top: container.querySelector('.section-title i.bi-collection') ? container.querySelector('.section-title i.bi-collection').closest('.card').offsetTop - 20 : 0, behavior: 'smooth' });
            });
        });
    }

    function renderAdCard(ad) {
        const isVideo = ad.ad_type === 'video';
        const ytId = ad.youtube_url ? extractYouTubeId(ad.youtube_url) : null;
        const thumbSrc = ad.preview_image || (ytId ? 'https://i.ytimg.com/vi/' + ytId + '/hqdefault.jpg' : null);
        const viewCount = parseInt(ad.view_count) || 0;
        const productName = ad.product_names ? ad.product_names.split('||')[0] : '';
        const countries = ad.countries ? ad.countries.split(',').map(function(c) { return c.trim(); }).filter(Boolean) : [];

        let thumbHtml = '';
        if (thumbSrc) {
            thumbHtml = `<div class="ad-thumb">
                <img src="${escapeHtml(thumbSrc)}" loading="lazy" alt="">
                ${isVideo ? '<span class="play-icon"><i class="bi bi-play-circle-fill"></i></span>' : ''}
                ${viewCount > 0 ? '<span class="views-badge badge bg-dark"><i class="bi bi-eye me-1"></i>' + formatNumber(viewCount) + '</span>' : ''}
            </div>`;
        } else if (!isVideo && ad.preview_url) {
            thumbHtml = `<div class="ad-thumb" style="background:#f8f9fa"><iframe src="${escapeHtml(ad.preview_url)}" sandbox="allow-scripts allow-same-origin" style="width:100%;height:100%;border:none;pointer-events:none"></iframe></div>`;
        } else if (isVideo) {
            thumbHtml = `<div class="ad-thumb d-flex align-items-center justify-content-center"><i class="bi bi-play-circle" style="font-size:2rem;color:rgba(255,255,255,.5)"></i></div>`;
        } else {
            const typeIcons = { text: 'bi-file-text', image: 'bi-image', video: 'bi-play-circle' };
            const typeIcon = typeIcons[ad.ad_type] || 'bi-file-earmark';
            thumbHtml = `<div class="ad-thumb d-flex align-items-center justify-content-center" style="background:#f0f0f5"><i class="bi ${typeIcon}" style="font-size:2rem;color:rgba(0,0,0,.15)"></i></div>`;
        }

        let linksHtml = '';
        if (productName && ad.product_id) {
            linksHtml += `<a href="app_profile.php?id=${encodeURIComponent(ad.product_id)}" class="badge bg-warning text-dark text-decoration-none" title="View App"><i class="bi bi-app-indicator me-1"></i>${escapeHtml(productName)}</a>`;
        }
        if (ytId) {
            linksHtml += `<a href="youtube_profile.php?id=${encodeURIComponent(ytId)}" class="badge bg-danger text-decoration-none" title="YouTube Profile"><i class="bi bi-youtube"></i></a>`;
        }
        if (ad.landing_url) {
            linksHtml += `<a href="${escapeHtml(ad.landing_url)}" target="_blank" rel="noopener" class="badge bg-primary text-decoration-none" title="Landing Page"><i class="bi bi-box-arrow-up-right"></i></a>`;
        }

        return `<div class="col-md-4 col-lg-3 mb-3">
            <div class="card ad-card h-100">
                ${thumbHtml}
                <div class="ad-body">
                    <div class="d-flex gap-1 mb-1 flex-wrap">
                        ${typeBadge(ad.ad_type || 'text')}
                        ${statusBadge(ad.status || 'unknown')}
                    </div>
                    <div class="ad-headline">${escapeHtml(ad.headline || 'Untitled')}${ad.headline_source === 'youtube' ? ' <span class="badge bg-danger bg-opacity-75" style="font-size:.55rem;vertical-align:middle"><i class="bi bi-youtube"></i> YT</span>' : ''}</div>
                    ${ad.description ? '<div class="ad-desc">' + escapeHtml(ad.description) + '</div>' : ''}
                    ${ad.cta ? '<span class="badge bg-outline-primary mt-1" style="border:1px solid var(--ai-primary);color:var(--ai-primary);font-size:.68rem">' + escapeHtml(ad.cta) + '</span>' : ''}
                    ${linksHtml ? '<div class="ad-links">' + linksHtml + '</div>' : ''}
                    ${countries.length > 0 ? '<div class="ad-countries"><i class="bi bi-geo me-1"></i>' + countries.map(function(c) { return escapeHtml(c); }).join(', ') + '</div>' : ''}
                    <div class="ad-dates"><i class="bi bi-calendar-range me-1"></i>${formatDate(ad.first_seen)} &ndash; ${formatDate(ad.last_seen)}</div>
                </div>
            </div>
        </div>`;
    }

    function extractYouTubeId(url) {
        if (!url) return null;
        var m = url.match(/[?&]v=([a-zA-Z0-9_-]{11})/);
        if (m) return m[1];
        m = url.match(/youtu\.be\/([a-zA-Z0-9_-]{11})/);
        if (m) return m[1];
        m = url.match(/\/embed\/([a-zA-Z0-9_-]{11})/);
        return m ? m[1] : null;
    }

    function renderPagination(page, totalPages) {
        if (totalPages <= 1) return '';
        var html = '<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-0">';
        if (page > 1) {
            html += '<li class="page-item"><a class="page-link" href="#" data-page="1" title="First"><i class="bi bi-chevron-double-left"></i></a></li>';
            html += '<li class="page-item"><a class="page-link" href="#" data-page="' + (page - 1) + '">Prev</a></li>';
        }
        var start = Math.max(1, page - 2);
        var end = Math.min(totalPages, page + 2);
        if (start > 1) {
            html += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
            if (start > 2) html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
        for (var i = start; i <= end; i++) {
            html += '<li class="page-item ' + (i === page ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
        }
        if (end < totalPages) {
            if (end < totalPages - 1) html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            html += '<li class="page-item"><a class="page-link" href="#" data-page="' + totalPages + '">' + totalPages + '</a></li>';
        }
        if (page < totalPages) {
            html += '<li class="page-item"><a class="page-link" href="#" data-page="' + (page + 1) + '">Next</a></li>';
            html += '<li class="page-item"><a class="page-link" href="#" data-page="' + totalPages + '" title="Last"><i class="bi bi-chevron-double-right"></i></a></li>';
        }
        html += '</ul></nav>';
        return html;
    }

    load(1);
})();
</script>

<?php require_once 'includes/footer.php'; ?>
