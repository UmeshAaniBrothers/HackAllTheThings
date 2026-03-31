<?php require_once 'includes/header.php'; ?>

<!-- Stats Cards -->
<div class="row mb-4" id="statsCards">
    <div class="col-6 col-md-3 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Total Ads</div>
                    <div class="kpi-value text-primary" id="vStatTotal">-</div>
                </div>
                <i class="bi bi-collection kpi-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Active</div>
                    <div class="kpi-value text-success" id="vStatActive">-</div>
                </div>
                <i class="bi bi-check-circle kpi-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Inactive</div>
                    <div class="kpi-value text-danger" id="vStatInactive">-</div>
                </div>
                <i class="bi bi-x-circle kpi-icon text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Shown Results</div>
                    <div class="kpi-value text-info" id="vStatShown">-</div>
                </div>
                <i class="bi bi-funnel kpi-icon text-info"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mb-3" id="viewerFilterBar">
    <div class="row g-2 align-items-end">
        <!-- Row 1: Primary filters -->
        <div class="col-md-2">
            <label class="form-label small mb-1">Advertiser</label>
            <select id="vFilterAdvertiser" class="form-select form-select-sm">
                <option value="">All Advertisers</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Country</label>
            <select id="vFilterCountry" class="form-select form-select-sm">
                <option value="">All Countries</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Platform</label>
            <select id="vFilterPlatform" class="form-select form-select-sm">
                <option value="">All Platforms</option>
                <option value="ios">iOS</option>
                <option value="playstore">Play Store</option>
                <option value="web">Web</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">App</label>
            <select id="vFilterProduct" class="form-select form-select-sm">
                <option value="">All Apps</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label small mb-1">Type</label>
            <select id="vFilterType" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="text">Text</option>
                <option value="image">Image</option>
                <option value="video">Video</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label small mb-1">Status</label>
            <select id="vFilterStatus" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">From</label>
            <input type="date" id="vFilterDateFrom" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">To</label>
            <input type="date" id="vFilterDateTo" class="form-control form-control-sm">
        </div>
    </div>
    <div class="row g-2 align-items-end mt-1">
        <!-- Row 2: Advanced filters + search -->
        <div class="col-md-2">
            <label class="form-label small mb-1">Domain</label>
            <input type="text" id="vFilterDomain" class="form-control form-control-sm" placeholder="e.g. example.com">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">CTA</label>
            <input type="text" id="vFilterCta" class="form-control form-control-sm" placeholder="e.g. Sign Up">
        </div>
        <div class="col-md-1">
            <label class="form-label small mb-1">Sentiment</label>
            <select id="vFilterSentiment" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="aggressive">Aggressive</option>
                <option value="moderate">Moderate</option>
                <option value="soft">Soft</option>
                <option value="neutral">Neutral</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label small mb-1">Hook</label>
            <select id="vFilterHook" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="urgency">Urgency</option>
                <option value="scarcity">Scarcity</option>
                <option value="social_proof">Social Proof</option>
                <option value="free_offer">Free Offer</option>
                <option value="discount">Discount</option>
                <option value="guarantee">Guarantee</option>
                <option value="authority">Authority</option>
                <option value="curiosity">Curiosity</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Tag</label>
            <select id="vFilterTag" class="form-select form-select-sm">
                <option value="">All Tags</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Search</label>
            <input type="text" id="vFilterSearch" class="form-control form-control-sm" placeholder="Search headlines, descriptions...">
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-primary btn-sm flex-grow-1" onclick="viewerLoad()" title="Apply filters">
                <i class="bi bi-search"></i>
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="viewerClearFilters()" title="Clear all filters">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
    <!-- Active filter pills + view toggle -->
    <div class="d-flex justify-content-between align-items-center mt-2" id="viewerToolbar">
        <div id="vActiveFilters" class="d-flex flex-wrap gap-1"></div>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-primary active" id="vViewGrid" onclick="viewerSetView('grid')" title="Grid view">
                <i class="bi bi-grid-3x3-gap-fill"></i>
            </button>
            <button type="button" class="btn btn-outline-primary" id="vViewTable" onclick="viewerSetView('table')" title="Table view">
                <i class="bi bi-list-ul"></i>
            </button>
            <div class="dropdown d-inline-block ms-2">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-sort-down me-1"></i><span id="vSortLabel">Newest</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="viewerSetSort('newest'); return false;">Newest First</a></li>
                    <li><a class="dropdown-item" href="#" onclick="viewerSetSort('oldest'); return false;">Oldest First</a></li>
                    <li><a class="dropdown-item" href="#" onclick="viewerSetSort('last_seen'); return false;">Last Seen</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="viewerSetSort('views_desc'); return false;">Most Views</a></li>
                    <li><a class="dropdown-item" href="#" onclick="viewerSetSort('views_asc'); return false;">Least Views</a></li>
                </ul>
            </div>
            <div class="dropdown d-inline-block ms-1">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <span id="vPerPageLabel">20</span>/page
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="viewerSetPerPage(12); return false;">12</a></li>
                    <li><a class="dropdown-item" href="#" onclick="viewerSetPerPage(20); return false;">20</a></li>
                    <li><a class="dropdown-item" href="#" onclick="viewerSetPerPage(40); return false;">40</a></li>
                    <li><a class="dropdown-item" href="#" onclick="viewerSetPerPage(100); return false;">100</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Results container -->
<div id="vResults">
    <div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>
</div>

<!-- Pagination -->
<div id="vPagination" class="mt-3"></div>

<!-- Detail Modal -->
<div class="modal fade" id="adDetailModal" tabindex="-1" aria-labelledby="adDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adDetailModalLabel">Ad Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="adDetailBody">
                <div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <a id="modalOpenCreative" href="#" class="btn btn-outline-primary btn-sm" target="_blank">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Open Full Detail
                    </a>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // ── State ──────────────────────────────────────────────
    const S = {
        page: 1,
        perPage: 20,
        view: 'grid',       // 'grid' | 'table'
        sort: 'newest',     // 'newest' | 'oldest' | 'last_seen'
        filters: {},        // current applied filter key→value
        filterOptions: null, // cached {advertisers, countries, platforms}
        totalAds: 0,
        activeAds: 0,
        inactiveAds: 0,
        debounceTimer: null,
    };

    // ── Hash ↔ State ───────────────────────────────────────
    const FILTER_KEYS = [
        'advertiser_id', 'product_id', 'country', 'platform', 'ad_type', 'status',
        'date_from', 'date_to', 'domain', 'cta', 'sentiment',
        'hook', 'tag', 'search'
    ];
    const ELEMENT_MAP = {
        advertiser_id: 'vFilterAdvertiser',
        product_id:    'vFilterProduct',
        country:       'vFilterCountry',
        platform:      'vFilterPlatform',
        ad_type:       'vFilterType',
        status:        'vFilterStatus',
        date_from:     'vFilterDateFrom',
        date_to:       'vFilterDateTo',
        domain:        'vFilterDomain',
        cta:           'vFilterCta',
        sentiment:     'vFilterSentiment',
        hook:          'vFilterHook',
        tag:           'vFilterTag',
        search:        'vFilterSearch',
    };

    function readHash() {
        const h = location.hash.substring(1);
        if (!h) return {};
        const obj = {};
        h.split('&').forEach(pair => {
            const [k, v] = pair.split('=').map(decodeURIComponent);
            if (k && v) obj[k] = v;
        });
        return obj;
    }

    function writeHash() {
        const parts = [];
        FILTER_KEYS.forEach(k => {
            if (S.filters[k]) parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(S.filters[k]));
        });
        if (S.page > 1) parts.push('page=' + S.page);
        if (S.view !== 'grid') parts.push('view=' + S.view);
        if (S.perPage !== 20) parts.push('per_page=' + S.perPage);
        if (S.sort !== 'newest') parts.push('sort=' + S.sort);
        const hash = parts.join('&');
        history.replaceState(null, '', hash ? '#' + hash : location.pathname + location.search);
    }

    function applyHashToState() {
        const h = readHash();
        FILTER_KEYS.forEach(k => {
            S.filters[k] = h[k] || '';
        });
        S.page = parseInt(h.page) || 1;
        S.view = h.view === 'table' ? 'table' : 'grid';
        S.perPage = [12,20,40,100].includes(parseInt(h.per_page)) ? parseInt(h.per_page) : 20;
        S.sort = ['newest','oldest','last_seen','views_desc','views_asc'].includes(h.sort) ? h.sort : 'newest';
    }

    function syncFormFromState() {
        FILTER_KEYS.forEach(k => {
            const el = document.getElementById(ELEMENT_MAP[k]);
            if (el) el.value = S.filters[k] || '';
        });
        document.getElementById('vViewGrid').classList.toggle('active', S.view === 'grid');
        document.getElementById('vViewTable').classList.toggle('active', S.view === 'table');
        document.getElementById('vPerPageLabel').textContent = S.perPage;
        const sortLabels = { newest: 'Newest', oldest: 'Oldest', last_seen: 'Last Seen', views_desc: 'Most Views', views_asc: 'Least Views' };
        document.getElementById('vSortLabel').textContent = sortLabels[S.sort] || 'Newest';
    }

    function readFormToState() {
        FILTER_KEYS.forEach(k => {
            const el = document.getElementById(ELEMENT_MAP[k]);
            S.filters[k] = el ? el.value.trim() : '';
        });
    }

    // ── Filter Pills ───────────────────────────────────────
    const LABEL_MAP = {
        advertiser_id: 'Advertiser', product_id: 'App', country: 'Country', platform: 'Platform',
        ad_type: 'Type', status: 'Status', date_from: 'From', date_to: 'To',
        domain: 'Domain', cta: 'CTA', sentiment: 'Sentiment', hook: 'Hook',
        tag: 'Tag', search: 'Search',
    };

    function renderFilterPills() {
        const container = document.getElementById('vActiveFilters');
        const pills = [];
        FILTER_KEYS.forEach(k => {
            if (S.filters[k]) {
                var displayVal = S.filters[k];
                // For product_id, show the product name from dropdown text
                if (k === 'product_id') {
                    var pSel = document.getElementById('vFilterProduct');
                    if (pSel && pSel.selectedIndex > 0) {
                        displayVal = pSel.options[pSel.selectedIndex].textContent;
                    }
                }
                if (k === 'platform') {
                    var platLabels = { ios: 'iOS', playstore: 'Play Store', web: 'Web' };
                    displayVal = platLabels[displayVal] || displayVal;
                }
                pills.push(`<span class="badge bg-primary d-inline-flex align-items-center gap-1 viewer-pill"
                    >${LABEL_MAP[k]}: ${escapeHtml(displayVal)}
                    <i class="bi bi-x-circle" role="button" data-filter="${k}" style="cursor:pointer"></i></span>`);
            }
        });
        container.innerHTML = pills.length
            ? pills.join('')
            : '<small class="text-muted">No active filters</small>';

        container.querySelectorAll('[data-filter]').forEach(btn => {
            btn.addEventListener('click', function() {
                const key = this.dataset.filter;
                S.filters[key] = '';
                const el = document.getElementById(ELEMENT_MAP[key]);
                if (el) el.value = '';
                S.page = 1;
                viewerLoad();
            });
        });
    }

    // ── Click-to-Filter (interlinked entities) ─────────────
    function clickFilter(key, value) {
        S.filters[key] = value;
        const el = document.getElementById(ELEMENT_MAP[key]);
        if (el) {
            // If the option doesn't exist in the select, add it
            if (el.tagName === 'SELECT' && !Array.from(el.options).some(o => o.value === value)) {
                const opt = document.createElement('option');
                opt.value = value;
                opt.textContent = value;
                el.appendChild(opt);
            }
            el.value = value;
        }
        S.page = 1;
        viewerLoad();
    }
    window.viewerClickFilter = clickFilter;

    // ── API call ───────────────────────────────────────────
    async function loadData() {
        const params = { page: S.page, per_page: S.perPage, sort: S.sort };

        FILTER_KEYS.forEach(k => {
            if (S.filters[k]) {
                params[k] = S.filters[k];
            }
        });

        const endpoint = 'ads.php';

        const data = await fetchAPI(endpoint, params);
        if (!data.success) throw new Error('API returned success=false');
        return { data, endpoint };
    }

    async function loadStats() {
        try {
            const overview = await fetchAPI('overview.php', {
                advertiser_id: S.filters.advertiser_id || null
            });
            if (overview.success && overview.stats) {
                S.totalAds = overview.stats.total_ads || 0;
                S.activeAds = overview.stats.active_ads || 0;
                S.inactiveAds = S.totalAds - S.activeAds;
                document.getElementById('vStatTotal').textContent = formatNumber(S.totalAds);
                document.getElementById('vStatActive').textContent = formatNumber(S.activeAds);
                document.getElementById('vStatInactive').textContent = formatNumber(S.inactiveAds);
            }
        } catch (e) { /* stats are non-critical */ }
    }

    // ── Main Load ──────────────────────────────────────────
    async function load() {
        writeHash();
        renderFilterPills();
        syncFormFromState();

        const resultsEl = document.getElementById('vResults');
        resultsEl.innerHTML = '<div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>';
        document.getElementById('vPagination').innerHTML = '';

        // Load stats separately so it can't crash the main load
        loadStats();

        try {
            const { data, endpoint } = await loadData();

            const ads = data.ads || data.results || [];
            const total = data.total || 0;
            const totalPages = data.total_pages || 1;
            const page = data.page || 1;

            document.getElementById('vStatShown').textContent = formatNumber(total);

            // Populate filter dropdowns from ads.php filter_options
            if (endpoint === 'ads.php' && data.filter_options) {
                S.filterOptions = data.filter_options;
                if (!S._dropdownsInitialized) {
                    populateViewerDropdowns(data.filter_options);
                    S._dropdownsInitialized = true;
                }
                // Always refresh app dropdown based on current platform filter
                refreshAppDropdown();
            }

            if (S.view === 'grid') {
                renderGrid(ads);
            } else {
                renderTable(ads);
            }

            renderPag(page, totalPages, total);

        } catch (err) {
            console.error('Viewer load error:', err);
            resultsEl.innerHTML = `<div class="text-center text-danger py-5"><i class="bi bi-exclamation-triangle me-2"></i>Failed to load ads: ${typeof escapeHtml === 'function' ? escapeHtml(err.message) : err.message}<br><small class="text-muted mt-2 d-block">Check browser console for details</small></div>`;
        }
    }
    window.viewerLoad = load;

    // ── Populate dropdowns ────────────────────────────────
    function populateViewerDropdowns(options) {
        addOptions('vFilterAdvertiser', options.advertisers || [], 'advertiser_id', 'name');
        // Country dropdown: show flag + full name
        var countrySel = document.getElementById('vFilterCountry');
        if (countrySel && countrySel.options.length <= 1) {
            (options.countries || []).forEach(function(item) {
                var code = item.country;
                var opt = document.createElement('option');
                opt.value = code;
                opt.textContent = countryFlag(code) + ' ' + countryName(code) + ' (' + code + ')';
                countrySel.appendChild(opt);
            });
        }
        refreshAppDropdown();
        syncFormFromState();
    }

    function refreshAppDropdown() {
        var productSel = document.getElementById('vFilterProduct');
        if (!productSel || !S.filterOptions || !S.filterOptions.products) return;
        var currentVal = S.filters.product_id || '';
        // Clear all options except "All Apps"
        productSel.length = 1;
        var selectedPlatform = S.filters.platform || '';
        var selectedAdvertiser = S.filters.advertiser_id || '';
        (S.filterOptions.products || []).forEach(function(p) {
            if (p.product_name === 'Unknown') return;
            if (p.store_platform !== 'ios' && p.store_platform !== 'playstore') return;
            // Filter by selected platform
            if (selectedPlatform && selectedPlatform !== 'web' && p.store_platform !== selectedPlatform) return;
            // Filter by selected advertiser
            if (selectedAdvertiser && p.advertiser_id !== selectedAdvertiser) return;
            var opt = document.createElement('option');
            opt.value = p.product_id;
            opt.textContent = p.product_name + ' (' + (p.ad_count || 0) + ')';
            productSel.appendChild(opt);
        });
        // Restore selection if still valid
        productSel.value = currentVal;
        if (productSel.value !== currentVal) {
            // Selected app no longer in filtered list, clear it
            S.filters.product_id = '';
            productSel.value = '';
        }
    }

    function addOptions(selectId, items, valueKey, labelKey) {
        const sel = document.getElementById(selectId);
        if (!sel || sel.options.length > 1) return;
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = item[labelKey];
            sel.appendChild(opt);
        });
    }

    async function loadTags() {
        // Tags feature placeholder — tags API not yet implemented
    }

    // ── Grid Render ────────────────────────────────────────
    // Copy text to clipboard with button feedback
    window.copyAdText = function(btn, text) {
        var t = text.replace(/\\n/g, '\n');
        navigator.clipboard.writeText(t).then(function() {
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
            btn.classList.remove('btn-outline-secondary', 'btn-outline-danger');
            btn.classList.add('btn-success');
            setTimeout(function() {
                btn.innerHTML = orig;
                btn.classList.remove('btn-success');
                if (orig.indexOf('youtube') >= 0 || orig.indexOf('YouTube') >= 0) {
                    btn.classList.add('btn-outline-danger');
                } else {
                    btn.classList.add('btn-outline-secondary');
                }
            }, 1500);
        });
    };

    function renderGrid(ads) {
        const container = document.getElementById('vResults');
        if (ads.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size:3rem"></i><h5 class="mt-2">No ads found</h5><p>Try adjusting your filters</p></div>';
            return;
        }

        var cards = [];
        ads.forEach(function(ad) {
            try {
                var countries = (ad.countries || '').split(',').map(function(c) { return c.trim(); }).filter(Boolean);
                var isVideo = ad.ad_type === 'video';
                var advName = ad.advertiser_name || ad.advertiser_id || '';
                var transparencyUrl = 'https://adstransparency.google.com/advertiser/' + encodeURIComponent(ad.advertiser_id) + '/creative/' + encodeURIComponent(ad.creative_id);
                var headline = ad.headline || advName;
                var productName = ad.product_names ? ad.product_names.split('||')[0] : '';
                var productIdVal = ad.product_id || '';
                var storePlatform = ad.store_platform || 'web';
                var platformLabels = { ios: 'iOS', playstore: 'Play Store', web: 'Web' };
                var platformIcons = { ios: 'bi-apple', playstore: 'bi-google-play', web: 'bi-globe' };
                var platformColors = { ios: 'bg-dark', playstore: 'bg-success', web: 'bg-info' };
                var viewCount = parseInt(ad.view_count) || 0;
                var viewCountStr = viewCount > 0 ? formatNumber(viewCount) : '';

                // Thumbnail
                var thumbHtml = '';
                var ytId = ad.youtube_url ? extractYouTubeId(ad.youtube_url) : null;
                var thumbSrc = ad.preview_image || (ytId ? 'https://i.ytimg.com/vi/' + ytId + '/hqdefault.jpg' : null);
                if (thumbSrc) {
                    thumbHtml = '<div class="ad-thumb"><img src="' + escapeHtml(thumbSrc) + '" alt="" loading="lazy">' +
                        (isVideo ? '<span class="ad-play-icon"><i class="bi bi-play-fill"></i></span>' : '') +
                        (viewCount > 0 ? '<span class="ad-view-count"><i class="bi bi-eye-fill me-1"></i>' + viewCountStr + ' views</span>' : '') +
                        '</div>';
                } else if (isVideo) {
                    thumbHtml = '<div class="ad-thumb d-flex align-items-center justify-content-center" style="background:#1a1a2e"><i class="bi bi-play-circle" style="font-size:3rem;color:rgba(255,255,255,.5)"></i></div>';
                } else if (ad.preview_url) {
                    thumbHtml = '<div class="ad-thumb ad-thumb-preview"><iframe src="' + escapeHtml(ad.preview_url) + '" sandbox="allow-scripts allow-same-origin" loading="lazy" scrolling="no" style="width:100%;height:100%;border:none;pointer-events:none"></iframe></div>';
                }

                // Landing URL domain
                var landingHtml = '';
                if (ad.landing_url && ad.landing_url.indexOf('displayads-formats') === -1) {
                    try {
                        var h = new URL(ad.landing_url).hostname.replace('www.', '');
                        landingHtml = '<div class="mt-1"><a href="' + escapeHtml(ad.landing_url) + '" target="_blank" rel="noopener" class="badge bg-light text-dark text-decoration-none" onclick="event.stopPropagation()" title="' + escapeHtml(ad.landing_url) + '"><i class="bi bi-link-45deg"></i> ' + escapeHtml(h.substring(0,30)) + '</a></div>';
                    } catch(e) {}
                }

                // Country badges with flags + full names
                var countryHtml = '';
                if (countries.length > 0) {
                    countryHtml = countries.map(function(c) {
                        var flag = countryFlag(c);
                        var name = countryName(c);
                        return '<span class="badge bg-secondary bg-opacity-75 viewer-clickable" data-filter="country" data-value="' + escapeHtml(c) + '" style="cursor:pointer" title="' + escapeHtml(name) + ' (' + escapeHtml(c) + ')">' + flag + '</span>';
                    }).join(' ');
                } else {
                    countryHtml = '<span class="badge bg-light text-muted" style="font-size:.65rem"><i class="bi bi-geo-alt"></i> No country data</span>';
                }

                // YouTube link
                var ytLink = '';
                if (isVideo && ad.youtube_url && ytId) {
                    ytLink = '<a href="youtube_profile.php?id=' + encodeURIComponent(ytId) + '" class="btn btn-outline-danger btn-sm viewer-ext-link" onclick="event.stopPropagation()"><i class="bi bi-youtube me-1"></i>YouTube</a> ';
                }

                // Product badge
                var productHtml = '';
                if (productName && productName !== 'Unknown') {
                    productHtml = '<div class="mt-1"><a href="app_profile.php?id=' + encodeURIComponent(productIdVal) + '" class="badge bg-warning text-dark me-1 text-decoration-none" onclick="event.stopPropagation()" title="View App Profile"><i class="bi bi-app-indicator me-1"></i>' + escapeHtml(productName) + '</a>' +
                        '<span class="badge ' + (platformColors[storePlatform] || 'bg-info') + ' viewer-clickable" data-filter="platform" data-value="' + escapeHtml(storePlatform) + '" title="Platform"><i class="bi ' + (platformIcons[storePlatform] || 'bi-globe') + ' me-1"></i>' + (platformLabels[storePlatform] || 'Web') + '</span></div>';
                }

                cards.push('<div class="col-md-6 col-lg-4 col-xl-3 mb-4">' +
                    '<div class="ad-card viewer-card" role="button" data-id="' + escapeHtml(ad.creative_id) + '">' +
                    thumbHtml +
                    '<div class="ad-card-header"><div class="d-flex justify-content-between align-items-center"><div>' +
                    '<span class="badge badge-' + (ad.ad_type || 'text') + ' viewer-clickable" data-filter="ad_type" data-value="' + escapeHtml(ad.ad_type) + '">' + (ad.ad_type || 'text') + '</span> ' +
                    '<span class="badge ' + (ad.status === 'active' ? 'badge-active' : 'badge-inactive') + ' viewer-clickable" data-filter="status" data-value="' + escapeHtml(ad.status) + '">' + ad.status + '</span>' +
                    '</div><small class="text-muted">' + formatDate(ad.last_seen) + '</small></div></div>' +
                    '<div class="ad-body">' +
                    '<div class="ad-headline">' + escapeHtml(headline) + '</div>' +
                    (ad.description ? '<div class="ad-description">' + escapeHtml(ad.description.substring(0, 150)) + '</div>' : '') +
                    (ad.cta ? '<div class="mt-1"><span class="badge bg-primary">' + escapeHtml(ad.cta) + '</span></div>' : '') +
                    productHtml +
                    landingHtml +
                    '<div class="mt-2 d-flex flex-wrap gap-1">' +
                    (ad.headline || ad.description ? '<button class="btn btn-outline-secondary btn-sm viewer-ext-link" onclick="event.stopPropagation();copyAdText(this,' + "'" + escapeHtml((ad.headline || '') + (ad.description ? '\\n' + ad.description : '')) + "'" + ')" title="Copy ad text"><i class="bi bi-clipboard me-1"></i>Copy Text</button> ' : '') +
                    (ad.youtube_url ? '<button class="btn btn-outline-danger btn-sm viewer-ext-link" onclick="event.stopPropagation();copyAdText(this,' + "'" + escapeHtml(ad.youtube_url) + "'" + ')" title="Copy YouTube URL"><i class="bi bi-youtube me-1"></i>Copy URL</button> ' : '') +
                    ytLink +
                    '<a href="' + escapeHtml(transparencyUrl) + '" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm viewer-ext-link" onclick="event.stopPropagation()"><i class="bi bi-box-arrow-up-right me-1"></i>Google</a>' +
                    '</div></div>' +
                    '<div class="ad-meta"><div class="d-flex flex-wrap gap-1 mb-1">' + countryHtml + '</div>' +
                    '<div class="d-flex justify-content-between align-items-center">' +
                    '<a href="advertiser_profile.php?id=' + encodeURIComponent(ad.advertiser_id) + '" class="small text-muted text-decoration-none" onclick="event.stopPropagation()" title="View Advertiser Profile"><i class="bi bi-person-fill me-1"></i>' + escapeHtml(advName) + '</a>' +
                    '<small class="text-muted">' + formatDate(ad.first_seen) + ' - ' + formatDate(ad.last_seen) + '</small>' +
                    '</div></div>' +
                    '</div></div>');
            } catch(cardErr) {
                console.error('Card render error for', ad.creative_id, cardErr);
                cards.push('<div class="col-md-6 col-lg-4 col-xl-3 mb-4"><div class="card p-3 text-danger">Error rendering ad ' + (ad.creative_id || '') + '</div></div>');
            }
        });
        container.innerHTML = '<div class="row">' + cards.join('') + '</div>';
        bindCardEvents(container);
    }

    // ── Table Render ───────────────────────────────────────
    function renderTable(ads) {
        var container = document.getElementById('vResults');
        if (ads.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size:3rem"></i><h5 class="mt-2">No ads found</h5></div>';
            return;
        }

        var html = '<div class="table-container"><div class="table-responsive"><table class="table table-hover mb-0">' +
            '<thead><tr><th>Advertiser</th><th>Headline</th><th>App</th><th>Platform</th><th>Views</th><th>Type</th>' +
            '<th>Status</th><th>Countries</th><th>First Seen</th><th>Last Seen</th><th>Links</th></tr></thead><tbody>';

        var tblPlatformLabels = { ios: 'iOS', playstore: 'Play Store', web: 'Web' };

        ads.forEach(function(ad) {
            try {
                var countries = (ad.countries || '').split(',').map(function(c) { return c.trim(); }).filter(Boolean);
                var advName = ad.advertiser_name || ad.advertiser_id || '-';
                var pName = ad.product_names ? ad.product_names.split('||')[0] : '';
                var sPlatform = ad.store_platform || 'web';
                var vCount = parseInt(ad.view_count) || 0;
                var tUrl = 'https://adstransparency.google.com/advertiser/' + encodeURIComponent(ad.advertiser_id) + '/creative/' + encodeURIComponent(ad.creative_id);
                var hl = ad.headline || '';

                var countryTd = countries.length > 0 ? countries.map(function(c) {
                    return '<span class="badge bg-secondary bg-opacity-75 viewer-clickable" data-filter="country" data-value="' + escapeHtml(c) + '" style="cursor:pointer" title="' + escapeHtml(countryName(c)) + '">' + countryFlag(c) + '</span>';
                }).join(' ') : '<small class="text-muted">-</small>';

                html += '<tr class="viewer-row" role="button" data-id="' + escapeHtml(ad.creative_id) + '">' +
                    '<td><span class="viewer-clickable text-primary" data-filter="advertiser_id" data-value="' + escapeHtml(ad.advertiser_id) + '" style="cursor:pointer">' + escapeHtml(advName) + '</span></td>' +
                    '<td style="max-width:200px"><div class="text-truncate fw-bold">' + escapeHtml(hl || '-') + '</div>' + (ad.description ? '<div class="text-truncate text-muted small">' + escapeHtml(ad.description.substring(0,60)) + '</div>' : '') + '</td>' +
                    '<td>' + (pName && pName !== 'Unknown' ? '<span class="badge bg-warning text-dark" style="font-size:.75rem">' + escapeHtml(pName) + '</span>' : '<small class="text-muted">-</small>') + '</td>' +
                    '<td><span class="badge bg-info" style="font-size:.75rem">' + (tblPlatformLabels[sPlatform] || 'Web') + '</span></td>' +
                    '<td>' + (vCount > 0 ? '<strong>' + formatNumber(vCount) + '</strong>' : '<small class="text-muted">-</small>') + '</td>' +
                    '<td><span class="badge badge-' + (ad.ad_type || 'text') + '">' + (ad.ad_type || 'text') + '</span></td>' +
                    '<td><span class="badge ' + (ad.status === 'active' ? 'badge-active' : 'badge-inactive') + '">' + ad.status + '</span></td>' +
                    '<td>' + countryTd + '</td>' +
                    '<td><small>' + formatDate(ad.first_seen) + '</small></td>' +
                    '<td><small>' + formatDate(ad.last_seen) + '</small></td>' +
                    '<td><a href="' + escapeHtml(tUrl) + '" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm py-0 px-1" onclick="event.stopPropagation()"><i class="bi bi-box-arrow-up-right"></i></a>' +
                    (ad.youtube_url ? ' <a href="' + escapeHtml(ad.youtube_url) + '" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="event.stopPropagation()"><i class="bi bi-youtube"></i></a>' : '') + '</td></tr>';
            } catch(e) {
                console.error('Table row error:', ad.creative_id, e);
            }
        });

        html += '</tbody></table></div></div>';
        container.innerHTML = html;
        bindCardEvents(container);
    }

    // ── Card/Row click + click-to-filter delegation ───────
    function bindCardEvents(container) {
        // Click-to-filter badges (stop propagation so it doesn't open modal)
        container.querySelectorAll('.viewer-clickable').forEach(el => {
            el.style.cursor = 'pointer';
            el.addEventListener('click', function(e) {
                e.stopPropagation();
                clickFilter(this.dataset.filter, this.dataset.value);
            });
        });

        // Card/row click → open detail modal
        container.querySelectorAll('[data-id].viewer-card, [data-id].viewer-row').forEach(el => {
            el.addEventListener('click', function() {
                openDetail(this.dataset.id);
            });
        });
    }

    // ── Detail Modal ───────────────────────────────────────
    async function openDetail(creativeId) {
        const body = document.getElementById('adDetailBody');
        body.innerHTML = '<div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>';
        document.getElementById('adDetailModalLabel').textContent = 'Loading...';
        document.getElementById('modalOpenCreative').href = 'creative.php?id=' + encodeURIComponent(creativeId);

        const modal = new bootstrap.Modal(document.getElementById('adDetailModal'));
        modal.show();

        try {
            const [creative] = await Promise.all([
                fetchAPI('creative.php', { id: creativeId }),
            ]);
            const intel = null;

            if (!creative.success) {
                body.innerHTML = '<div class="text-danger">Failed to load detail</div>';
                return;
            }

            const ad = creative.ad;
            const detail = creative.details[0] || {};
            const assets = creative.assets || [];
            const targeting = creative.targeting || [];
            const products = creative.products || [];
            const youtube = creative.youtube || null;
            const countries = creative.countries || [];

            const transparencyUrl = 'https://adstransparency.google.com/advertiser/' + encodeURIComponent(ad.advertiser_id) + '/creative/' + encodeURIComponent(ad.creative_id);
            const advName = ad.advertiser_name || ad.advertiser_id;
            document.getElementById('adDetailModalLabel').textContent = escapeHtml(detail.headline || advName || ad.creative_id);

            let html = '';

            // Parse JSON fields
            let headlinesArr = [];
            let descriptionsArr = [];
            let trackingArr = [];
            try { if (detail.headlines_json) headlinesArr = JSON.parse(detail.headlines_json); } catch(e) {}
            try { if (detail.descriptions_json) descriptionsArr = JSON.parse(detail.descriptions_json); } catch(e) {}
            try { if (detail.tracking_ids_json) trackingArr = JSON.parse(detail.tracking_ids_json); } catch(e) {}

            // Header row
            html += `<div class="row mb-3">
                <div class="col-md-8">
                    ${detail.headline ? `<h5>${escapeHtml(detail.headline)}</h5>` : ''}
                    ${detail.description ? `<p class="text-muted">${escapeHtml(detail.description)}</p>` : ''}
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        ${detail.cta ? `<span class="badge bg-primary viewer-clickable" data-filter="cta" data-value="${escapeHtml(detail.cta)}" style="cursor:pointer">${escapeHtml(detail.cta)}</span>` : ''}
                        ${detail.landing_url ? `<a href="${escapeHtml(detail.landing_url)}" target="_blank" rel="noopener" class="badge bg-light text-dark text-decoration-none"><i class="bi bi-link-45deg me-1"></i>${escapeHtml((detail.landing_url || '').substring(0, 60))}</a>` : ''}
                        ${detail.display_url ? `<span class="badge bg-light text-muted"><i class="bi bi-globe2 me-1"></i>${escapeHtml(detail.display_url)}</span>` : ''}
                        ${detail.ad_width && detail.ad_height ? `<span class="badge bg-light text-dark"><i class="bi bi-aspect-ratio me-1"></i>${detail.ad_width}x${detail.ad_height}</span>` : ''}
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <a href="advertiser_profile.php?id=${encodeURIComponent(ad.advertiser_id)}" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-building me-1"></i>${escapeHtml(advName)}
                        </a>
                        <a href="${escapeHtml(transparencyUrl)}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Google Ads Transparency
                        </a>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge badge-${ad.ad_type || 'text'} viewer-clickable" data-filter="ad_type" data-value="${escapeHtml(ad.ad_type)}" style="cursor:pointer">${ad.ad_type}</span>
                    <span class="badge ${ad.status === 'active' ? 'badge-active' : 'badge-inactive'} viewer-clickable" data-filter="status" data-value="${escapeHtml(ad.status)}" style="cursor:pointer">${ad.status}</span>
                    ${ad.view_count > 0 ? `<br><small class="text-muted"><i class="bi bi-eye me-1"></i>${formatNumber(ad.view_count)} views</small>` : ''}
                    <br><small class="text-muted">${escapeHtml(ad.creative_id)}</small>
                </div>
            </div>`;

            // Headline variations (responsive ads)
            if (headlinesArr.length > 1) {
                html += '<div class="mb-3"><h6 class="mb-2"><i class="bi bi-card-text me-1"></i>Headline Variations (' + headlinesArr.length + ')</h6><div class="d-flex flex-wrap gap-1">';
                headlinesArr.forEach(h => {
                    html += `<span class="badge bg-info bg-opacity-10 text-dark border">${escapeHtml(h)}</span>`;
                });
                html += '</div></div>';
            }

            // Description variations
            if (descriptionsArr.length > 1) {
                html += '<div class="mb-3"><h6 class="mb-2"><i class="bi bi-text-paragraph me-1"></i>Description Variations (' + descriptionsArr.length + ')</h6>';
                descriptionsArr.forEach(d => {
                    html += `<p class="small text-muted mb-1 border-start ps-2">${escapeHtml(d)}</p>`;
                });
                html += '</div>';
            }

            // Tracking IDs
            if (trackingArr.length > 0) {
                html += '<div class="mb-3"><h6 class="mb-2"><i class="bi bi-fingerprint me-1"></i>Tracking IDs</h6><div class="d-flex flex-wrap gap-1">';
                trackingArr.forEach(t => {
                    const colors = {ga_ua: 'warning', ga4: 'success', gtm: 'info', fb_pixel: 'primary'};
                    const labels = {ga_ua: 'GA', ga4: 'GA4', gtm: 'GTM', fb_pixel: 'FB Pixel'};
                    html += `<span class="badge bg-${colors[t.type] || 'secondary'} bg-opacity-75">${labels[t.type] || t.type}: ${escapeHtml(t.id)}</span>`;
                });
                html += '</div></div>';
            }

            // Products / Apps linked to this ad
            if (products.length > 0) {
                html += '<h6 class="mt-3 mb-2"><i class="bi bi-phone me-1"></i>Promoted Apps</h6><div class="d-flex flex-wrap gap-2 mb-3">';
                products.forEach(p => {
                    const icon = p.icon_url ? `<img src="${escapeHtml(p.icon_url)}" style="width:32px;height:32px;border-radius:8px;" class="me-2" onerror="this.style.display='none'">` : '';
                    const platform = p.store_platform === 'ios' ? '<i class="bi bi-apple"></i>' : '<i class="bi bi-google-play"></i>';
                    html += `<a href="app_profile.php?id=${p.product_id}" class="d-flex align-items-center text-decoration-none card p-2" style="min-width:200px;">
                        ${icon}
                        <div>
                            <div class="fw-bold small text-dark">${escapeHtml(p.app_name || p.product_name)}</div>
                            <div class="text-muted" style="font-size:0.75rem;">${platform} ${p.category ? escapeHtml(p.category) : ''} ${p.rating ? '&middot; ' + parseFloat(p.rating).toFixed(1) + '<i class="bi bi-star-fill text-warning ms-1" style="font-size:0.65rem;"></i>' : ''}</div>
                        </div>
                    </a>`;
                });
                html += '</div>';
            }

            // YouTube metadata panel
            if (youtube) {
                html += `<div class="card bg-light p-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-youtube text-danger fs-5"></i>
                        <div>
                            <div class="fw-bold small">${escapeHtml(youtube.title || '')}</div>
                            <div class="text-muted" style="font-size:0.75rem;">${escapeHtml(youtube.channel_name || '')} &middot; ${formatNumber(youtube.view_count)} views ${youtube.duration ? '&middot; ' + escapeHtml(youtube.duration) : ''}</div>
                        </div>
                        <a href="youtube_profile.php?id=${encodeURIComponent(youtube.video_id)}" class="btn btn-outline-danger btn-sm ms-auto"><i class="bi bi-box-arrow-up-right"></i></a>
                    </div>
                </div>`;
            }

            // Stats mini-cards
            html += `<div class="row mb-3">
                <div class="col-3"><div class="card p-2 text-center"><small class="text-muted">Duration</small><br><strong>${creative.duration_days || 0}d</strong></div></div>
                <div class="col-3"><div class="card p-2 text-center"><small class="text-muted">Versions</small><br><strong>${creative.version_count || 1}</strong></div></div>
                <div class="col-3"><div class="card p-2 text-center"><small class="text-muted">First Seen</small><br><strong>${formatDate(ad.first_seen)}</strong></div></div>
                <div class="col-3"><div class="card p-2 text-center"><small class="text-muted">Last Seen</small><br><strong>${formatDate(ad.last_seen)}</strong></div></div>
            </div>`;

            // Media assets — show preview iframes for Google preview URLs, real images for direct URLs
            const realAssets = assets.filter(a => a.type === 'image' && a.original_url && a.original_url.indexOf('displayads-formats') === -1);
            const previewAssets = assets.filter(a => (a.type === 'preview' || a.type === 'image') && a.original_url && a.original_url.indexOf('displayads-formats') !== -1);
            const videoAssets = assets.filter(a => a.type === 'video');

            if (realAssets.length > 0) {
                html += '<h6 class="mt-3 mb-2"><i class="bi bi-images me-1"></i>Images</h6><div class="row mb-3">';
                realAssets.forEach(asset => {
                    const src = asset.local_path || asset.original_url;
                    html += `<div class="col-md-4 mb-2"><img src="${escapeHtml(src)}" class="img-fluid rounded shadow-sm" alt="Ad asset" loading="lazy" style="cursor:zoom-in" onclick="window.open(this.src)"></div>`;
                });
                html += '</div>';
            }

            if (videoAssets.length > 0) {
                html += '<h6 class="mt-3 mb-2"><i class="bi bi-play-circle me-1"></i>Video</h6><div class="row mb-3">';
                videoAssets.forEach(asset => {
                    const url = asset.original_url || '';
                    const ytId = extractYouTubeId(url);
                    if (ytId) {
                        html += `<div class="col-md-8 mb-2">
                            <div class="ratio ratio-16x9 mb-2"><iframe src="https://www.youtube.com/embed/${escapeHtml(ytId)}" allowfullscreen class="rounded"></iframe></div>
                            <a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="btn btn-outline-danger btn-sm"><i class="bi bi-youtube me-1"></i>Open on YouTube</a>
                        </div>`;
                    }
                });
                html += '</div>';
            }

            if (previewAssets.length > 0) {
                html += '<h6 class="mt-3 mb-2"><i class="bi bi-eye me-1"></i>Ad Preview</h6><div class="row mb-3">';
                previewAssets.forEach(asset => {
                    html += `<div class="col-md-6 mb-2"><div class="ratio ratio-16x9 border rounded"><iframe src="${escapeHtml(asset.original_url)}" sandbox="allow-scripts allow-same-origin" class="rounded" style="width:100%;height:100%"></iframe></div></div>`;
                });
                html += '</div>';
            }

            // Targeting — countries with full names + flags + platforms
            const ctryList = countries.length > 0 ? countries : [...new Set(targeting.map(t => t.country).filter(Boolean))];
            const platList = [...new Set(targeting.map(t => t.platform).filter(Boolean))];
            html += '<h6 class="mt-3 mb-2"><i class="bi bi-geo-alt me-1"></i>Targeting Countries (' + ctryList.length + ')</h6>';
            if (ctryList.length > 0) {
                html += '<div class="mb-3"><div class="d-flex flex-wrap gap-1 mb-2">';
                ctryList.forEach(c => {
                    const flag = countryFlag(c);
                    const name = countryName(c);
                    html += `<span class="badge bg-secondary bg-opacity-75 viewer-clickable" data-filter="country" data-value="${escapeHtml(c)}" style="cursor:pointer;font-size:.8rem">${flag} ${escapeHtml(name)} (${escapeHtml(c)})</span>`;
                });
                html += '</div>';
                if (platList.length > 0) {
                    html += '<div class="d-flex flex-wrap gap-1">';
                    platList.forEach(p => { html += `<span class="badge bg-dark bg-opacity-75"><i class="bi bi-broadcast me-1"></i>${escapeHtml(p)}</span>`; });
                    html += '</div>';
                }
                html += '</div>';
            } else {
                html += '<div class="mb-3 text-muted small"><i class="bi bi-info-circle me-1"></i>No country targeting data available for this ad.</div>';
            }

            // Version history
            if (creative.details.length > 1) {
                html += '<h6 class="mt-3 mb-2"><i class="bi bi-clock-history me-1"></i>Version History</h6>';
                html += '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Headline</th><th>CTA</th><th>Landing URL</th></tr></thead><tbody>';
                creative.details.forEach(d => {
                    html += `<tr>
                        <td><small>${formatDate(d.snapshot_date)}</small></td>
                        <td>${escapeHtml(d.headline || '-')}</td>
                        <td>${escapeHtml(d.cta || '-')}</td>
                        <td class="text-truncate" style="max-width:200px">${escapeHtml(d.landing_url || '-')}</td>
                    </tr>`;
                });
                html += '</tbody></table></div>';
            }

            // Related ads section (same advertiser)
            html += `<h6 class="mt-3 mb-2"><i class="bi bi-collection me-1"></i>More from this Advertiser</h6>
                <div id="modalRelatedAds"><div class="loading-overlay" style="min-height:80px"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div></div>`;

            body.innerHTML = html;

            // Bind click-to-filter in modal
            body.querySelectorAll('.viewer-clickable').forEach(el => {
                el.addEventListener('click', function(e) {
                    e.stopPropagation();
                    modal.hide();
                    clickFilter(this.dataset.filter, this.dataset.value);
                });
            });

            // Load related ads
            loadRelatedAds(ad.advertiser_id, ad.creative_id);

        } catch (err) {
            console.error('Detail load error:', err);
            body.innerHTML = '<div class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Failed to load ad detail.</div>';
        }
    }

    async function loadRelatedAds(advertiserId, excludeId) {
        try {
            const data = await fetchAPI('ads.php', { advertiser_id: advertiserId, per_page: 6 });
            const container = document.getElementById('modalRelatedAds');
            if (!container || !data.success) return;

            const related = (data.ads || []).filter(a => a.creative_id !== excludeId).slice(0, 5);
            if (related.length === 0) {
                container.innerHTML = '<small class="text-muted">No other ads from this advertiser</small>';
                return;
            }

            container.innerHTML = '<div class="d-flex gap-2 overflow-auto pb-2">' + related.map(ad =>
                `<div class="card flex-shrink-0 viewer-related-card" style="width:180px;cursor:pointer" data-related-id="${escapeHtml(ad.creative_id)}">
                    <div class="p-2">
                        <small class="fw-bold d-block text-truncate">${escapeHtml(ad.headline || ad.advertiser_name || ad.creative_id)}</small>
                        <small>${typeBadge(ad.ad_type)} ${statusBadge(ad.status)}</small>
                        <small class="d-block text-muted mt-1">${formatDate(ad.last_seen)}</small>
                    </div>
                </div>`
            ).join('') + '</div>';

            container.querySelectorAll('.viewer-related-card').forEach(card => {
                card.addEventListener('click', function() {
                    openDetail(this.dataset.relatedId);
                });
            });
        } catch (e) {
            const container = document.getElementById('modalRelatedAds');
            if (container) container.innerHTML = '<small class="text-muted">Could not load related ads</small>';
        }
    }

    function extractYouTubeId(url) {
        if (!url) return null;
        const m = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([\w-]{11})/);
        return m ? m[1] : null;
    }

    // ── Pagination ─────────────────────────────────────────
    function renderPag(currentPage, totalPages, total) {
        const container = document.getElementById('vPagination');
        if (!container) return;
        if (totalPages <= 1 && total <= S.perPage) {
            container.innerHTML = `<div class="text-center"><small class="text-muted">${formatNumber(total)} total results</small></div>`;
            return;
        }

        let html = `<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">Showing ${((currentPage - 1) * S.perPage) + 1}–${Math.min(currentPage * S.perPage, total)} of ${formatNumber(total)}</small>
            <nav><ul class="pagination pagination-sm mb-0">`;

        // First
        if (currentPage > 2) html += `<li class="page-item"><a class="page-link" href="#" onclick="viewerGoPage(1); return false;">&laquo;</a></li>`;
        // Prev
        if (currentPage > 1) html += `<li class="page-item"><a class="page-link" href="#" onclick="viewerGoPage(${currentPage - 1}); return false;">Prev</a></li>`;

        const startPage = Math.max(1, currentPage - 3);
        const endPage = Math.min(totalPages, currentPage + 3);
        if (startPage > 1) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';

        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" onclick="viewerGoPage(${i}); return false;">${i}</a></li>`;
        }

        if (endPage < totalPages) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        // Next
        if (currentPage < totalPages) html += `<li class="page-item"><a class="page-link" href="#" onclick="viewerGoPage(${currentPage + 1}); return false;">Next</a></li>`;
        // Last
        if (currentPage < totalPages - 1) html += `<li class="page-item"><a class="page-link" href="#" onclick="viewerGoPage(${totalPages}); return false;">&raquo;</a></li>`;

        html += '</ul></nav></div>';
        container.innerHTML = html;
    }

    window.viewerGoPage = function(p) {
        S.page = p;
        load();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // ── View / Sort / PerPage controls ────────────────────
    window.viewerSetView = function(v) {
        S.view = v;
        document.getElementById('vViewGrid').classList.toggle('active', v === 'grid');
        document.getElementById('vViewTable').classList.toggle('active', v === 'table');
        load();
    };

    window.viewerSetSort = function(s) {
        S.sort = s;
        const labels = { newest: 'Newest', oldest: 'Oldest', last_seen: 'Last Seen', views_desc: 'Most Views', views_asc: 'Least Views' };
        document.getElementById('vSortLabel').textContent = labels[s] || s;
        S.page = 1;
        load();
    };

    window.viewerSetPerPage = function(n) {
        S.perPage = n;
        document.getElementById('vPerPageLabel').textContent = n;
        S.page = 1;
        load();
    };

    window.viewerClearFilters = function() {
        FILTER_KEYS.forEach(k => {
            S.filters[k] = '';
            const el = document.getElementById(ELEMENT_MAP[k]);
            if (el) el.value = '';
        });
        S.page = 1;
        load();
    };

    // ── Debounced search input ─────────────────────────────
    function setupDebounce() {
        const searchInput = document.getElementById('vFilterSearch');
        if (!searchInput) return;
        searchInput.addEventListener('input', function() {
            clearTimeout(S.debounceTimer);
            S.debounceTimer = setTimeout(() => {
                readFormToState();
                S.page = 1;
                load();
            }, 400);
        });
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                clearTimeout(S.debounceTimer);
                readFormToState();
                S.page = 1;
                load();
            }
        });
    }

    // Filter change handlers for dropdowns/date inputs
    function setupFilterListeners() {
        const onChange = () => {
            readFormToState();
            S.page = 1;
            load();
        };
        ['vFilterAdvertiser', 'vFilterProduct', 'vFilterCountry', 'vFilterPlatform', 'vFilterType',
         'vFilterStatus', 'vFilterDateFrom', 'vFilterDateTo', 'vFilterSentiment',
         'vFilterHook', 'vFilterTag'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', onChange);
        });

        // Debounce for text inputs (domain, cta)
        ['vFilterDomain', 'vFilterCta'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function() {
                clearTimeout(S.debounceTimer);
                S.debounceTimer = setTimeout(onChange, 400);
            });
            el.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') { clearTimeout(S.debounceTimer); onChange(); }
            });
        });
    }

    // ── Hash change listener (back/forward) ───────────────
    window.addEventListener('hashchange', function() {
        applyHashToState();
        syncFormFromState();
        load();
    });

    // ── Init ───────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        applyHashToState();
        syncFormFromState();
        setupDebounce();
        setupFilterListeners();
        loadTags();
        load();
    });

})();
</script>

<style>
/* Viewer-specific styles */
.viewer-card { cursor: pointer; transition: transform 0.15s, box-shadow 0.15s; border: 1px solid #e9ecef; border-radius: 8px; overflow: hidden; }
.viewer-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
.ad-thumb { position: relative; background: #000; aspect-ratio: 16/9; overflow: hidden; }
.ad-thumb img { width: 100%; height: 100%; object-fit: cover; }
.ad-thumb-preview { background: #f8f9fa; }
.ad-thumb-preview iframe { width: 100%; height: 100%; border: none; pointer-events: none; }
.ad-play-icon { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.6); color: #fff; border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
.ad-view-count { position: absolute; bottom: 6px; right: 6px; background: rgba(0,0,0,0.75); color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.ad-card-header { padding: 10px 12px 6px; border-bottom: 1px solid #f0f0f0; }
.ad-body { padding: 10px 12px; }
.ad-meta { padding: 8px 12px; border-top: 1px solid #f0f0f0; background: #fafafa; }
.ad-headline { font-weight: 600; font-size: 0.95rem; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.ad-description { font-size: 0.85rem; color: #6c757d; }
.viewer-ext-link { font-size: 0.75rem; }
.viewer-clickable:hover { opacity: 0.8; filter: brightness(1.1); }
.viewer-pill { font-size: 0.78rem; }
.viewer-related-card { transition: box-shadow 0.15s; }
.viewer-related-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
#adDetailModal .modal-body { max-height: 80vh; }
.viewer-row:hover td { background-color: rgba(67,97,238,0.04); }
.viewer-row { cursor: pointer; }
</style>

<?php require_once 'includes/footer.php'; ?>
