<?php require_once 'includes/header.php'; ?>

<div id="appProfileContent">
    <div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>
</div>

<script>
(function() {
    'use strict';

    const params = new URLSearchParams(window.location.search);
    const productId = params.get('id');

    if (!productId) {
        document.getElementById('appProfileContent').innerHTML = '<div class="text-center py-5 text-danger">Missing app ID parameter.</div>';
        return;
    }

    let currentPage = 1;

    async function load(page) {
        currentPage = page || 1;
        try {
            const data = await fetchAPI('app_profile.php', { id: productId, page: currentPage });
            if (!data.success) throw new Error(data.error);
            render(data);
        } catch (err) {
            document.getElementById('appProfileContent').innerHTML =
                '<div class="text-center py-5 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>' + escapeHtml(err.message) + '</div>';
        }
    }

    function render(data) {
        const p = data.product;
        const m = data.metadata || {};
        const adv = data.advertiser || {};
        const stats = data.ad_stats || {};
        const container = document.getElementById('appProfileContent');

        const isIOS = p.store_platform === 'ios';
        const platformBadge = isIOS
            ? '<span class="badge bg-dark"><i class="bi bi-apple me-1"></i>iOS</span>'
            : '<span class="badge bg-success"><i class="bi bi-google-play me-1"></i>Play Store</span>';

        const storeBtn = p.store_url
            ? `<a href="${escapeHtml(p.store_url)}" target="_blank" rel="noopener" class="btn btn-sm ${isIOS ? 'btn-dark' : 'btn-success'}"><i class="bi ${isIOS ? 'bi-apple' : 'bi-google-play'} me-1"></i>View on ${isIOS ? 'App Store' : 'Play Store'}</a>`
            : '';

        const icon = m.icon_url
            ? `<img src="${escapeHtml(m.icon_url)}" class="app-profile-icon rounded" alt="">`
            : '<div class="app-profile-icon bg-light rounded d-flex align-items-center justify-content-center"><i class="bi bi-app" style="font-size:2rem"></i></div>';

        const ratingVal = m.rating ? parseFloat(m.rating) : 0;
        const fullStars = Math.floor(ratingVal);
        const halfStar = (ratingVal - fullStars) >= 0.3;
        const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
        const ratingStars = ratingVal > 0
            ? `<div class="text-warning">${'<i class="bi bi-star-fill"></i>'.repeat(fullStars)}${halfStar ? '<i class="bi bi-star-half"></i>' : ''}${'<i class="bi bi-star"></i>'.repeat(emptyStars)} <strong>${ratingVal.toFixed(1)}</strong>${m.rating_count ? ` <span class="text-muted fw-normal">(${formatNumber(m.rating_count)})</span>` : ''}</div>`
            : '';

        const appName = escapeHtml(m.app_name || p.product_name);
        const advName = escapeHtml(adv.name || p.advertiser_id);

        let html = '';

        // ── Breadcrumb ──
        html += `<nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none"><i class="bi bi-house me-1"></i>Home</a></li>
                <li class="breadcrumb-item"><a href="advertiser_profile.php?id=${encodeURIComponent(p.advertiser_id)}" class="text-decoration-none">${advName}</a></li>
                <li class="breadcrumb-item active" aria-current="page">${appName}</li>
            </ol>
        </nav>`;

        // ── Header Card ──
        html += `<div class="card mb-4 app-profile-header">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3 flex-wrap">
                    ${icon}
                    <div class="flex-grow-1">
                        <h3 class="mb-1">${appName}</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            ${platformBadge}
                            ${m.category ? `<span class="badge bg-info text-dark"><i class="bi bi-tag me-1"></i>${escapeHtml(m.category)}</span>` : ''}
                            ${m.price ? `<span class="badge bg-secondary">${escapeHtml(m.price)}</span>` : ''}
                            ${m.version ? `<span class="badge bg-light text-dark border"><i class="bi bi-code-slash me-1"></i>v${escapeHtml(m.version)}</span>` : ''}
                        </div>
                        ${ratingStars}
                        ${m.developer_name ? `<div class="text-muted mt-1">
                            <i class="bi bi-person me-1"></i>Developer: <strong>${escapeHtml(m.developer_name)}</strong>
                            ${m.developer_url ? `<a href="${escapeHtml(m.developer_url)}" target="_blank" rel="noopener" class="text-decoration-none ms-1"><i class="bi bi-box-arrow-up-right"></i></a>` : ''}
                        </div>` : ''}
                        ${m.bundle_id ? `<div class="text-muted small"><code>${escapeHtml(m.bundle_id)}</code></div>` : ''}
                    </div>
                    <div class="text-end d-flex flex-column gap-2">
                        ${storeBtn}
                        <a href="advertiser_profile.php?id=${encodeURIComponent(p.advertiser_id)}" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-badge me-1"></i>${advName}</a>
                    </div>
                </div>
            </div>
        </div>`;

        // ── 8 KPI Cards ──
        const countries = data.countries || [];
        html += `<div class="row mb-4 g-3">
            <div class="col-6 col-md-3 col-xl"><div class="card kpi-card p-3 text-center h-100">
                <div class="kpi-label">Total Ads</div>
                <div class="kpi-value text-primary">${formatNumber(data.total_ads || stats.total || 0)}</div>
            </div></div>
            <div class="col-6 col-md-3 col-xl"><div class="card kpi-card p-3 text-center h-100">
                <div class="kpi-label">Active</div>
                <div class="kpi-value text-success">${formatNumber(stats.active || 0)}</div>
            </div></div>
            <div class="col-6 col-md-3 col-xl"><div class="card kpi-card p-3 text-center h-100">
                <div class="kpi-label">Inactive</div>
                <div class="kpi-value text-danger">${formatNumber(stats.inactive || 0)}</div>
            </div></div>
            <div class="col-6 col-md-3 col-xl"><div class="card kpi-card p-3 text-center h-100">
                <div class="kpi-label">Videos</div>
                <div class="kpi-value" style="color:var(--ai-danger)">${formatNumber(stats.video_count || 0)}</div>
            </div></div>
            <div class="col-6 col-md-3 col-xl"><div class="card kpi-card p-3 text-center h-100">
                <div class="kpi-label">Total Views</div>
                <div class="kpi-value" style="color:var(--ai-info)">${formatNumber(stats.total_views || 0)}</div>
            </div></div>
            <div class="col-6 col-md-3 col-xl"><div class="card kpi-card p-3 text-center h-100">
                <div class="kpi-label">Countries</div>
                <div class="kpi-value text-info">${formatNumber(countries.length)}</div>
            </div></div>
            <div class="col-6 col-md-3 col-xl"><div class="card kpi-card p-3 text-center h-100">
                <div class="kpi-label">Rating</div>
                <div class="kpi-value text-warning">${ratingVal > 0 ? ratingVal.toFixed(1) + '/5' : '-'}</div>
            </div></div>
            <div class="col-6 col-md-3 col-xl"><div class="card kpi-card p-3 text-center h-100">
                <div class="kpi-label">${m.downloads ? 'Downloads' : 'Rating Count'}</div>
                <div class="kpi-value" style="color:var(--ai-dark)">${m.downloads ? escapeHtml(m.downloads) : (m.rating_count ? formatNumber(m.rating_count) : '-')}</div>
            </div></div>
        </div>`;

        // ── Two-Column Layout ──
        html += `<div class="row mb-4">`;

        // ── Left Column (col-8) ──
        html += `<div class="col-lg-8 mb-3">`;

        // Ad Type Breakdown
        const adTypes = data.ad_type_breakdown || [];
        if (adTypes.length > 0) {
            const typeTotal = adTypes.reduce((s, t) => s + parseInt(t.count), 0);
            const typeColors = { video: 'var(--ai-info)', image: 'var(--ai-warning)', text: 'var(--ai-primary)' };
            const typeBgClasses = { video: 'bg-info', image: 'bg-warning', text: 'bg-primary' };
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-pie-chart me-2"></i>Ad Type Breakdown</h5>
                ${adTypes.map(t => {
                    const pct = typeTotal > 0 ? (parseInt(t.count) / typeTotal * 100) : 0;
                    const bgClass = typeBgClasses[t.ad_type] || 'bg-secondary';
                    return `<div class="d-flex align-items-center mb-3">
                        <span class="badge badge-${t.ad_type}" style="width:70px;text-transform:capitalize">${escapeHtml(t.ad_type)}</span>
                        <div class="flex-grow-1 mx-2">
                            <div class="progress" style="height:24px;border-radius:6px">
                                <div class="progress-bar ${bgClass}" role="progressbar" style="width:${pct}%;transition:width 0.6s ease" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100">
                                    <span class="fw-semibold">${t.count} (${pct.toFixed(0)}%)</span>
                                </div>
                            </div>
                        </div>
                    </div>`;
                }).join('')}
            </div></div>`;
        }

        // Activity Timeline (stacked bar chart)
        const timeline = data.timeline || [];
        if (timeline.length > 0) {
            const maxCount = Math.max(...timeline.map(t => parseInt(t.count)));
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-graph-up me-2"></i>Activity Timeline</h5>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <small class="d-flex align-items-center gap-1"><span class="timeline-legend-dot" style="background:var(--ai-info)"></span> Video</small>
                    <small class="d-flex align-items-center gap-1"><span class="timeline-legend-dot" style="background:var(--ai-warning)"></span> Image</small>
                    <small class="d-flex align-items-center gap-1"><span class="timeline-legend-dot" style="background:var(--ai-primary)"></span> Text</small>
                </div>
                <div class="chart-container d-flex align-items-end gap-1" style="height:160px;padding-bottom:24px;position:relative">
                    ${timeline.map(t => {
                        const total = parseInt(t.count) || 0;
                        const videos = parseInt(t.videos) || 0;
                        const images = parseInt(t.images) || 0;
                        const texts = parseInt(t.texts) || 0;
                        const pctV = maxCount > 0 ? (videos / maxCount * 100) : 0;
                        const pctI = maxCount > 0 ? (images / maxCount * 100) : 0;
                        const pctT = maxCount > 0 ? (texts / maxCount * 100) : 0;
                        return `<div class="d-flex flex-column align-items-center flex-grow-1 timeline-bar-group" title="${escapeHtml(t.month)}: ${total} ads (V:${videos} I:${images} T:${texts})">
                            <small class="text-muted mb-1" style="font-size:.65rem">${total}</small>
                            <div class="d-flex flex-column w-100" style="height:${Math.max((total / maxCount) * 100, 5)}%;min-height:4px">
                                ${pctV > 0 ? `<div class="w-100 rounded-top" style="flex:${videos};background:var(--ai-info);min-height:2px"></div>` : ''}
                                ${pctI > 0 ? `<div class="w-100" style="flex:${images};background:var(--ai-warning);min-height:2px"></div>` : ''}
                                ${pctT > 0 ? `<div class="w-100 rounded-bottom" style="flex:${texts};background:var(--ai-primary);min-height:2px"></div>` : ''}
                            </div>
                            <small class="text-muted mt-1" style="font-size:.6rem;white-space:nowrap">${escapeHtml(t.month.substring(5))}</small>
                        </div>`;
                    }).join('')}
                </div>
            </div></div>`;
        }

        // Top Performing Ads Table
        const topAds = data.top_ads || [];
        if (topAds.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-trophy me-2"></i>Top Performing Ads</h5>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Headline</th>
                                <th style="width:80px">Type</th>
                                <th style="width:100px" class="text-end">Views</th>
                                <th style="width:60px" class="text-center">Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${topAds.map(ad => {
                                const ytId = ad.youtube_url ? extractYouTubeId(ad.youtube_url) : null;
                                return `<tr class="top-entity-row">
                                    <td><span class="text-truncate d-inline-block" style="max-width:320px">${escapeHtml(ad.headline || 'Untitled')}</span></td>
                                    <td><span class="badge badge-${ad.ad_type || 'text'}">${escapeHtml(ad.ad_type || 'text')}</span></td>
                                    <td class="text-end fw-semibold">${formatNumber(ad.view_count || 0)}</td>
                                    <td class="text-center">
                                        ${ytId ? `<a href="https://www.youtube.com/watch?v=${encodeURIComponent(ytId)}" target="_blank" rel="noopener" class="text-danger"><i class="bi bi-youtube"></i></a>` : '<span class="text-muted">-</span>'}
                                    </td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div></div>`;
        }

        html += `</div>`; // end left col

        // ── Right Column (col-4) ──
        html += `<div class="col-lg-4 mb-3">`;

        // Advertiser Card
        html += `<div class="card mb-4"><div class="card-body">
            <h5 class="mb-3"><i class="bi bi-person-badge me-2"></i>Advertiser</h5>
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;min-width:48px">
                    <i class="bi bi-building text-primary" style="font-size:1.2rem"></i>
                </div>
                <div class="flex-grow-1">
                    <strong class="d-block">${advName}</strong>
                    ${adv.status ? `<span class="badge ${adv.status === 'active' ? 'badge-active' : 'badge-inactive'} mt-1">${escapeHtml(adv.status)}</span>` : ''}
                </div>
            </div>
            <a href="advertiser_profile.php?id=${encodeURIComponent(p.advertiser_id)}" class="btn btn-outline-primary btn-sm w-100 mt-3"><i class="bi bi-arrow-right me-1"></i>View Advertiser Profile</a>
        </div></div>`;

        // Other Advertisers
        const otherAdvs = data.other_advertisers || [];
        if (otherAdvs.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-people me-2"></i>Other Advertisers <span class="badge bg-secondary">${otherAdvs.length}</span></h5>
                <div class="list-group list-group-flush">
                    ${otherAdvs.map(a => `<a href="advertiser_profile.php?id=${encodeURIComponent(a.advertiser_id)}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0 border-start-0 border-end-0">
                        <span><i class="bi bi-person me-2 text-muted"></i>${escapeHtml(a.name || a.advertiser_id)}</span>
                        <span class="badge bg-primary rounded-pill">${formatNumber(a.ad_count)} ads</span>
                    </a>`).join('')}
                </div>
            </div></div>`;
        }

        // Countries with bar chart
        if (countries.length > 0) {
            const maxAdCount = Math.max(...countries.map(c => parseInt(c.ad_count) || 0));
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-geo-alt me-2"></i>Target Countries <span class="badge bg-secondary">${countries.length}</span></h5>
                ${countries.slice(0, 15).map(c => {
                    const cnt = parseInt(c.ad_count) || 0;
                    const pct = maxAdCount > 0 ? (cnt / maxAdCount * 100) : 0;
                    return `<div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-semibold small">${escapeHtml(c.country)}</span>
                            <span class="text-muted small">${formatNumber(cnt)} ads</span>
                        </div>
                        <div class="progress" style="height:6px;border-radius:3px">
                            <div class="progress-bar bg-info" style="width:${pct}%;transition:width 0.5s ease"></div>
                        </div>
                    </div>`;
                }).join('')}
                ${countries.length > 15 ? `<small class="text-muted">+${countries.length - 15} more countries</small>` : ''}
            </div></div>`;
        }

        // Related Apps
        const relatedApps = data.related_apps || [];
        if (relatedApps.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-app-indicator me-2"></i>Related Apps <span class="badge bg-secondary">${relatedApps.length}</span></h5>
                <div class="list-group list-group-flush">
                    ${relatedApps.map(app => {
                        const platIcon = app.store_platform === 'ios' ? 'bi-apple' : 'bi-google-play';
                        const platColor = app.store_platform === 'ios' ? 'bg-dark' : 'bg-success';
                        return `<a href="app_profile.php?id=${encodeURIComponent(app.product_id)}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0 border-start-0 border-end-0">
                            <span class="d-flex align-items-center gap-2">
                                <span class="badge ${platColor}"><i class="bi ${platIcon}"></i></span>
                                <span class="text-truncate" style="max-width:180px">${escapeHtml(app.product_name)}</span>
                            </span>
                            <span class="badge bg-primary rounded-pill">${formatNumber(app.ad_count)} ads</span>
                        </a>`;
                    }).join('')}
                </div>
            </div></div>`;
        }

        html += `</div>`; // end right col
        html += `</div>`; // end row

        // ── Description Card ──
        if (m.description) {
            const descFull = escapeHtml(m.description);
            const isLong = m.description.length > 300;
            const descShort = isLong ? descFull.substring(0, 300) + '...' : descFull;
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>About This App</h5>
                <p class="mb-0 text-muted app-description-text" id="descText">${descShort}</p>
                ${isLong ? `<a href="#" class="small text-primary mt-2 d-inline-block" id="descToggle" data-expanded="false" data-full="${descFull.replace(/"/g, '&quot;')}" data-short="${descShort.replace(/"/g, '&quot;')}">Show more <i class="bi bi-chevron-down"></i></a>` : ''}
            </div></div>`;
        }

        // ── Screenshots Gallery ──
        if (m.screenshots) {
            try {
                const screens = typeof m.screenshots === 'string' ? JSON.parse(m.screenshots) : m.screenshots;
                if (Array.isArray(screens) && screens.length > 0) {
                    html += `<div class="card mb-4"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-images me-2"></i>Screenshots <span class="badge bg-secondary">${screens.length}</span></h5>
                        <div class="app-screenshots-gallery d-flex gap-3 overflow-auto pb-2">
                            ${screens.map((s, i) => `<img src="${escapeHtml(s)}" class="rounded shadow-sm app-screenshot-img" onclick="window.open(this.src)" loading="lazy" alt="Screenshot ${i + 1}">`).join('')}
                        </div>
                    </div></div>`;
                }
            } catch(e) {}
        }

        // ── YouTube Videos Grid ──
        const videos = data.videos || [];
        if (videos.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-youtube me-2 text-danger"></i>YouTube Videos <span class="badge bg-secondary">${videos.length}</span></h5>
                <div class="row g-3">
                    ${videos.map(v => {
                        const thumbUrl = v.thumbnail_url || ('https://i.ytimg.com/vi/' + escapeHtml(v.video_id) + '/hqdefault.jpg');
                        return `<div class="col-md-6 col-lg-3">
                            <a href="youtube_profile.php?id=${encodeURIComponent(v.video_id)}" class="text-decoration-none text-dark">
                                <div class="card h-100 app-video-card">
                                    <div style="position:relative;aspect-ratio:16/9;overflow:hidden;background:#000">
                                        <img src="${escapeHtml(thumbUrl)}" class="w-100 h-100" style="object-fit:cover" loading="lazy" alt="">
                                        <span class="app-video-play-overlay"><i class="bi bi-play-circle-fill"></i></span>
                                        ${v.duration ? `<span class="badge bg-dark" style="position:absolute;bottom:6px;right:6px;font-size:.7rem"><i class="bi bi-clock me-1"></i>${escapeHtml(v.duration)}</span>` : ''}
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="fw-semibold text-truncate small mb-1">${escapeHtml(v.title || 'Untitled Video')}</div>
                                        ${v.channel_name ? `<div class="text-muted small text-truncate"><i class="bi bi-person-circle me-1"></i>${escapeHtml(v.channel_name)}</div>` : ''}
                                        <div class="d-flex gap-3 mt-1">
                                            ${v.view_count ? `<small class="text-muted"><i class="bi bi-eye me-1"></i>${formatNumber(v.view_count)}</small>` : ''}
                                            ${v.like_count ? `<small class="text-muted"><i class="bi bi-hand-thumbs-up me-1"></i>${formatNumber(v.like_count)}</small>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>`;
                    }).join('')}
                </div>
            </div></div>`;
        }

        // ── Ads Grid with Pagination ──
        const ads = data.ads || [];
        if (ads.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-collection me-2"></i>Ads <span class="badge bg-secondary">${formatNumber(data.total_ads)}</span></h5>
                <div class="row g-3">
                    ${ads.map(ad => renderAdCard(ad)).join('')}
                </div>
                ${renderPagination(data.page, data.total_pages)}
            </div></div>`;
        }

        container.innerHTML = html;

        // ── Post-render Event Bindings ──

        // Pagination clicks
        container.querySelectorAll('[data-page]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                load(parseInt(this.dataset.page));
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        // Description toggle
        const descToggle = document.getElementById('descToggle');
        if (descToggle) {
            descToggle.addEventListener('click', function(e) {
                e.preventDefault();
                const expanded = this.dataset.expanded === 'true';
                const textEl = document.getElementById('descText');
                if (expanded) {
                    textEl.innerHTML = this.dataset.short;
                    this.innerHTML = 'Show more <i class="bi bi-chevron-down"></i>';
                    this.dataset.expanded = 'false';
                } else {
                    textEl.innerHTML = this.dataset.full;
                    this.innerHTML = 'Show less <i class="bi bi-chevron-up"></i>';
                    this.dataset.expanded = 'true';
                }
            });
        }
    }

    function renderAdCard(ad) {
        const isVideo = ad.ad_type === 'video';
        const ytId = ad.youtube_url ? extractYouTubeId(ad.youtube_url) : null;
        const thumbSrc = ad.preview_image || (ytId ? 'https://i.ytimg.com/vi/' + ytId + '/hqdefault.jpg' : null);
        const viewCount = parseInt(ad.view_count) || 0;
        const transparencyUrl = 'https://adstransparency.google.com/advertiser/' + encodeURIComponent(ad.creative_id || '');

        // Countries display
        let countriesHtml = '';
        if (ad.countries) {
            try {
                const countryList = typeof ad.countries === 'string' ? JSON.parse(ad.countries) : ad.countries;
                if (Array.isArray(countryList) && countryList.length > 0) {
                    const shown = countryList.slice(0, 3);
                    const extra = countryList.length - shown.length;
                    countriesHtml = `<div class="d-flex flex-wrap gap-1 mb-1">${shown.map(c => `<span class="badge bg-light text-dark border" style="font-size:.65rem">${escapeHtml(typeof c === 'object' ? c.country || c.code : c)}</span>`).join('')}${extra > 0 ? `<span class="badge bg-light text-muted border" style="font-size:.65rem">+${extra}</span>` : ''}</div>`;
                }
            } catch(e) {}
        }

        return `<div class="col-md-6 col-lg-3">
            <div class="card h-100 app-ad-card">
                ${thumbSrc ? `<div style="position:relative;aspect-ratio:16/9;overflow:hidden;background:#000">
                    <img src="${escapeHtml(thumbSrc)}" class="w-100 h-100" style="object-fit:cover" loading="lazy" alt="">
                    ${isVideo ? '<span class="app-video-play-overlay"><i class="bi bi-play-circle-fill"></i></span>' : ''}
                    ${viewCount > 0 ? `<span style="position:absolute;bottom:6px;right:6px" class="badge bg-dark"><i class="bi bi-eye me-1"></i>${formatNumber(viewCount)}</span>` : ''}
                </div>` : (!isVideo && ad.preview_url ? `<div style="aspect-ratio:16/9;overflow:hidden;background:#f8f9fa"><iframe src="${escapeHtml(ad.preview_url)}" sandbox="allow-scripts allow-same-origin" style="width:100%;height:100%;border:none;pointer-events:none" loading="lazy"></iframe></div>` : (isVideo ? `<div style="aspect-ratio:16/9;background:#1a1a2e" class="d-flex align-items-center justify-content-center"><i class="bi bi-play-circle" style="font-size:2rem;color:rgba(255,255,255,.5)"></i></div>` : `<div style="aspect-ratio:16/9;background:#f0f0f5" class="d-flex align-items-center justify-content-center"><i class="bi bi-file-text" style="font-size:2rem;color:rgba(0,0,0,.2)"></i></div>`))}
                <div class="card-body p-2">
                    <div class="d-flex gap-1 mb-1 flex-wrap">
                        <span class="badge badge-${ad.ad_type || 'text'}">${escapeHtml(ad.ad_type || 'text')}</span>
                        <span class="badge ${ad.status === 'active' ? 'badge-active' : 'badge-inactive'}">${escapeHtml(ad.status || 'unknown')}</span>
                    </div>
                    <div class="fw-semibold text-truncate small mb-1">${escapeHtml(ad.headline || 'Untitled')}</div>
                    ${ad.description ? `<div class="text-muted text-truncate small mb-1" style="font-size:.75rem">${escapeHtml(ad.description)}</div>` : ''}
                    ${countriesHtml}
                    <div class="text-muted small" style="font-size:.7rem"><i class="bi bi-calendar-range me-1"></i>${formatDate(ad.first_seen)} - ${formatDate(ad.last_seen)}</div>
                    <div class="d-flex gap-2 mt-2">
                        ${ytId ? `<a href="https://www.youtube.com/watch?v=${encodeURIComponent(ytId)}" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm py-0 px-1" title="Watch on YouTube"><i class="bi bi-youtube"></i></a>` : ''}
                        <a href="${escapeHtml(transparencyUrl)}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm py-0 px-1" title="Google Ads Transparency"><i class="bi bi-shield-check"></i></a>
                    </div>
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
        let html = '<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-0">';
        if (page > 1) html += `<li class="page-item"><a class="page-link" href="#" data-page="1" title="First"><i class="bi bi-chevron-double-left"></i></a></li>`;
        if (page > 1) html += `<li class="page-item"><a class="page-link" href="#" data-page="${page - 1}">Prev</a></li>`;
        for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
            html += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }
        if (page < totalPages) html += `<li class="page-item"><a class="page-link" href="#" data-page="${page + 1}">Next</a></li>`;
        if (page < totalPages) html += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}" title="Last"><i class="bi bi-chevron-double-right"></i></a></li>`;
        html += '</ul></nav>';
        return html;
    }

    load(1);
})();
</script>

<style>
/* ── App Profile Specific Styles ── */
.app-profile-icon {
    width: 80px;
    height: 80px;
    object-fit: cover;
    flex-shrink: 0;
}

.app-profile-header .card-body {
    padding: 1.5rem;
}

/* KPI row: 8 equal columns on xl */
@media (min-width: 1200px) {
    .col-xl {
        flex: 1 0 0%;
    }
}

/* Screenshot gallery */
.app-screenshots-gallery {
    scrollbar-width: thin;
    scrollbar-color: var(--ai-primary) transparent;
    -webkit-overflow-scrolling: touch;
    scroll-snap-type: x mandatory;
}

.app-screenshots-gallery::-webkit-scrollbar {
    height: 6px;
}

.app-screenshots-gallery::-webkit-scrollbar-track {
    background: transparent;
}

.app-screenshots-gallery::-webkit-scrollbar-thumb {
    background: var(--ai-primary);
    border-radius: 3px;
}

.app-screenshot-img {
    height: 220px;
    width: auto;
    cursor: zoom-in;
    scroll-snap-align: start;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    flex-shrink: 0;
}

.app-screenshot-img:hover {
    transform: scale(1.03);
    box-shadow: 0 8px 24px rgba(0,0,0,.15);
}

/* Video card */
.app-video-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
}

.app-video-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,.1);
}

.app-video-play-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 2.2rem;
    color: rgba(255, 255, 255, 0.85);
    transition: transform 0.2s ease, color 0.2s ease;
    pointer-events: none;
    text-shadow: 0 2px 8px rgba(0,0,0,.4);
}

.app-video-card:hover .app-video-play-overlay {
    transform: translate(-50%, -50%) scale(1.15);
    color: #fff;
}

/* Ad card */
.app-ad-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
}

.app-ad-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
}

/* Timeline legend dot */
.timeline-legend-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Timeline bar hover */
.timeline-bar-group {
    cursor: default;
    transition: opacity 0.15s ease;
}

.timeline-bar-group:hover {
    opacity: 0.8;
}

/* Description text */
.app-description-text {
    line-height: 1.7;
    white-space: pre-line;
}

/* Progress bar text */
.progress-bar span {
    font-size: 0.75rem;
}

/* Smooth transitions on list items */
.list-group-item {
    transition: background-color 0.15s ease;
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .app-profile-icon {
        width: 60px;
        height: 60px;
    }

    .app-screenshot-img {
        height: 160px;
    }

    .app-profile-header h3 {
        font-size: 1.25rem;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
