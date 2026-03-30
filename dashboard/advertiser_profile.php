<?php require_once 'includes/header.php'; ?>

<div id="advProfileContent">
    <div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>
</div>

<script>
(function() {
    'use strict';

    const params = new URLSearchParams(window.location.search);
    const advertiserId = params.get('id');

    if (!advertiserId) {
        document.getElementById('advProfileContent').innerHTML = '<div class="text-center py-5 text-danger">Missing advertiser ID.</div>';
        return;
    }

    let currentPage = 1;

    async function load(page) {
        currentPage = page || 1;
        try {
            const data = await fetchAPI('advertiser_profile.php', { id: advertiserId, page: currentPage });
            if (!data.success) throw new Error(data.error);
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

        // ── Header Card ──
        html += `<div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="mb-1"><i class="bi bi-person-badge me-2"></i>${escapeHtml(adv.name || adv.advertiser_id)}</h3>
                        <div class="text-muted small mb-2"><code>${escapeHtml(adv.advertiser_id)}</code></div>
                        <div class="d-flex gap-2">
                            <span class="badge ${adv.status === 'active' ? 'bg-success' : 'bg-secondary'}">${adv.status}</span>
                            <a href="${escapeHtml(transparencyUrl)}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>Google Ads Transparency</a>
                        </div>
                    </div>
                    <div class="text-end text-muted small">
                        ${adv.last_fetched_at ? `<div>Last fetched: ${formatDate(adv.last_fetched_at)}</div>` : ''}
                        ${adv.fetch_count ? `<div>${adv.fetch_count} fetches</div>` : ''}
                    </div>
                </div>
            </div>
        </div>`;

        // ── KPI Cards ──
        html += `<div class="row mb-4">
            <div class="col-6 col-md-3 mb-3"><div class="card kpi-card p-3">
                <div class="kpi-label">Total Ads</div>
                <div class="kpi-value text-primary">${formatNumber(stats.total)}</div>
            </div></div>
            <div class="col-6 col-md-3 mb-3"><div class="card kpi-card p-3">
                <div class="kpi-label">Active</div>
                <div class="kpi-value text-success">${formatNumber(stats.active)}</div>
            </div></div>
            <div class="col-6 col-md-3 mb-3"><div class="card kpi-card p-3">
                <div class="kpi-label">Inactive</div>
                <div class="kpi-value text-danger">${formatNumber(stats.inactive)}</div>
            </div></div>
            <div class="col-6 col-md-3 mb-3"><div class="card kpi-card p-3">
                <div class="kpi-label">Apps</div>
                <div class="kpi-value text-warning">${formatNumber(data.apps.length)}</div>
            </div></div>
        </div>`;

        // ── Ad Type Breakdown + Countries (side by side) ──
        html += `<div class="row mb-4">`;

        // Ad type breakdown
        if (data.ad_types.length > 0) {
            const total = data.ad_types.reduce((s, t) => s + parseInt(t.count), 0);
            const colors = { video: '#dc3545', image: '#198754', text: '#0d6efd' };
            html += `<div class="col-md-6 mb-3"><div class="card h-100"><div class="card-body">
                <h5><i class="bi bi-pie-chart me-2"></i>Ad Types</h5>
                ${data.ad_types.map(t => {
                    const pct = total > 0 ? (parseInt(t.count) / total * 100) : 0;
                    return `<div class="d-flex align-items-center mb-2">
                        <span class="badge badge-${t.ad_type}" style="width:60px">${t.ad_type}</span>
                        <div class="flex-grow-1 mx-2">
                            <div class="progress" style="height:20px">
                                <div class="progress-bar" style="width:${pct}%;background:${colors[t.ad_type] || '#6c757d'}">${t.count} (${pct.toFixed(0)}%)</div>
                            </div>
                        </div>
                    </div>`;
                }).join('')}
            </div></div></div>`;
        }

        // Countries
        if (data.countries.length > 0) {
            html += `<div class="col-md-6 mb-3"><div class="card h-100"><div class="card-body">
                <h5><i class="bi bi-geo-alt me-2"></i>Target Countries (${data.countries.length})</h5>
                <div class="d-flex flex-wrap gap-1">
                    ${data.countries.map(c => `<span class="badge bg-secondary">${escapeHtml(c)}</span>`).join('')}
                </div>
            </div></div></div>`;
        }
        html += `</div>`;

        // ── Apps ──
        if (data.apps.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5><i class="bi bi-app-indicator me-2"></i>Apps (${data.apps.length})</h5>
                <div class="row">
                    ${data.apps.map(app => {
                        const platIcon = app.store_platform === 'ios' ? 'bi-apple' : 'bi-google-play';
                        const platColor = app.store_platform === 'ios' ? 'bg-dark' : 'bg-success';
                        return `<div class="col-md-4 col-lg-3 mb-3">
                            <a href="app_profile.php?id=${app.product_id}" class="text-decoration-none text-dark">
                                <div class="card h-100 border-hover">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge ${platColor}"><i class="bi ${platIcon}"></i></span>
                                            <strong class="text-truncate">${escapeHtml(app.product_name)}</strong>
                                        </div>
                                        <small class="text-muted">${app.ad_count} ads</small>
                                        ${app.store_url ? `<br><small><a href="${escapeHtml(app.store_url)}" target="_blank" rel="noopener" class="text-primary" onclick="event.stopPropagation()">Store link <i class="bi bi-box-arrow-up-right"></i></a></small>` : ''}
                                    </div>
                                </div>
                            </a>
                        </div>`;
                    }).join('')}
                </div>
            </div></div>`;
        }

        // ── YouTube Videos ──
        if (data.videos.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5><i class="bi bi-youtube me-2 text-danger"></i>YouTube Videos (${data.videos.length})</h5>
                <div class="row">
                    ${data.videos.map(v => `<div class="col-md-4 col-lg-3 mb-3">
                        <a href="youtube_profile.php?id=${encodeURIComponent(v.video_id)}" class="text-decoration-none text-dark">
                            <div class="card h-100">
                                <div style="position:relative;aspect-ratio:16/9;overflow:hidden;background:#000">
                                    <img src="https://i.ytimg.com/vi/${escapeHtml(v.video_id)}/hqdefault.jpg" class="w-100 h-100" style="object-fit:cover" loading="lazy">
                                    ${parseInt(v.view_count) > 0 ? `<span style="position:absolute;bottom:5px;right:5px" class="badge bg-dark"><i class="bi bi-eye me-1"></i>${formatNumber(v.view_count)}</span>` : ''}
                                </div>
                                <div class="card-body p-2">
                                    <small class="text-truncate d-block">${escapeHtml(v.title || 'Video')}</small>
                                </div>
                            </div>
                        </a>
                    </div>`).join('')}
                </div>
            </div></div>`;
        }

        // ── Timeline ──
        if (data.timeline.length > 0) {
            const maxCount = Math.max(...data.timeline.map(t => parseInt(t.count)));
            html += `<div class="card mb-4"><div class="card-body">
                <h5><i class="bi bi-graph-up me-2"></i>Ad Activity Timeline</h5>
                <div class="d-flex align-items-end gap-1" style="height:120px">
                    ${data.timeline.map(t => {
                        const pct = maxCount > 0 ? (parseInt(t.count) / maxCount * 100) : 0;
                        return `<div class="d-flex flex-column align-items-center flex-grow-1">
                            <small class="text-muted mb-1" style="font-size:.65rem">${t.count}</small>
                            <div class="bg-primary rounded-top w-100" style="height:${Math.max(pct, 5)}%;min-height:4px" title="${t.month}: ${t.count} ads"></div>
                            <small class="text-muted mt-1" style="font-size:.6rem">${t.month.substring(5)}</small>
                        </div>`;
                    }).join('')}
                </div>
            </div></div>`;
        }

        // ── Ads Grid ──
        if (data.ads.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5><i class="bi bi-collection me-2"></i>Ads (${formatNumber(data.total_ads)})</h5>
                <div class="row">
                    ${data.ads.map(ad => renderAdCard(ad)).join('')}
                </div>
                ${renderPagination(data.page, data.total_pages)}
            </div></div>`;
        }

        container.innerHTML = html;

        container.querySelectorAll('[data-page]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                load(parseInt(this.dataset.page));
                window.scrollTo(0, 0);
            });
        });
    }

    function renderAdCard(ad) {
        const isVideo = ad.ad_type === 'video';
        const ytId = ad.youtube_url ? extractYouTubeId(ad.youtube_url) : null;
        const thumbSrc = ad.preview_image || (ytId ? 'https://i.ytimg.com/vi/' + ytId + '/hqdefault.jpg' : null);
        const viewCount = parseInt(ad.view_count) || 0;
        const productName = ad.product_names ? ad.product_names.split('||')[0] : '';
        const storePlatform = ad.store_platform || '';

        return `<div class="col-md-4 col-lg-3 mb-3">
            <div class="card h-100">
                ${thumbSrc ? `<div style="position:relative;aspect-ratio:16/9;overflow:hidden;background:#000">
                    <img src="${escapeHtml(thumbSrc)}" class="w-100 h-100" style="object-fit:cover" loading="lazy">
                    ${isVideo ? '<span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:2rem;color:rgba(255,255,255,.8)"><i class="bi bi-play-circle-fill"></i></span>' : ''}
                    ${viewCount > 0 ? `<span style="position:absolute;bottom:5px;right:5px" class="badge bg-dark"><i class="bi bi-eye me-1"></i>${formatNumber(viewCount)}</span>` : ''}
                </div>` : (!isVideo && ad.preview_url ? `<div style="aspect-ratio:16/9;overflow:hidden;background:#f8f9fa"><iframe src="${escapeHtml(ad.preview_url)}" sandbox="allow-scripts allow-same-origin" style="width:100%;height:100%;border:none;pointer-events:none"></iframe></div>` : (isVideo ? `<div style="aspect-ratio:16/9;background:#1a1a2e" class="d-flex align-items-center justify-content-center"><i class="bi bi-play-circle" style="font-size:2rem;color:rgba(255,255,255,.5)"></i></div>` : ''))}
                <div class="card-body p-2">
                    <div class="d-flex gap-1 mb-1">
                        <span class="badge badge-${ad.ad_type || 'text'}">${ad.ad_type || 'text'}</span>
                        <span class="badge ${ad.status === 'active' ? 'badge-active' : 'badge-inactive'}">${ad.status}</span>
                    </div>
                    <small class="text-truncate d-block">${escapeHtml(ad.headline || 'Untitled')}</small>
                    ${productName ? `<small class="d-block"><span class="badge bg-warning text-dark"><i class="bi bi-app-indicator me-1"></i>${escapeHtml(productName)}</span></small>` : ''}
                    <small class="text-muted">${formatDate(ad.first_seen)} - ${formatDate(ad.last_seen)}</small>
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
        if (page > 1) html += `<li class="page-item"><a class="page-link" href="#" data-page="${page - 1}">Prev</a></li>`;
        for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
            html += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }
        if (page < totalPages) html += `<li class="page-item"><a class="page-link" href="#" data-page="${page + 1}">Next</a></li>`;
        html += '</ul></nav>';
        return html;
    }

    load(1);
})();
</script>

<?php require_once 'includes/footer.php'; ?>
