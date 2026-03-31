<?php require_once 'includes/header.php'; ?>

<style>
    .yt-hero-player {
        border-radius: 8px 8px 0 0;
        overflow: hidden;
        background: #000;
    }
    .yt-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        color: var(--ai-dark, #343a40);
        font-size: 0.9rem;
    }
    .yt-meta-row span {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }
    .yt-stat-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    .yt-stat-grid .kpi-card {
        text-align: center;
    }
    .yt-channel-link {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 600;
        color: var(--ai-dark, #343a40);
        text-decoration: none;
    }
    .yt-channel-link:hover {
        color: var(--ai-primary, #0d6efd);
    }
    .yt-app-card {
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        border: 1px solid rgba(0,0,0,.08);
    }
    .yt-app-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,.1);
    }
    .yt-app-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        object-fit: cover;
    }
    .yt-app-icon-placeholder {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: #6c757d;
    }
    .yt-rating-stars {
        color: #ffc107;
        font-size: 0.8rem;
    }
    .yt-country-bar {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.5rem;
    }
    .yt-country-bar .country-code {
        font-weight: 600;
        min-width: 32px;
        font-size: 0.85rem;
    }
    .yt-country-bar .progress {
        flex: 1;
        height: 22px;
        border-radius: 4px;
    }
    .yt-country-bar .count-label {
        min-width: 40px;
        text-align: right;
        font-size: 0.8rem;
        color: #6c757d;
    }
    .yt-timeline-chart {
        display: flex;
        align-items: flex-end;
        gap: 3px;
        height: 140px;
        padding-top: 10px;
    }
    .yt-timeline-bar {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        height: 100%;
        justify-content: flex-end;
    }
    .yt-timeline-bar .bar {
        width: 100%;
        border-radius: 4px 4px 0 0;
        background: var(--ai-primary, #0d6efd);
        transition: height 0.4s ease;
        min-width: 8px;
        cursor: default;
    }
    .yt-timeline-bar .bar:hover {
        opacity: 0.85;
    }
    .yt-timeline-bar .bar-label {
        font-size: 0.6rem;
        color: #6c757d;
        margin-top: 4px;
        white-space: nowrap;
    }
    .yt-timeline-bar .bar-count {
        font-size: 0.6rem;
        color: #6c757d;
        margin-bottom: 2px;
    }
    .yt-related-card {
        position: relative;
        overflow: hidden;
        border-radius: 8px;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .yt-related-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,.1);
    }
    .yt-related-thumb {
        position: relative;
        aspect-ratio: 16/9;
        overflow: hidden;
        background: #000;
    }
    .yt-related-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .yt-play-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 2.5rem;
        color: rgba(255,255,255,0.85);
        opacity: 0;
        transition: opacity 0.2s ease;
        text-shadow: 0 2px 8px rgba(0,0,0,0.4);
    }
    .yt-related-card:hover .yt-play-overlay {
        opacity: 1;
    }
    .yt-duration-badge {
        position: absolute;
        bottom: 6px;
        right: 6px;
        background: rgba(0,0,0,0.8);
        color: #fff;
        padding: 1px 6px;
        border-radius: 3px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .yt-desc-collapsed {
        max-height: 4.5em;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    .yt-desc-expanded {
        max-height: none;
    }
    .yt-desc-toggle {
        cursor: pointer;
        color: var(--ai-primary, #0d6efd);
        font-weight: 600;
        font-size: 0.85rem;
        user-select: none;
    }
    .yt-desc-toggle:hover {
        text-decoration: underline;
    }
    .yt-ads-table th {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #6c757d;
        border-bottom-width: 2px;
    }
    .yt-ads-table td {
        vertical-align: middle;
        font-size: 0.875rem;
    }
    .yt-advertiser-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(0,0,0,.05);
    }
    .yt-advertiser-item:last-child {
        border-bottom: none;
    }
</style>

<div id="ytProfileContent">
    <div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>
</div>

<script>
(function() {
    'use strict';

    const params = new URLSearchParams(window.location.search);
    const videoId = params.get('id');

    if (!videoId) {
        document.getElementById('ytProfileContent').innerHTML = '<div class="text-center py-5 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Missing video ID.</div>';
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

    function renderStars(rating) {
        const r = Math.round(parseFloat(rating) || 0);
        let s = '';
        for (let i = 1; i <= 5; i++) {
            s += i <= r ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
        }
        return s;
    }

    function render(data) {
        const v = data.video;
        const ads = data.ads || [];
        const apps = data.apps || [];
        const advertisers = data.advertisers || [];
        const countries = data.countries || [];
        const relatedVideos = data.related_videos || [];
        const timeline = data.timeline || [];
        const container = document.getElementById('ytProfileContent');

        let html = '';

        // ── Breadcrumb ──
        html += `<nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door me-1"></i>Home</a></li>
                <li class="breadcrumb-item"><a href="index.php?tab=youtube">YouTube Videos</a></li>
                <li class="breadcrumb-item active" aria-current="page">${escapeHtml(v.title || 'Video')}</li>
            </ol>
        </nav>`;

        // ── Two-Column Hero Layout ──
        html += `<div class="row mb-4">`;

        // Left Column: Player + Video Info
        html += `<div class="col-lg-8 mb-3">
            <div class="card">
                <div class="yt-hero-player">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/${encodeURIComponent(videoId)}?rel=0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                    </div>
                </div>
                <div class="card-body">
                    <h3 class="mb-2">${escapeHtml(v.title || 'Untitled Video')}</h3>
                    <div class="yt-meta-row mb-3">
                        ${v.view_count ? `<span><i class="bi bi-eye text-primary"></i>${formatNumber(v.view_count)} views</span>` : ''}
                        ${v.like_count ? `<span><i class="bi bi-hand-thumbs-up text-success"></i>${formatNumber(v.like_count)} likes</span>` : ''}
                        ${v.comment_count ? `<span><i class="bi bi-chat-dots text-info"></i>${formatNumber(v.comment_count)} comments</span>` : ''}
                        ${v.duration ? `<span><i class="bi bi-clock text-warning"></i>${escapeHtml(v.duration)}</span>` : ''}
                        ${v.publish_date ? `<span><i class="bi bi-calendar3 text-secondary"></i>${formatDate(v.publish_date)}</span>` : ''}
                    </div>
                    ${v.channel_name ? `<div class="mb-3">
                        ${v.channel_url
                            ? `<a href="${escapeHtml(v.channel_url)}" target="_blank" rel="noopener" class="yt-channel-link"><i class="bi bi-person-circle" style="font-size:1.2rem"></i>${escapeHtml(v.channel_name)}<i class="bi bi-box-arrow-up-right" style="font-size:0.75rem"></i></a>`
                            : `<span class="yt-channel-link"><i class="bi bi-person-circle" style="font-size:1.2rem"></i>${escapeHtml(v.channel_name)}</span>`
                        }
                    </div>` : ''}
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="https://www.youtube.com/watch?v=${encodeURIComponent(videoId)}" target="_blank" rel="noopener" class="btn btn-danger btn-sm">
                            <i class="bi bi-youtube me-1"></i>Watch on YouTube
                        </a>
                        <a href="ads_viewer.php#ad_type=video&search=${encodeURIComponent(videoId)}" class="btn btn-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>View in Ads Viewer
                        </a>
                    </div>
                </div>
            </div>
        </div>`;

        // Right Column: Stats + Advertisers
        html += `<div class="col-lg-4 mb-3">`;

        // 2x3 Stat Grid
        html += `<div class="yt-stat-grid mb-3">
            <div class="card kpi-card p-3">
                <div class="kpi-label"><i class="bi bi-eye me-1"></i>Views</div>
                <div class="kpi-value text-primary">${v.view_count ? formatNumber(v.view_count) : '-'}</div>
            </div>
            <div class="card kpi-card p-3">
                <div class="kpi-label"><i class="bi bi-hand-thumbs-up me-1"></i>Likes</div>
                <div class="kpi-value text-success">${v.like_count ? formatNumber(v.like_count) : '-'}</div>
            </div>
            <div class="card kpi-card p-3">
                <div class="kpi-label"><i class="bi bi-chat-dots me-1"></i>Comments</div>
                <div class="kpi-value text-info">${v.comment_count ? formatNumber(v.comment_count) : '-'}</div>
            </div>
            <div class="card kpi-card p-3">
                <div class="kpi-label"><i class="bi bi-megaphone me-1"></i>Ads Using</div>
                <div class="kpi-value text-warning">${formatNumber(ads.length)}</div>
            </div>
            <div class="card kpi-card p-3">
                <div class="kpi-label"><i class="bi bi-app-indicator me-1"></i>Apps Promoted</div>
                <div class="kpi-value text-danger">${formatNumber(apps.length)}</div>
            </div>
            <div class="card kpi-card p-3">
                <div class="kpi-label"><i class="bi bi-geo-alt me-1"></i>Countries</div>
                <div class="kpi-value" style="color:var(--ai-dark,#343a40)">${formatNumber(countries.length)}</div>
            </div>
        </div>`;

        // Advertiser(s) Card
        if (advertisers.length > 0) {
            html += `<div class="card">
                <div class="card-body">
                    <h6 class="mb-3"><i class="bi bi-person-badge me-2"></i>Advertiser${advertisers.length > 1 ? 's' : ''}</h6>
                    ${advertisers.map(a => `<div class="yt-advertiser-item">
                        <a href="advertiser_profile.php?id=${encodeURIComponent(a.advertiser_id)}" class="text-decoration-none d-flex align-items-center gap-2">
                            <i class="bi bi-building text-primary"></i>
                            <strong>${escapeHtml(a.name || a.advertiser_id)}</strong>
                        </a>
                        <div class="d-flex gap-2">
                            <span class="badge bg-primary">${formatNumber(a.ad_count)} ads</span>
                            ${parseInt(a.active_count) > 0 ? `<span class="badge badge-active">${formatNumber(a.active_count)} active</span>` : ''}
                        </div>
                    </div>`).join('')}
                </div>
            </div>`;
        }

        html += `</div>`; // end right col
        html += `</div>`; // end row

        // ── Apps Section ──
        if (apps.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-app-indicator me-2"></i>Promoted Apps (${apps.length})</h5>
                <div class="row">
                    ${apps.map(app => {
                        const isIos = app.store_platform === 'ios';
                        const platIcon = isIos ? 'bi-apple' : 'bi-google-play';
                        const platColor = isIos ? 'bg-dark' : 'bg-success';
                        const platLabel = isIos ? 'iOS' : 'Android';
                        const ratingVal = parseFloat(app.rating) || 0;
                        return `<div class="col-md-6 col-lg-4 col-xl-3 mb-3">
                            <a href="app_profile.php?id=${encodeURIComponent(app.product_id)}" class="text-decoration-none text-dark">
                                <div class="card h-100 yt-app-card">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-start gap-3 mb-2">
                                            ${app.icon_url
                                                ? `<img src="${escapeHtml(app.icon_url)}" class="yt-app-icon" alt="" loading="lazy">`
                                                : '<div class="yt-app-icon-placeholder"><i class="bi bi-app"></i></div>'}
                                            <div class="flex-grow-1 min-width-0">
                                                <strong class="d-block text-truncate">${escapeHtml(app.product_name)}</strong>
                                                <span class="badge ${platColor} mt-1"><i class="bi ${platIcon} me-1"></i>${platLabel}</span>
                                            </div>
                                        </div>
                                        ${ratingVal > 0 ? `<div class="yt-rating-stars mb-1">${renderStars(ratingVal)} <small class="text-muted ms-1">${ratingVal.toFixed(1)}</small></div>` : ''}
                                        <div class="d-flex align-items-center justify-content-between">
                                            <small class="text-muted"><i class="bi bi-megaphone me-1"></i>${formatNumber(app.ad_count)} ads</small>
                                            ${app.store_url ? `<small><span class="text-primary" onclick="event.preventDefault();event.stopPropagation();window.open('${escapeHtml(app.store_url)}','_blank')"><i class="bi bi-box-arrow-up-right"></i> Store</span></small>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>`;
                    }).join('')}
                </div>
            </div></div>`;
        }

        // ── Countries Section ──
        if (countries.length > 0) {
            const maxAdCount = Math.max(...countries.map(c => parseInt(c.ad_count) || 0));
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-geo-alt me-2"></i>Countries (${countries.length})</h5>
                ${countries.map(c => {
                    const cnt = parseInt(c.ad_count) || 0;
                    const pct = maxAdCount > 0 ? (cnt / maxAdCount * 100) : 0;
                    const flag = countryFlag(c.country);
                    const name = countryName(c.country);
                    return `<div class="yt-country-bar">
                        <span class="country-code" title="${escapeHtml(name)}">${flag} ${escapeHtml(c.country)}</span>
                        <span style="font-size:.75rem;color:#666;min-width:90px">${escapeHtml(name)}</span>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width:${pct}%;background:var(--ai-primary,#0d6efd)" aria-valuenow="${cnt}" aria-valuemin="0" aria-valuemax="${maxAdCount}">${cnt > 0 ? cnt + ' ads' : ''}</div>
                        </div>
                        <span class="count-label">${formatNumber(cnt)}</span>
                    </div>`;
                }).join('')}
            </div></div>`;
        }

        // ── Activity Timeline ──
        if (timeline.length > 0) {
            const maxCount = Math.max(...timeline.map(t => parseInt(t.count) || 0));
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-bar-chart me-2"></i>Ad Activity Timeline</h5>
                <div class="chart-container">
                    <div class="yt-timeline-chart">
                        ${timeline.map(t => {
                            const cnt = parseInt(t.count) || 0;
                            const pct = maxCount > 0 ? (cnt / maxCount * 100) : 0;
                            const label = t.month.length >= 7 ? t.month.substring(5) : t.month;
                            return `<div class="yt-timeline-bar" title="${escapeHtml(t.month)}: ${cnt} ads">
                                <span class="bar-count">${cnt > 0 ? cnt : ''}</span>
                                <div class="bar" style="height:${Math.max(pct, 3)}%"></div>
                                <span class="bar-label">${escapeHtml(label)}</span>
                            </div>`;
                        }).join('')}
                    </div>
                </div>
            </div></div>`;
        }

        // ── Related Videos Grid ──
        if (relatedVideos.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-collection-play me-2 text-danger"></i>Related Videos (${relatedVideos.length})</h5>
                <div class="row">
                    ${relatedVideos.map(rv => {
                        const thumbUrl = 'https://i.ytimg.com/vi/' + encodeURIComponent(rv.video_id) + '/hqdefault.jpg';
                        return `<div class="col-md-4 mb-3">
                            <a href="youtube_profile.php?id=${encodeURIComponent(rv.video_id)}" class="text-decoration-none text-dark">
                                <div class="card h-100 yt-related-card">
                                    <div class="yt-related-thumb">
                                        <img src="${escapeHtml(thumbUrl)}" alt="" loading="lazy">
                                        <div class="yt-play-overlay"><i class="bi bi-play-circle-fill"></i></div>
                                        ${rv.duration ? `<span class="yt-duration-badge">${escapeHtml(rv.duration)}</span>` : ''}
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="text-truncate fw-semibold mb-1" style="font-size:0.9rem">${escapeHtml(rv.title || 'Untitled')}</div>
                                        ${rv.view_count ? `<small class="text-muted"><i class="bi bi-eye me-1"></i>${formatNumber(rv.view_count)} views</small>` : ''}
                                    </div>
                                </div>
                            </a>
                        </div>`;
                    }).join('')}
                </div>
            </div></div>`;
        }

        // ── Description Card (Collapsible) ──
        if (v.description) {
            const descText = escapeHtml(v.description);
            const isLong = v.description.length > 300;
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-text-left me-2"></i>Description</h5>
                <pre id="ytDescText" class="mb-2 text-muted ${isLong ? 'yt-desc-collapsed' : ''}" style="white-space:pre-wrap;font-family:inherit;font-size:.9rem">${descText}</pre>
                ${isLong ? '<span id="ytDescToggle" class="yt-desc-toggle">Show more</span>' : ''}
            </div></div>`;
        }

        // ── Ads Table ──
        if (ads.length > 0) {
            html += `<div class="card mb-4"><div class="card-body">
                <h5 class="mb-3"><i class="bi bi-collection me-2"></i>Ads Using This Video (${formatNumber(ads.length)})</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover yt-ads-table">
                        <thead><tr>
                            <th>Advertiser</th>
                            <th>Headline</th>
                            <th>App</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Countries</th>
                            <th>First Seen</th>
                            <th>Last Seen</th>
                        </tr></thead>
                        <tbody>
                            ${ads.map(ad => {
                                const adCountries = ad.countries ? ad.countries.split(',').map(c => c.trim()).filter(Boolean) : [];
                                const appName = ad.product_names ? ad.product_names.split('||')[0].trim() : '';
                                return `<tr>
                                    <td>
                                        ${ad.advertiser_id
                                            ? `<a href="advertiser_profile.php?id=${encodeURIComponent(ad.advertiser_id)}" class="text-decoration-none fw-semibold">${escapeHtml(ad.advertiser_name || ad.advertiser_id)}</a>`
                                            : '<span class="text-muted">-</span>'}
                                    </td>
                                    <td class="text-truncate" style="max-width:220px" title="${escapeHtml(ad.headline || '')}">${escapeHtml(ad.headline || '-')}</td>
                                    <td>
                                        ${ad.product_id && appName
                                            ? `<a href="app_profile.php?id=${encodeURIComponent(ad.product_id)}" class="text-decoration-none"><span class="badge bg-warning text-dark"><i class="bi bi-app-indicator me-1"></i>${escapeHtml(appName)}</span></a>`
                                            : '<span class="text-muted">-</span>'}
                                    </td>
                                    <td>${typeBadge(ad.ad_type)}</td>
                                    <td>${statusBadge(ad.status)}</td>
                                    <td>${parseInt(ad.view_count) > 0 ? formatNumber(ad.view_count) : '-'}</td>
                                    <td>
                                        ${adCountries.length > 0
                                            ? adCountries.map(c => `<span class="badge bg-secondary bg-opacity-75 me-1">${countryFlag(c)} ${escapeHtml(c)}</span>`).join('')
                                            : '<span class="text-muted">-</span>'}
                                    </td>
                                    <td class="small text-nowrap">${formatDate(ad.first_seen)}</td>
                                    <td class="small text-nowrap">${formatDate(ad.last_seen)}</td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div></div>`;
        }

        container.innerHTML = html;

        // ── Description Toggle ──
        var toggleBtn = document.getElementById('ytDescToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                var descEl = document.getElementById('ytDescText');
                var expanded = descEl.classList.contains('yt-desc-expanded');
                if (expanded) {
                    descEl.classList.remove('yt-desc-expanded');
                    descEl.classList.add('yt-desc-collapsed');
                    toggleBtn.textContent = 'Show more';
                } else {
                    descEl.classList.remove('yt-desc-collapsed');
                    descEl.classList.add('yt-desc-expanded');
                    toggleBtn.textContent = 'Show less';
                }
            });
        }
    }

    load();
})();
</script>

<?php require_once 'includes/footer.php'; ?>
