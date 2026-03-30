<?php require_once 'includes/header.php'; ?>

<div id="ytProfileContent">
    <div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>
</div>

<script>
(function() {
    'use strict';

    const params = new URLSearchParams(window.location.search);
    const videoId = params.get('id');

    if (!videoId) {
        document.getElementById('ytProfileContent').innerHTML = '<div class="text-center py-5 text-danger">Missing video ID.</div>';
        return;
    }

    async function load() {
        try {
            const data = await fetchAPI('youtube_profile.php', { id: videoId });
            if (!data.success) throw new Error(data.error);
            render(data);
        } catch (err) {
            document.getElementById('ytProfileContent').innerHTML =
                '<div class="text-center py-5 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>' + escapeHtml(err.message) + '</div>';
        }
    }

    function render(data) {
        const v = data.video;
        const container = document.getElementById('ytProfileContent');

        let html = '';

        // ── Video Player + Info ──
        html += `<div class="row mb-4">
            <div class="col-lg-8 mb-3">
                <div class="card">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/${escapeHtml(videoId)}" allowfullscreen class="rounded-top"></iframe>
                    </div>
                    <div class="card-body">
                        <h4 class="mb-2">${escapeHtml(v.title || 'Untitled Video')}</h4>
                        <div class="d-flex flex-wrap gap-3 text-muted mb-3">
                            ${v.view_count ? `<span><i class="bi bi-eye me-1"></i>${formatNumber(v.view_count)} views</span>` : ''}
                            ${v.like_count ? `<span><i class="bi bi-hand-thumbs-up me-1"></i>${formatNumber(v.like_count)} likes</span>` : ''}
                            ${v.duration ? `<span><i class="bi bi-clock me-1"></i>${escapeHtml(v.duration)}</span>` : ''}
                            ${v.publish_date ? `<span><i class="bi bi-calendar me-1"></i>${formatDate(v.publish_date)}</span>` : ''}
                        </div>
                        ${v.channel_name ? `<div class="mb-2">
                            <i class="bi bi-person-circle me-1"></i>
                            <strong>${escapeHtml(v.channel_name)}</strong>
                            ${v.channel_url ? `<a href="${escapeHtml(v.channel_url)}" target="_blank" rel="noopener" class="ms-1 text-decoration-none"><i class="bi bi-box-arrow-up-right"></i></a>` : ''}
                        </div>` : ''}
                        <a href="https://www.youtube.com/watch?v=${escapeHtml(videoId)}" target="_blank" rel="noopener" class="btn btn-danger btn-sm"><i class="bi bi-youtube me-1"></i>Watch on YouTube</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <!-- Stats Cards -->
                <div class="row g-2 mb-3">
                    <div class="col-6"><div class="card kpi-card p-3 text-center">
                        <div class="kpi-label">Views</div>
                        <div class="kpi-value text-primary">${v.view_count ? formatNumber(v.view_count) : '-'}</div>
                    </div></div>
                    <div class="col-6"><div class="card kpi-card p-3 text-center">
                        <div class="kpi-label">Likes</div>
                        <div class="kpi-value text-success">${v.like_count ? formatNumber(v.like_count) : '-'}</div>
                    </div></div>
                    <div class="col-6"><div class="card kpi-card p-3 text-center">
                        <div class="kpi-label">Ads Using</div>
                        <div class="kpi-value text-warning">${formatNumber(data.ads.length)}</div>
                    </div></div>
                    <div class="col-6"><div class="card kpi-card p-3 text-center">
                        <div class="kpi-label">Countries</div>
                        <div class="kpi-value text-info">${formatNumber(data.countries.length)}</div>
                    </div></div>
                </div>

                <!-- Advertiser(s) -->
                ${data.advertisers.length > 0 ? `<div class="card mb-3"><div class="card-body">
                    <h6><i class="bi bi-person-badge me-2"></i>Advertiser${data.advertisers.length > 1 ? 's' : ''}</h6>
                    ${data.advertisers.map(a => `<a href="advertiser_profile.php?id=${encodeURIComponent(a.advertiser_id)}" class="d-block text-decoration-none mb-1">
                        <span class="badge bg-primary">${escapeHtml(a.name || a.advertiser_id)}</span>
                    </a>`).join('')}
                </div></div>` : ''}

                <!-- App(s) -->
                ${data.apps.length > 0 ? `<div class="card mb-3"><div class="card-body">
                    <h6><i class="bi bi-app-indicator me-2"></i>Promoted App${data.apps.length > 1 ? 's' : ''}</h6>
                    ${data.apps.map(app => {
                        const platIcon = app.store_platform === 'ios' ? 'bi-apple' : 'bi-google-play';
                        const platColor = app.store_platform === 'ios' ? 'bg-dark' : 'bg-success';
                        return `<a href="app_profile.php?id=${app.id}" class="d-flex align-items-center gap-2 text-decoration-none text-dark mb-2">
                            <span class="badge ${platColor}"><i class="bi ${platIcon}"></i></span>
                            <span>${escapeHtml(app.product_name)}</span>
                        </a>
                        ${app.store_url ? `<a href="${escapeHtml(app.store_url)}" target="_blank" rel="noopener" class="btn btn-sm ${app.store_platform === 'ios' ? 'btn-outline-dark' : 'btn-outline-success'} mb-2"><i class="bi ${platIcon} me-1"></i>Store Link</a>` : ''}`;
                    }).join('')}
                </div></div>` : ''}

                <!-- Countries -->
                ${data.countries.length > 0 ? `<div class="card"><div class="card-body">
                    <h6><i class="bi bi-geo-alt me-2"></i>Countries</h6>
                    <div class="d-flex flex-wrap gap-1">
                        ${data.countries.map(c => `<span class="badge bg-secondary">${escapeHtml(c)}</span>`).join('')}
                    </div>
                </div></div>` : ''}
            </div>
        </div>`;

        // ── Description ──
        if (v.description) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5><i class="bi bi-text-left me-2"></i>Description</h5>
                <pre class="mb-0 text-muted" style="white-space:pre-wrap;font-family:inherit;font-size:.9rem">${escapeHtml(v.description)}</pre>
            </div></div>`;
        }

        // ── Ads Using This Video ──
        if (data.ads.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5><i class="bi bi-collection me-2"></i>Ads Using This Video (${data.ads.length})</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead><tr>
                            <th>Creative ID</th>
                            <th>Headline</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>First Seen</th>
                            <th>Last Seen</th>
                        </tr></thead>
                        <tbody>
                            ${data.ads.map(ad => `<tr>
                                <td><code class="small">${escapeHtml((ad.creative_id || '').substring(0, 20))}...</code></td>
                                <td class="text-truncate" style="max-width:250px">${escapeHtml(ad.headline || '-')}</td>
                                <td><span class="badge badge-${ad.ad_type || 'text'}">${ad.ad_type}</span></td>
                                <td><span class="badge ${ad.status === 'active' ? 'badge-active' : 'badge-inactive'}">${ad.status}</span></td>
                                <td>${parseInt(ad.view_count) > 0 ? formatNumber(ad.view_count) : '-'}</td>
                                <td class="small">${formatDate(ad.first_seen)}</td>
                                <td class="small">${formatDate(ad.last_seen)}</td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>
            </div></div>`;
        }

        container.innerHTML = html;
    }

    load();
})();
</script>

<?php require_once 'includes/footer.php'; ?>
