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
        const container = document.getElementById('appProfileContent');

        const platformBadge = p.store_platform === 'ios'
            ? '<span class="badge bg-dark"><i class="bi bi-apple me-1"></i>iOS</span>'
            : '<span class="badge bg-success"><i class="bi bi-google-play me-1"></i>Play Store</span>';

        const storeBtn = p.store_url
            ? `<a href="${escapeHtml(p.store_url)}" target="_blank" rel="noopener" class="btn btn-sm ${p.store_platform === 'ios' ? 'btn-dark' : 'btn-success'}"><i class="bi ${p.store_platform === 'ios' ? 'bi-apple' : 'bi-google-play'} me-1"></i>View on ${p.store_platform === 'ios' ? 'App Store' : 'Play Store'}</a>`
            : '';

        const icon = m.icon_url ? `<img src="${escapeHtml(m.icon_url)}" class="rounded" style="width:80px;height:80px;object-fit:cover" alt="">` : '<div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:80px;height:80px"><i class="bi bi-app" style="font-size:2rem"></i></div>';

        const rating = m.rating ? `<div class="text-warning">${'<i class="bi bi-star-fill"></i>'.repeat(Math.round(m.rating))}${'<i class="bi bi-star"></i>'.repeat(5 - Math.round(m.rating))} <strong>${parseFloat(m.rating).toFixed(1)}</strong></div>` : '';

        let html = '';

        // ── Header Card ──
        html += `<div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    ${icon}
                    <div class="flex-grow-1">
                        <h3 class="mb-1">${escapeHtml(m.app_name || p.product_name)}</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            ${platformBadge}
                            ${m.category ? `<span class="badge bg-info">${escapeHtml(m.category)}</span>` : ''}
                            ${m.price ? `<span class="badge bg-secondary">${escapeHtml(m.price)}</span>` : ''}
                            ${storeBtn}
                        </div>
                        ${m.developer_name ? `<div class="text-muted"><i class="bi bi-person me-1"></i>Developer: <strong>${escapeHtml(m.developer_name)}</strong> ${m.developer_url ? `<a href="${escapeHtml(m.developer_url)}" target="_blank" rel="noopener" class="text-decoration-none"><i class="bi bi-box-arrow-up-right"></i></a>` : ''}</div>` : ''}
                        ${rating}
                    </div>
                    <div class="text-end">
                        <a href="advertiser_profile.php?id=${encodeURIComponent(p.advertiser_id)}" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-badge me-1"></i>${escapeHtml(adv.name || p.advertiser_id)}</a>
                    </div>
                </div>
            </div>
        </div>`;

        // ── KPI Cards ──
        html += `<div class="row mb-4">
            <div class="col-6 col-md-3 mb-3"><div class="card kpi-card p-3">
                <div class="kpi-label">Total Ads</div>
                <div class="kpi-value text-primary">${formatNumber(data.total_ads)}</div>
            </div></div>
            <div class="col-6 col-md-3 mb-3"><div class="card kpi-card p-3">
                <div class="kpi-label">Videos</div>
                <div class="kpi-value text-danger">${formatNumber(data.videos.length)}</div>
            </div></div>
            <div class="col-6 col-md-3 mb-3"><div class="card kpi-card p-3">
                <div class="kpi-label">Countries</div>
                <div class="kpi-value text-info">${formatNumber(data.countries.length)}</div>
            </div></div>
            <div class="col-6 col-md-3 mb-3"><div class="card kpi-card p-3">
                <div class="kpi-label">${m.rating_count ? 'Ratings' : (m.downloads || 'Rating')}</div>
                <div class="kpi-value text-warning">${m.downloads || (m.rating_count ? formatNumber(m.rating_count) : (m.rating ? parseFloat(m.rating).toFixed(1) + '/5' : '-'))}</div>
            </div></div>
        </div>`;

        // ── Description ──
        if (m.description) {
            const desc = escapeHtml(m.description).substring(0, 500);
            html += `<div class="card mb-4"><div class="card-body">
                <h5><i class="bi bi-info-circle me-2"></i>About</h5>
                <p class="mb-0 text-muted">${desc}${m.description.length > 500 ? '...' : ''}</p>
            </div></div>`;
        }

        // ── Screenshots ──
        if (m.screenshots) {
            try {
                const screens = JSON.parse(m.screenshots);
                if (screens.length > 0) {
                    html += `<div class="card mb-4"><div class="card-body">
                        <h5><i class="bi bi-images me-2"></i>Screenshots</h5>
                        <div class="d-flex gap-2 overflow-auto pb-2">
                            ${screens.map(s => `<img src="${escapeHtml(s)}" class="rounded shadow-sm" style="height:200px;cursor:zoom-in" onclick="window.open(this.src)" loading="lazy">`).join('')}
                        </div>
                    </div></div>`;
                }
            } catch(e) {}
        }

        // ── Countries ──
        if (data.countries.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5><i class="bi bi-geo-alt me-2"></i>Target Countries</h5>
                <div class="d-flex flex-wrap gap-1">
                    ${data.countries.map(c => `<span class="badge bg-secondary">${escapeHtml(c)}</span>`).join('')}
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
                                    <span style="position:absolute;bottom:5px;right:5px" class="badge bg-dark"><i class="bi bi-eye me-1"></i>${formatNumber(v.view_count)}</span>
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

        // Pagination clicks
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

        return `<div class="col-md-4 col-lg-3 mb-3">
            <div class="card h-100">
                ${thumbSrc ? `<div style="position:relative;aspect-ratio:16/9;overflow:hidden;background:#000">
                    <img src="${escapeHtml(thumbSrc)}" class="w-100 h-100" style="object-fit:cover" loading="lazy">
                    ${isVideo ? '<span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:2rem;color:rgba(255,255,255,.8)"><i class="bi bi-play-circle-fill"></i></span>' : ''}
                    ${viewCount > 0 ? `<span style="position:absolute;bottom:5px;right:5px" class="badge bg-dark"><i class="bi bi-eye me-1"></i>${formatNumber(viewCount)}</span>` : ''}
                </div>` : (isVideo ? `<div style="aspect-ratio:16/9;background:#1a1a2e" class="d-flex align-items-center justify-content-center"><i class="bi bi-play-circle" style="font-size:2rem;color:rgba(255,255,255,.5)"></i></div>` : '')}
                <div class="card-body p-2">
                    <div class="d-flex gap-1 mb-1">
                        <span class="badge badge-${ad.ad_type || 'text'}">${ad.ad_type || 'text'}</span>
                        <span class="badge ${ad.status === 'active' ? 'badge-active' : 'badge-inactive'}">${ad.status}</span>
                    </div>
                    <small class="text-truncate d-block">${escapeHtml(ad.headline || 'Untitled')}</small>
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
