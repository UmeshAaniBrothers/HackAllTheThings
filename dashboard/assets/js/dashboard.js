/**
 * Ad Intelligence Dashboard - Frontend JavaScript
 *
 * Shared utilities and overview page logic.
 * Ads Viewer (ads_viewer.php) has its own inline JS.
 * Manage page (manage.php) has its own inline JS.
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
    var text = await response.text();
    var data = null;
    try { data = JSON.parse(text); } catch(e) {}
    if (!response.ok) {
        var msg = (data && data.error) ? data.error : (text.substring(0, 300) || ('API error: ' + response.status));
        var file = (data && data.file) ? (' [' + data.file + ']') : '';
        throw new Error(msg + file);
    }
    if (!data) {
        throw new Error('Invalid JSON: ' + text.substring(0, 300));
    }
    return data;
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
        var videoAds = document.getElementById('videoAds');
        if (videoAds) videoAds.textContent = formatNumber(data.stats.video_ads);
        var pendingEl = document.getElementById('pendingPayloads');
        if (pendingEl) pendingEl.textContent = formatNumber(data.stats.pending_payloads);

        // Recent activity table
        renderActivityTable(data.recent_activity);

        // Populate advertiser filter
        populateAdvertiserFilter(data.advertisers);

    } catch (err) {
        console.error('Overview load error:', err);
    }
}

function renderActivityTable(activities) {
    const tbody = document.getElementById('activityTable');
    if (!tbody || !activities) return;

    tbody.innerHTML = activities.map(a => `
        <tr>
            <td class="text-truncate" style="max-width:140px"><small class="text-muted">${escapeHtml((a.creative_id || '').substring(0, 16))}...</small></td>
            <td>${escapeHtml(a.headline || 'N/A')}</td>
            <td>${typeBadge(a.ad_type)}</td>
            <td>${statusBadge(a.status)}</td>
            <td>${formatDate(a.last_seen)}</td>
        </tr>
    `).join('');
}

function populateAdvertiserFilter(advertisers) {
    const select = document.getElementById('advertiserFilter');
    if (!select || select.options.length > 1 || !advertisers) return;

    advertisers.forEach(a => {
        const option = document.createElement('option');
        option.value = a.advertiser_id;
        option.textContent = `${a.name || a.advertiser_id} (${a.total_ads || 0} ads)`;
        select.appendChild(option);
    });
}
