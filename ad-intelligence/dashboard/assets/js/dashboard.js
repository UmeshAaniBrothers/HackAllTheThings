/**
 * Ad Intelligence Dashboard - Frontend JavaScript
 *
 * Handles AJAX data loading, Chart.js rendering, and UI interactions.
 */

const API_BASE = 'api/';

// ============================================================
// Utility Functions
// ============================================================

async function fetchAPI(endpoint, params = {}) {
    const url = new URL(API_BASE + endpoint, window.location.href);
    Object.entries(params).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') {
            url.searchParams.set(k, v);
        }
    });

    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`API error: ${response.status}`);
    }
    return response.json();
}

function showLoading(containerId) {
    const el = document.getElementById(containerId);
    if (el) {
        el.innerHTML = `<div class="loading-overlay">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>`;
    }
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatNumber(num) {
    return new Intl.NumberFormat().format(num || 0);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function statusBadge(status) {
    const cls = status === 'active' ? 'badge-active' : 'badge-inactive';
    return `<span class="badge ${cls}">${status}</span>`;
}

function typeBadge(type) {
    const cls = `badge-${type || 'text'}`;
    return `<span class="badge ${cls}">${type || 'text'}</span>`;
}

// ============================================================
// Overview Page
// ============================================================

async function loadOverview() {
    try {
        const advertiserId = document.getElementById('advertiserFilter')?.value || null;
        const data = await fetchAPI('overview.php', { advertiser_id: advertiserId });

        if (!data.success) return;

        // Update KPI cards
        document.getElementById('totalAds').textContent = formatNumber(data.stats.total_ads);
        document.getElementById('activeAds').textContent = formatNumber(data.stats.active_ads);
        document.getElementById('newToday').textContent = formatNumber(data.stats.new_today);
        document.getElementById('avgDuration').textContent = data.stats.avg_campaign_days + 'd';

        // Velocity chart
        renderVelocityChart(data.velocity);

        // Ad type distribution
        renderTypeChart(data.stats.ad_types);

        // Recent activity table
        renderActivityTable(data.recent_activity);

        // Populate advertiser filter
        populateAdvertiserFilter(data.advertisers);

    } catch (err) {
        console.error('Overview load error:', err);
    }
}

function renderVelocityChart(velocity) {
    const ctx = document.getElementById('velocityChart');
    if (!ctx) return;

    if (window.velocityChartInstance) {
        window.velocityChartInstance.destroy();
    }

    window.velocityChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: velocity.map(v => v.date),
            datasets: [{
                label: 'New Ads',
                data: velocity.map(v => v.count),
                borderColor: '#4361ee',
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                fill: true,
                tension: 0.4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { ticks: { maxTicksAuto: true, maxRotation: 45 } }
            }
        }
    });
}

function renderTypeChart(adTypes) {
    const ctx = document.getElementById('typeChart');
    if (!ctx) return;

    if (window.typeChartInstance) {
        window.typeChartInstance.destroy();
    }

    const colors = { text: '#4361ee', image: '#ff9f1c', video: '#7209b7' };

    window.typeChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: adTypes.map(t => t.ad_type),
            datasets: [{
                data: adTypes.map(t => t.count),
                backgroundColor: adTypes.map(t => colors[t.ad_type] || '#6c757d'),
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function renderActivityTable(activities) {
    const tbody = document.getElementById('activityTable');
    if (!tbody) return;

    tbody.innerHTML = activities.map(a => `
        <tr>
            <td><a href="creative.php?id=${encodeURIComponent(a.creative_id)}">${escapeHtml(a.creative_id.substring(0, 12))}...</a></td>
            <td>${escapeHtml(a.headline || 'N/A')}</td>
            <td>${typeBadge(a.ad_type)}</td>
            <td>${statusBadge(a.status)}</td>
            <td>${formatDate(a.created_at)}</td>
        </tr>
    `).join('');
}

function populateAdvertiserFilter(advertisers) {
    const select = document.getElementById('advertiserFilter');
    if (!select || select.options.length > 1) return;

    advertisers.forEach(a => {
        const option = document.createElement('option');
        option.value = a.advertiser_id;
        option.textContent = `${a.advertiser_id} (${a.total_ads} ads)`;
        select.appendChild(option);
    });
}

// ============================================================
// Ad Explorer Page
// ============================================================

async function loadExplorer(page = 1) {
    try {
        const params = {
            page: page,
            per_page: 20,
            advertiser_id: document.getElementById('filterAdvertiser')?.value || null,
            country: document.getElementById('filterCountry')?.value || null,
            platform: document.getElementById('filterPlatform')?.value || null,
            ad_type: document.getElementById('filterType')?.value || null,
            status: document.getElementById('filterStatus')?.value || null,
            date_from: document.getElementById('filterDateFrom')?.value || null,
            date_to: document.getElementById('filterDateTo')?.value || null,
            search: document.getElementById('filterSearch')?.value || null,
        };

        const data = await fetchAPI('ads.php', params);
        if (!data.success) return;

        renderAdGrid(data.ads);
        renderPagination(data.page, data.total_pages, data.total);
        populateFilterOptions(data.filter_options);

    } catch (err) {
        console.error('Explorer load error:', err);
    }
}

function renderAdGrid(ads) {
    const container = document.getElementById('adGrid');
    if (!container) return;

    if (ads.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted py-5"><h5>No ads found</h5></div>';
        return;
    }

    container.innerHTML = ads.map(ad => `
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <div class="ad-card">
                <div class="ad-media">
                    ${ad.preview_image
                        ? `<img src="${escapeHtml(ad.preview_image)}" alt="Ad preview" loading="lazy">`
                        : `<i class="bi bi-image no-media"></i>`
                    }
                </div>
                <div class="ad-body">
                    <div class="ad-headline">${escapeHtml(ad.headline || 'No headline')}</div>
                    <div class="ad-description">${escapeHtml(ad.description || 'No description')}</div>
                    ${ad.cta ? `<span class="badge bg-primary mt-2">${escapeHtml(ad.cta)}</span>` : ''}
                </div>
                <div class="ad-meta d-flex justify-content-between align-items-center">
                    <div>
                        ${typeBadge(ad.ad_type)} ${statusBadge(ad.status)}
                    </div>
                    <a href="creative.php?id=${encodeURIComponent(ad.creative_id)}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> View
                    </a>
                </div>
            </div>
        </div>
    `).join('');
}

function renderPagination(currentPage, totalPages, total) {
    const container = document.getElementById('pagination');
    if (!container) return;

    let html = `<div class="d-flex justify-content-between align-items-center">
        <span class="text-muted">${formatNumber(total)} results</span>
        <nav><ul class="pagination mb-0">`;

    if (currentPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadExplorer(${currentPage - 1}); return false;">Prev</a></li>`;
    }

    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="loadExplorer(${i}); return false;">${i}</a>
        </li>`;
    }

    if (currentPage < totalPages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadExplorer(${currentPage + 1}); return false;">Next</a></li>`;
    }

    html += `</ul></nav></div>`;
    container.innerHTML = html;
}

function populateFilterOptions(options) {
    populateSelect('filterAdvertiser', options.advertisers, 'advertiser_id', 'advertiser_id');
    populateSelect('filterCountry', options.countries, 'country', 'country');
    populateSelect('filterPlatform', options.platforms, 'platform', 'platform');
}

function populateSelect(id, items, valueKey, labelKey) {
    const select = document.getElementById(id);
    if (!select || select.options.length > 1) return;

    items.forEach(item => {
        const option = document.createElement('option');
        option.value = item[valueKey];
        option.textContent = item[labelKey];
        select.appendChild(option);
    });
}

// ============================================================
// Timeline Page
// ============================================================

async function loadTimeline() {
    try {
        const advertiserId = document.getElementById('timelineAdvertiser')?.value || null;
        const from = document.getElementById('timelineFrom')?.value || null;
        const to = document.getElementById('timelineTo')?.value || null;

        const data = await fetchAPI('timeline.php', { advertiser_id: advertiserId, from, to });
        if (!data.success) return;

        renderTimelineChart(data.velocity);
        renderTimelineList(data.timeline);

        const statsEl = document.getElementById('timelineStats');
        if (statsEl) {
            statsEl.innerHTML = `
                <span class="badge bg-primary me-2">Avg: ${data.avg_per_day} ads/day</span>
                <span class="badge bg-warning me-2">${data.spikes.length} activity spikes</span>
                <span class="badge bg-info">${data.timeline.length} total ads</span>
            `;
        }

    } catch (err) {
        console.error('Timeline load error:', err);
    }
}

function renderTimelineChart(velocity) {
    const ctx = document.getElementById('timelineChart');
    if (!ctx) return;

    if (window.timelineChartInstance) {
        window.timelineChartInstance.destroy();
    }

    window.timelineChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: velocity.map(v => v.date),
            datasets: [{
                label: 'Ads Created',
                data: velocity.map(v => v.count),
                backgroundColor: 'rgba(67, 97, 238, 0.7)',
                borderColor: '#4361ee',
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { ticks: { maxRotation: 45 } }
            }
        }
    });
}

function renderTimelineList(timeline) {
    const container = document.getElementById('timelineList');
    if (!container) return;

    const recent = timeline.slice(-50).reverse();

    container.innerHTML = recent.map(ad => `
        <div class="timeline-item">
            <div class="d-flex justify-content-between">
                <strong><a href="creative.php?id=${encodeURIComponent(ad.creative_id)}">${escapeHtml(ad.creative_id.substring(0, 16))}...</a></strong>
                ${statusBadge(ad.status)} ${typeBadge(ad.ad_type)}
            </div>
            <small class="text-muted">
                ${formatDate(ad.first_seen)} &mdash; ${formatDate(ad.last_seen)}
            </small>
        </div>
    `).join('');
}

// ============================================================
// Creative Detail Page
// ============================================================

async function loadCreative(creativeId) {
    try {
        const data = await fetchAPI('creative.php', { id: creativeId });
        if (!data.success) return;

        renderCreativeDetail(data);

    } catch (err) {
        console.error('Creative load error:', err);
    }
}

function renderCreativeDetail(data) {
    const ad = data.ad;
    const detail = data.details[0] || {};

    // Header info
    setHtml('creativeId', ad.creative_id);
    setHtml('creativeAdvertiser', ad.advertiser_id);
    setHtml('creativeStatus', statusBadge(ad.status));
    setHtml('creativeType', typeBadge(ad.ad_type));
    setHtml('creativeDuration', `${data.duration_days || 0} days`);
    setHtml('creativeVersions', data.version_count);
    setHtml('creativeFirstSeen', formatDate(ad.first_seen));
    setHtml('creativeLastSeen', formatDate(ad.last_seen));

    // Content
    setHtml('creativeHeadline', escapeHtml(detail.headline || 'N/A'));
    setHtml('creativeDescription', escapeHtml(detail.description || 'N/A'));
    setHtml('creativeCta', detail.cta ? `<span class="badge bg-primary">${escapeHtml(detail.cta)}</span>` : 'N/A');
    setHtml('creativeLanding', detail.landing_url
        ? `<a href="${escapeHtml(detail.landing_url)}" target="_blank" rel="noopener">${escapeHtml(detail.landing_url)}</a>`
        : 'N/A');

    // Assets
    const assetsEl = document.getElementById('creativeAssets');
    if (assetsEl) {
        assetsEl.innerHTML = data.assets.map(asset => {
            if (asset.type === 'image') {
                const src = asset.local_path || asset.original_url;
                return `<div class="col-md-4 mb-3">
                    <img src="${escapeHtml(src)}" class="img-fluid rounded" alt="Ad asset" loading="lazy">
                    <small class="text-muted d-block mt-1">${escapeHtml(asset.type)}</small>
                </div>`;
            }
            if (asset.type === 'video') {
                return `<div class="col-md-6 mb-3">
                    <video controls class="w-100 rounded">
                        <source src="${escapeHtml(asset.original_url)}" type="video/mp4">
                    </video>
                </div>`;
            }
            return '';
        }).join('') || '<div class="col-12 text-muted">No assets available</div>';
    }

    // Targeting
    const targetEl = document.getElementById('creativeTargeting');
    if (targetEl) {
        targetEl.innerHTML = data.targeting.map(t =>
            `<span class="badge bg-secondary me-1 mb-1">${escapeHtml(t.country)} / ${escapeHtml(t.platform)}</span>`
        ).join('') || '<span class="text-muted">No targeting data</span>';
    }

    // Version history
    const historyEl = document.getElementById('creativeHistory');
    if (historyEl) {
        historyEl.innerHTML = `<table class="table table-sm">
            <thead><tr><th>Date</th><th>Headline</th><th>CTA</th></tr></thead>
            <tbody>
                ${data.details.map(d => `
                    <tr>
                        <td>${formatDate(d.snapshot_date)}</td>
                        <td>${escapeHtml(d.headline || 'N/A')}</td>
                        <td>${escapeHtml(d.cta || 'N/A')}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>`;
    }
}

function setHtml(id, content) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = content;
}

// ============================================================
// Geo Dashboard Page
// ============================================================

async function loadGeo() {
    try {
        const advertiserId = document.getElementById('geoAdvertiser')?.value || null;
        const data = await fetchAPI('geo.php', { advertiser_id: advertiserId });
        if (!data.success) return;

        renderGeoTable(data.distribution);
        renderPlatformChart(data.platforms);
        renderExpansionTimeline(data.expansion);

        if (typeof L !== 'undefined') {
            renderGeoMap(data.distribution);
        }

    } catch (err) {
        console.error('Geo load error:', err);
    }
}

function renderGeoMap(distribution) {
    const mapEl = document.getElementById('geoMap');
    if (!mapEl) return;

    if (window.geoMapInstance) {
        window.geoMapInstance.remove();
    }

    const map = L.map('geoMap').setView([20, 0], 2);
    window.geoMapInstance = map;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Add markers for each country (using approximate coordinates)
    distribution.forEach(d => {
        const coords = getCountryCoords(d.country);
        if (coords) {
            const radius = Math.min(Math.max(d.ad_count * 2, 5), 40);
            L.circleMarker(coords, {
                radius: radius,
                fillColor: '#4361ee',
                color: '#fff',
                weight: 1,
                fillOpacity: 0.6,
            }).addTo(map).bindPopup(`<b>${d.country}</b><br>${d.ad_count} ads`);
        }
    });
}

function renderGeoTable(distribution) {
    const tbody = document.getElementById('geoTable');
    if (!tbody) return;

    const total = distribution.reduce((sum, d) => sum + parseInt(d.ad_count), 0);

    tbody.innerHTML = distribution.map((d, i) => {
        const pct = total > 0 ? ((d.ad_count / total) * 100).toFixed(1) : 0;
        return `<tr>
            <td>${i + 1}</td>
            <td><strong>${escapeHtml(d.country)}</strong></td>
            <td>${formatNumber(d.ad_count)}</td>
            <td>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar" style="width: ${pct}%"></div>
                </div>
            </td>
            <td>${pct}%</td>
        </tr>`;
    }).join('');
}

function renderPlatformChart(platforms) {
    const ctx = document.getElementById('platformChart');
    if (!ctx) return;

    if (window.platformChartInstance) {
        window.platformChartInstance.destroy();
    }

    const colors = ['#4361ee', '#2ec4b6', '#ff9f1c', '#e71d36', '#7209b7'];

    window.platformChartInstance = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: platforms.map(p => p.platform),
            datasets: [{
                data: platforms.map(p => p.ad_count),
                backgroundColor: colors.slice(0, platforms.length),
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function renderExpansionTimeline(expansion) {
    const container = document.getElementById('expansionTimeline');
    if (!container) return;

    container.innerHTML = expansion.map(e => `
        <div class="timeline-item">
            <strong>${escapeHtml(e.country)}</strong>
            <span class="badge bg-info ms-2">${e.ad_count} ads</span>
            <br><small class="text-muted">First targeted: ${formatDate(e.first_targeted)}</small>
        </div>
    `).join('') || '<p class="text-muted">No expansion data available</p>';
}

// Country coordinates lookup (ISO 2-letter codes)
function getCountryCoords(code) {
    const coords = {
        'US': [39.8, -98.5], 'GB': [55.3, -3.4], 'CA': [56.1, -106.3],
        'AU': [-25.2, 133.7], 'DE': [51.1, 10.4], 'FR': [46.2, 2.2],
        'IN': [20.5, 78.9], 'BR': [-14.2, -51.9], 'JP': [36.2, 138.2],
        'MX': [23.6, -102.5], 'IT': [41.8, 12.5], 'ES': [40.4, -3.7],
        'KR': [35.9, 127.7], 'RU': [61.5, 105.3], 'NL': [52.1, 5.2],
        'SE': [60.1, 18.6], 'NO': [60.4, 8.4], 'DK': [56.2, 9.5],
        'FI': [61.9, 25.7], 'PL': [51.9, 19.1], 'BE': [50.5, 4.4],
        'AT': [47.5, 14.5], 'CH': [46.8, 8.2], 'PT': [39.3, -8.2],
        'IE': [53.4, -8.2], 'NZ': [-40.9, 174.8], 'SG': [1.3, 103.8],
        'HK': [22.3, 114.1], 'TW': [23.6, 120.9], 'TH': [15.8, 100.9],
        'PH': [12.8, 121.7], 'MY': [4.2, 101.9], 'ID': [-0.7, 113.9],
        'VN': [14.0, 108.2], 'ZA': [-30.5, 22.9], 'NG': [9.0, 8.6],
        'EG': [26.8, 30.8], 'KE': [-0.02, 37.9], 'SA': [23.8, 45.0],
        'AE': [23.4, 53.8], 'IL': [31.0, 34.8], 'TR': [38.9, 35.2],
        'AR': [-38.4, -63.6], 'CL': [-35.6, -71.5], 'CO': [4.5, -74.2],
        'PE': [-9.1, -75.0], 'PK': [30.3, 69.3], 'BD': [23.6, 90.3],
        'UA': [48.3, 31.1], 'RO': [45.9, 24.9], 'CZ': [49.8, 15.4],
        'HU': [47.1, 19.5], 'GR': [39.0, 21.8],
    };
    return coords[code?.toUpperCase()] || null;
}
