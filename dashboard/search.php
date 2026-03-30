<?php require_once 'includes/header.php'; ?>

<!-- Search Hero -->
<div class="search-hero text-center py-4" id="searchHero">
    <h3 class="mb-1"><i class="bi bi-search me-2"></i>Universal Search</h3>
    <p class="text-muted mb-3">Search across ads, advertisers, apps &amp; YouTube videos</p>
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="search-input-wrap position-relative">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="searchInput" class="form-control form-control-lg search-input"
                       placeholder="Search anything..." autocomplete="off" autofocus>
                <button class="btn btn-primary search-btn" id="searchBtn" onclick="doSearch()">
                    <i class="bi bi-arrow-right"></i>
                </button>
                <!-- Suggestions dropdown -->
                <div class="suggestions-dropdown" id="suggestionsDropdown"></div>
            </div>
            <!-- Type filter pills -->
            <div class="search-types mt-3" id="searchTypes">
                <button class="btn btn-sm search-type-btn active" data-type="all" onclick="setType('all')">
                    <i class="bi bi-grid-3x3-gap me-1"></i>All
                </button>
                <button class="btn btn-sm search-type-btn" data-type="ads" onclick="setType('ads')">
                    <i class="bi bi-collection me-1"></i>Ads
                </button>
                <button class="btn btn-sm search-type-btn" data-type="advertisers" onclick="setType('advertisers')">
                    <i class="bi bi-building me-1"></i>Advertisers
                </button>
                <button class="btn btn-sm search-type-btn" data-type="apps" onclick="setType('apps')">
                    <i class="bi bi-phone me-1"></i>Apps
                </button>
                <button class="btn btn-sm search-type-btn" data-type="videos" onclick="setType('videos')">
                    <i class="bi bi-youtube me-1"></i>Videos
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Results Area -->
<div id="resultsArea" style="display:none;">

    <!-- Result counts bar -->
    <div class="results-bar mb-3" id="resultsBar"></div>

    <!-- Analytics Panel (only for type=all) -->
    <div class="row mb-3" id="analyticsRow" style="display:none;">
        <div class="col-md-4 mb-3">
            <div class="chart-container h-100">
                <h6 class="fw-bold"><i class="bi bi-globe me-1"></i>Top Countries</h6>
                <div id="analyticsCountries"></div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="chart-container h-100">
                <h6 class="fw-bold"><i class="bi bi-pie-chart me-1"></i>Ad Types</h6>
                <div id="analyticsTypes"></div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="chart-container h-100">
                <h6 class="fw-bold"><i class="bi bi-graph-up me-1"></i>Timeline</h6>
                <canvas id="analyticsTimeline" height="180"></canvas>
            </div>
        </div>
    </div>

    <!-- Suggestions / Quick Navigation -->
    <div class="mb-3" id="suggestionsBar" style="display:none;">
        <div class="chart-container p-2">
            <span class="text-muted small me-2"><i class="bi bi-lightning me-1"></i>Quick jump:</span>
            <span id="quickJumpLinks"></span>
        </div>
    </div>

    <!-- Ads Section -->
    <div id="sectionAds" style="display:none;">
        <div class="section-header d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0"><i class="bi bi-collection text-primary me-2"></i>Ads <span class="badge bg-primary ms-1" id="adsCount">0</span></h5>
            <a href="#" class="btn btn-sm btn-outline-primary" id="adsViewAll" style="display:none;" onclick="setType('ads'); return false;">View All</a>
        </div>
        <div id="adsResults"></div>
        <div id="adsPagination" class="mt-2"></div>
    </div>

    <!-- Advertisers Section -->
    <div id="sectionAdvertisers" style="display:none;">
        <div class="section-header d-flex justify-content-between align-items-center mb-2 mt-3">
            <h5 class="mb-0"><i class="bi bi-building text-info me-2"></i>Advertisers <span class="badge bg-info ms-1" id="advertisersCount">0</span></h5>
            <a href="#" class="btn btn-sm btn-outline-info" id="advertisersViewAll" style="display:none;" onclick="setType('advertisers'); return false;">View All</a>
        </div>
        <div id="advertisersResults"></div>
        <div id="advertisersPagination" class="mt-2"></div>
    </div>

    <!-- Apps Section -->
    <div id="sectionApps" style="display:none;">
        <div class="section-header d-flex justify-content-between align-items-center mb-2 mt-3">
            <h5 class="mb-0"><i class="bi bi-phone text-success me-2"></i>Apps <span class="badge bg-success ms-1" id="appsCount">0</span></h5>
            <a href="#" class="btn btn-sm btn-outline-success" id="appsViewAll" style="display:none;" onclick="setType('apps'); return false;">View All</a>
        </div>
        <div id="appsResults"></div>
        <div id="appsPagination" class="mt-2"></div>
    </div>

    <!-- Videos Section -->
    <div id="sectionVideos" style="display:none;">
        <div class="section-header d-flex justify-content-between align-items-center mb-2 mt-3">
            <h5 class="mb-0"><i class="bi bi-youtube text-danger me-2"></i>YouTube Videos <span class="badge bg-danger ms-1" id="videosCount">0</span></h5>
            <a href="#" class="btn btn-sm btn-outline-danger" id="videosViewAll" style="display:none;" onclick="setType('videos'); return false;">View All</a>
        </div>
        <div id="videosResults"></div>
        <div id="videosPagination" class="mt-2"></div>
    </div>

    <!-- No results -->
    <div id="noResults" style="display:none;" class="empty-state mt-4">
        <i class="bi bi-search"></i>
        <h5>No results found</h5>
        <p class="text-muted">Try a different search term or broaden your filters.</p>
    </div>
</div>

<!-- Loading -->
<div id="searchLoading" style="display:none;">
    <div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// ============================================================
// State
// ============================================================
let S = { q: '', type: 'all', page: 1 };
let debounceTimer = null;
let timelineChart = null;

// ============================================================
// Init
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    parseHash();
    document.getElementById('searchInput').addEventListener('input', onInputChange);
    document.getElementById('searchInput').addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
        if (e.key === 'Escape') hideSuggestions();
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('.search-input-wrap')) hideSuggestions();
    });
    if (S.q) {
        document.getElementById('searchInput').value = S.q;
        doSearch();
    }
});

// ============================================================
// Hash State
// ============================================================
function parseHash() {
    const h = location.hash.slice(1);
    if (!h) return;
    const p = new URLSearchParams(h);
    S.q = p.get('q') || '';
    S.type = p.get('type') || 'all';
    S.page = parseInt(p.get('page')) || 1;
    syncTypeBtns();
}

function pushHash() {
    const p = new URLSearchParams();
    if (S.q) p.set('q', S.q);
    if (S.type !== 'all') p.set('type', S.type);
    if (S.page > 1) p.set('page', S.page);
    history.replaceState(null, '', '#' + p.toString());
}

// ============================================================
// Type switching
// ============================================================
function setType(type) {
    S.type = type;
    S.page = 1;
    syncTypeBtns();
    if (S.q) doSearch();
}

function syncTypeBtns() {
    document.querySelectorAll('.search-type-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.type === S.type);
    });
}

// ============================================================
// Live suggestions (debounced)
// ============================================================
function onInputChange() {
    const val = document.getElementById('searchInput').value.trim();
    clearTimeout(debounceTimer);
    if (val.length < 2) { hideSuggestions(); return; }
    debounceTimer = setTimeout(() => fetchSuggestions(val), 300);
}

async function fetchSuggestions(q) {
    try {
        const data = await fetchAPI('search.php', { q, type: 'all', per_page: 5 });
        if (!data.success || !data.suggestions || data.suggestions.length === 0) { hideSuggestions(); return; }
        renderSuggestions(data.suggestions);
    } catch (e) { hideSuggestions(); }
}

function renderSuggestions(suggestions) {
    const dd = document.getElementById('suggestionsDropdown');
    const icons = { advertiser: 'bi-building', app: 'bi-phone', video: 'bi-youtube' };
    const colors = { advertiser: 'text-info', app: 'text-success', video: 'text-danger' };
    dd.innerHTML = suggestions.map(s => {
        const icon = icons[s.entity_type] || 'bi-search';
        const color = colors[s.entity_type] || '';
        let href = '#';
        if (s.entity_type === 'advertiser') href = `advertiser_profile.php?id=${encodeURIComponent(s.entity_id)}`;
        else if (s.entity_type === 'app') href = `app_profile.php?id=${encodeURIComponent(s.entity_id)}`;
        else if (s.entity_type === 'video') href = `youtube_profile.php?id=${encodeURIComponent(s.entity_id)}`;
        return `<a class="suggestion-item" href="${href}">
            <i class="bi ${icon} ${color} me-2"></i>
            <span>${escapeHtml(s.label)}</span>
            <span class="badge bg-light text-dark ms-auto">${s.entity_type}</span>
        </a>`;
    }).join('');
    dd.style.display = 'block';
}

function hideSuggestions() {
    document.getElementById('suggestionsDropdown').style.display = 'none';
}

// ============================================================
// Main Search
// ============================================================
async function doSearch() {
    const input = document.getElementById('searchInput').value.trim();
    if (input.length < 2) return;
    S.q = input;
    hideSuggestions();
    pushHash();

    document.getElementById('searchLoading').style.display = 'block';
    document.getElementById('resultsArea').style.display = 'none';
    document.getElementById('searchHero').classList.add('compact');

    try {
        const data = await fetchAPI('search.php', { q: S.q, type: S.type, page: S.page, per_page: 20 });
        if (!data.success) throw new Error(data.error);
        renderResults(data);
    } catch (err) {
        document.getElementById('resultsArea').style.display = 'block';
        document.getElementById('noResults').style.display = 'block';
        document.getElementById('noResults').querySelector('p').textContent = err.message;
    } finally {
        document.getElementById('searchLoading').style.display = 'none';
    }
}

// ============================================================
// Render
// ============================================================
function renderResults(data) {
    const { counts, results, suggestions, analytics } = data;
    const area = document.getElementById('resultsArea');
    area.style.display = 'block';

    const total = counts.ads + counts.advertisers + counts.apps + counts.videos;

    // Results bar
    document.getElementById('resultsBar').innerHTML = `
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-bold">${formatNumber(total)} results</span>
            <span class="text-muted">for</span>
            <span class="badge bg-dark fs-6">"${escapeHtml(S.q)}"</span>
            <div class="ms-auto d-flex gap-1">
                ${counts.ads ? `<span class="badge bg-primary cursor-pointer" onclick="setType('ads')">${formatNumber(counts.ads)} ads</span>` : ''}
                ${counts.advertisers ? `<span class="badge bg-info cursor-pointer" onclick="setType('advertisers')">${formatNumber(counts.advertisers)} advertisers</span>` : ''}
                ${counts.apps ? `<span class="badge bg-success cursor-pointer" onclick="setType('apps')">${formatNumber(counts.apps)} apps</span>` : ''}
                ${counts.videos ? `<span class="badge bg-danger cursor-pointer" onclick="setType('videos')">${formatNumber(counts.videos)} videos</span>` : ''}
            </div>
        </div>`;

    // No results
    document.getElementById('noResults').style.display = total === 0 ? 'block' : 'none';

    // Quick jump suggestions
    if (suggestions && suggestions.length > 0 && S.type === 'all') {
        document.getElementById('suggestionsBar').style.display = 'block';
        const icons = { advertiser: 'bi-building', app: 'bi-phone', video: 'bi-youtube' };
        document.getElementById('quickJumpLinks').innerHTML = suggestions.map(s => {
            let href = '#';
            if (s.entity_type === 'advertiser') href = `advertiser_profile.php?id=${encodeURIComponent(s.entity_id)}`;
            else if (s.entity_type === 'app') href = `app_profile.php?id=${encodeURIComponent(s.entity_id)}`;
            else if (s.entity_type === 'video') href = `youtube_profile.php?id=${encodeURIComponent(s.entity_id)}`;
            return `<a href="${href}" class="btn btn-sm btn-outline-secondary me-1 mb-1"><i class="bi ${icons[s.entity_type] || 'bi-link'} me-1"></i>${escapeHtml(s.label)}</a>`;
        }).join('');
    } else {
        document.getElementById('suggestionsBar').style.display = 'none';
    }

    // Analytics
    if (S.type === 'all' && analytics && (analytics.top_countries || analytics.ad_types || analytics.timeline)) {
        document.getElementById('analyticsRow').style.display = 'flex';
        renderAnalytics(analytics);
    } else {
        document.getElementById('analyticsRow').style.display = 'none';
    }

    // Sections
    renderAds(results.ads || [], counts.ads);
    renderAdvertisers(results.advertisers || [], counts.advertisers);
    renderApps(results.apps || [], counts.apps);
    renderVideos(results.videos || [], counts.videos);

    // Pagination for single-type view
    if (S.type !== 'all') {
        renderPagination(data.page, data.total_pages, S.type);
    }
}

// ─────────────────────────────────────────────────
// Analytics
// ─────────────────────────────────────────────────
function renderAnalytics(a) {
    // Countries
    const cc = document.getElementById('analyticsCountries');
    if (a.top_countries && a.top_countries.length > 0) {
        const maxC = Math.max(...a.top_countries.map(c => c.ad_count));
        cc.innerHTML = a.top_countries.slice(0, 8).map(c => `
            <div class="country-bar">
                <span class="country-code">${escapeHtml(c.country)}</span>
                <div class="progress flex-grow-1"><div class="progress-bar bg-primary" style="width:${(c.ad_count / maxC * 100).toFixed(1)}%"></div></div>
                <span class="count">${c.ad_count}</span>
            </div>`).join('');
    } else {
        cc.innerHTML = '<p class="text-muted small">No country data</p>';
    }

    // Ad types
    const tc = document.getElementById('analyticsTypes');
    if (a.ad_types && a.ad_types.length > 0) {
        const typeColors = { text: 'var(--ai-primary)', image: 'var(--ai-warning)', video: 'var(--ai-info)' };
        const totalT = a.ad_types.reduce((s, t) => s + parseInt(t.count), 0);
        tc.innerHTML = a.ad_types.map(t => {
            const pct = (t.count / totalT * 100).toFixed(1);
            const color = typeColors[t.ad_type] || '#6c757d';
            return `<div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span style="width:12px;height:12px;border-radius:3px;background:${color};display:inline-block;"></span>
                    <span class="fw-semibold text-capitalize">${t.ad_type}</span>
                </div>
                <span class="text-muted">${t.count} (${pct}%)</span>
            </div>
            <div class="progress mb-2" style="height:6px;">
                <div class="progress-bar" style="width:${pct}%;background:${color};"></div>
            </div>`;
        }).join('');
    } else {
        tc.innerHTML = '<p class="text-muted small">No type data</p>';
    }

    // Timeline chart
    if (a.timeline && a.timeline.length > 0) {
        const labels = a.timeline.reverse().map(t => t.month);
        const vals = a.timeline.map(t => parseInt(t.count));
        if (timelineChart) timelineChart.destroy();
        const ctx = document.getElementById('analyticsTimeline').getContext('2d');
        timelineChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{ data: vals, backgroundColor: 'rgba(67, 97, 238, 0.6)', borderRadius: 4 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { font: { size: 10 } } } }
            }
        });
    }
}

// ─────────────────────────────────────────────────
// Ads
// ─────────────────────────────────────────────────
function renderAds(ads, count) {
    const section = document.getElementById('sectionAds');
    const showSection = (S.type === 'all' || S.type === 'ads') && count > 0;
    section.style.display = showSection ? 'block' : 'none';
    if (!showSection) return;

    document.getElementById('adsCount').textContent = formatNumber(count);
    document.getElementById('adsViewAll').style.display = (S.type === 'all' && count > 5) ? 'inline-block' : 'none';

    const container = document.getElementById('adsResults');
    container.innerHTML = `<div class="row g-3">${ads.map(ad => {
        const img = ad.preview_image || ad.preview_url || '';
        const countries = (ad.countries || '').split(',').filter(Boolean).slice(0, 5);
        const products = (ad.product_names || '').split('||').filter(Boolean);
        const hl = highlightMatch(ad.headline || 'Untitled Ad', S.q);
        const desc = highlightMatch(truncate(ad.ad_description || '', 120), S.q);
        return `<div class="col-md-6 col-xl-4">
            <div class="card ad-card">
                ${img ? `<div class="ad-preview" style="height:140px;overflow:hidden;background:#f0f2f5;">
                    <img src="${escapeHtml(img)}" style="width:100%;height:100%;object-fit:cover;" loading="lazy" onerror="this.parentElement.remove()">
                </div>` : ''}
                <div class="ad-body">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="ad-headline">${hl}</div>
                        <div class="d-flex gap-1">${typeBadge(ad.ad_type)} ${statusBadge(ad.status)}</div>
                    </div>
                    <div class="ad-description mb-2">${desc}</div>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <a href="advertiser_profile.php?id=${encodeURIComponent(ad.advertiser_id)}" class="badge bg-light text-dark text-decoration-none">
                            <i class="bi bi-building me-1"></i>${escapeHtml(ad.advertiser_name)}
                        </a>
                        ${products.map(p => `<span class="badge bg-light text-success"><i class="bi bi-phone me-1"></i>${escapeHtml(p)}</span>`).join('')}
                        ${countries.map(c => `<span class="badge bg-light text-primary">${countryFlag(c)} ${c}</span>`).join('')}
                    </div>
                </div>
                <div class="ad-meta d-flex justify-content-between">
                    <span><i class="bi bi-eye me-1"></i>${formatNumber(ad.view_count)}</span>
                    <span>${formatDate(ad.last_seen)}</span>
                    ${ad.product_id ? `<a href="app_profile.php?id=${ad.product_id}" class="text-success"><i class="bi bi-box-arrow-up-right"></i></a>` : ''}
                </div>
            </div>
        </div>`;
    }).join('')}</div>`;
}

// ─────────────────────────────────────────────────
// Advertisers
// ─────────────────────────────────────────────────
function renderAdvertisers(advs, count) {
    const section = document.getElementById('sectionAdvertisers');
    const show = (S.type === 'all' || S.type === 'advertisers') && count > 0;
    section.style.display = show ? 'block' : 'none';
    if (!show) return;

    document.getElementById('advertisersCount').textContent = formatNumber(count);
    document.getElementById('advertisersViewAll').style.display = (S.type === 'all' && count > 5) ? 'inline-block' : 'none';

    const container = document.getElementById('advertisersResults');
    container.innerHTML = `<div class="row g-3">${advs.map(a => {
        const name = highlightMatch(a.name || a.advertiser_id, S.q);
        const apps = (a.app_names || '').split('||').filter(Boolean);
        return `<div class="col-md-6 col-xl-4">
            <a href="advertiser_profile.php?id=${encodeURIComponent(a.advertiser_id)}" class="card entity-card text-decoration-none p-3 h-100">
                <div class="d-flex align-items-center mb-2">
                    <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
                        <i class="bi bi-building fs-5 text-info"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark">${name}</div>
                        <div class="text-muted small">${escapeHtml(a.advertiser_id)}</div>
                    </div>
                </div>
                <div class="row text-center mb-2">
                    <div class="col-3">
                        <div class="fw-bold text-primary">${formatNumber(a.total_ads)}</div>
                        <div class="text-muted" style="font-size:0.7rem;">Ads</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-success">${formatNumber(a.active_ads)}</div>
                        <div class="text-muted" style="font-size:0.7rem;">Active</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-warning">${formatNumber(a.total_views)}</div>
                        <div class="text-muted" style="font-size:0.7rem;">Views</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-info">${a.country_count || 0}</div>
                        <div class="text-muted" style="font-size:0.7rem;">Countries</div>
                    </div>
                </div>
                ${apps.length > 0 ? `<div class="d-flex flex-wrap gap-1">${apps.slice(0, 3).map(p => `<span class="badge bg-success bg-opacity-10 text-success">${escapeHtml(p)}</span>`).join('')}${apps.length > 3 ? `<span class="badge bg-light text-muted">+${apps.length - 3}</span>` : ''}</div>` : ''}
            </a>
        </div>`;
    }).join('')}</div>`;
}

// ─────────────────────────────────────────────────
// Apps
// ─────────────────────────────────────────────────
function renderApps(apps, count) {
    const section = document.getElementById('sectionApps');
    const show = (S.type === 'all' || S.type === 'apps') && count > 0;
    section.style.display = show ? 'block' : 'none';
    if (!show) return;

    document.getElementById('appsCount').textContent = formatNumber(count);
    document.getElementById('appsViewAll').style.display = (S.type === 'all' && count > 5) ? 'inline-block' : 'none';

    const container = document.getElementById('appsResults');
    container.innerHTML = `<div class="row g-3">${apps.map(app => {
        const name = highlightMatch(app.product_name || 'Unknown App', S.q);
        const icon = app.icon_url || '';
        const stars = renderStars(app.rating);
        const platform = app.store_platform === 'ios' ? '<i class="bi bi-apple"></i> iOS' : '<i class="bi bi-google-play"></i> Android';
        return `<div class="col-md-6 col-xl-4">
            <a href="app_profile.php?id=${app.product_id}" class="card entity-card text-decoration-none p-3 h-100">
                <div class="d-flex align-items-center mb-2">
                    ${icon ? `<img src="${escapeHtml(icon)}" class="rounded-3 me-3" style="width:48px;height:48px;object-fit:cover;" onerror="this.style.display='none'">` :
                    `<div class="rounded-3 bg-success bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;"><i class="bi bi-phone fs-5 text-success"></i></div>`}
                    <div>
                        <div class="fw-bold text-dark">${name}</div>
                        <div class="text-muted small">${platform} ${app.category ? '&middot; ' + escapeHtml(app.category) : ''}</div>
                        ${app.rating ? `<div class="small">${stars} <span class="text-muted">(${formatNumber(app.rating_count)})</span></div>` : ''}
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1 mb-2">
                    ${app.developer_name ? `<span class="badge bg-light text-dark"><i class="bi bi-person me-1"></i>${escapeHtml(app.developer_name)}</span>` : ''}
                    ${app.downloads ? `<span class="badge bg-light text-dark"><i class="bi bi-download me-1"></i>${escapeHtml(app.downloads)}</span>` : ''}
                    ${app.price && app.price !== '0' ? `<span class="badge bg-warning text-dark">${escapeHtml(app.price)}</span>` : '<span class="badge bg-success">Free</span>'}
                </div>
                <div class="row text-center">
                    <div class="col-3">
                        <div class="fw-bold text-primary">${formatNumber(app.ad_count)}</div>
                        <div class="text-muted" style="font-size:0.7rem;">Ads</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-success">${formatNumber(app.active_ads)}</div>
                        <div class="text-muted" style="font-size:0.7rem;">Active</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-warning">${formatNumber(app.total_views)}</div>
                        <div class="text-muted" style="font-size:0.7rem;">Views</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-info">${app.country_count || 0}</div>
                        <div class="text-muted" style="font-size:0.7rem;">Countries</div>
                    </div>
                </div>
            </a>
        </div>`;
    }).join('')}</div>`;
}

// ─────────────────────────────────────────────────
// Videos
// ─────────────────────────────────────────────────
function renderVideos(videos, count) {
    const section = document.getElementById('sectionVideos');
    const show = (S.type === 'all' || S.type === 'videos') && count > 0;
    section.style.display = show ? 'block' : 'none';
    if (!show) return;

    document.getElementById('videosCount').textContent = formatNumber(count);
    document.getElementById('videosViewAll').style.display = (S.type === 'all' && count > 5) ? 'inline-block' : 'none';

    const container = document.getElementById('videosResults');
    container.innerHTML = `<div class="row g-3">${videos.map(v => {
        const title = highlightMatch(v.title || v.video_id, S.q);
        const thumb = v.thumbnail_url || `https://img.youtube.com/vi/${v.video_id}/mqdefault.jpg`;
        return `<div class="col-md-6 col-xl-4">
            <a href="youtube_profile.php?id=${encodeURIComponent(v.video_id)}" class="card entity-card text-decoration-none h-100">
                <div class="video-thumb">
                    <img src="${escapeHtml(thumb)}" loading="lazy" onerror="this.src='https://img.youtube.com/vi/${v.video_id}/default.jpg'">
                    <div class="play-overlay"><i class="bi bi-play-circle-fill"></i></div>
                    ${v.duration ? `<span class="duration-badge">${escapeHtml(v.duration)}</span>` : ''}
                    ${v.view_count ? `<span class="views-badge"><i class="bi bi-eye me-1"></i>${formatNumber(v.view_count)}</span>` : ''}
                </div>
                <div class="p-3">
                    <div class="fw-bold text-dark mb-1" style="font-size:0.9rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${title}</div>
                    <div class="text-muted small mb-2">${escapeHtml(v.channel_name || 'Unknown Channel')}</div>
                    <div class="d-flex gap-2 text-muted small">
                        ${v.like_count ? `<span><i class="bi bi-hand-thumbs-up me-1"></i>${formatNumber(v.like_count)}</span>` : ''}
                        ${v.comment_count ? `<span><i class="bi bi-chat me-1"></i>${formatNumber(v.comment_count)}</span>` : ''}
                        ${v.ad_count ? `<span class="text-primary"><i class="bi bi-collection me-1"></i>${v.ad_count} ads</span>` : ''}
                        ${v.advertiser_count ? `<span class="text-info"><i class="bi bi-building me-1"></i>${v.advertiser_count}</span>` : ''}
                    </div>
                </div>
            </a>
        </div>`;
    }).join('')}</div>`;
}

// ─────────────────────────────────────────────────
// Pagination
// ─────────────────────────────────────────────────
function renderPagination(page, totalPages, type) {
    const containerId = type + 'Pagination';
    const el = document.getElementById(containerId);
    if (!el || totalPages <= 1) { if (el) el.innerHTML = ''; return; }

    let html = '<nav><ul class="pagination pagination-sm justify-content-center">';
    html += `<li class="page-item ${page <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="goPage(${page - 1}); return false;">&laquo;</a></li>`;

    const start = Math.max(1, page - 2);
    const end = Math.min(totalPages, page + 2);
    if (start > 1) html += `<li class="page-item"><a class="page-link" href="#" onclick="goPage(1); return false;">1</a></li>` + (start > 2 ? '<li class="page-item disabled"><span class="page-link">...</span></li>' : '');
    for (let i = start; i <= end; i++) {
        html += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="#" onclick="goPage(${i}); return false;">${i}</a></li>`;
    }
    if (end < totalPages) html += (end < totalPages - 1 ? '<li class="page-item disabled"><span class="page-link">...</span></li>' : '') + `<li class="page-item"><a class="page-link" href="#" onclick="goPage(${totalPages}); return false;">${totalPages}</a></li>`;

    html += `<li class="page-item ${page >= totalPages ? 'disabled' : ''}"><a class="page-link" href="#" onclick="goPage(${page + 1}); return false;">&raquo;</a></li>`;
    html += '</ul></nav>';
    el.innerHTML = html;
}

function goPage(p) {
    S.page = p;
    doSearch();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ─────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────
function highlightMatch(text, query) {
    if (!text || !query) return escapeHtml(text || '');
    const safe = escapeHtml(text);
    const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return safe.replace(new RegExp('(' + escaped + ')', 'gi'), '<mark>$1</mark>');
}

function truncate(str, len) {
    if (!str) return '';
    return str.length > len ? str.substring(0, len) + '...' : str;
}

function renderStars(rating) {
    if (!rating) return '';
    const r = parseFloat(rating);
    let html = '<span class="star-rating">';
    for (let i = 1; i <= 5; i++) {
        html += i <= Math.round(r) ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
    }
    html += '</span>';
    return html;
}
</script>
