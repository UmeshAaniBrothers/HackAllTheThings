/**
 * Ad Intelligence Dashboard - Frontend JavaScript
 *
 * Shared utilities used across all pages.
 * Overview page logic is inline in index.php.
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

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 30000);

    try {
        const response = await fetch(url, { signal: controller.signal });
        clearTimeout(timeoutId);

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
    } catch (err) {
        clearTimeout(timeoutId);
        if (err.name === 'AbortError') {
            throw new Error('Request timed out');
        }
        throw err;
    }
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
    if (isNaN(d.getTime())) return 'N/A';
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatNumber(num) {
    if (num === null || num === undefined) return '0';
    const n = parseInt(num) || 0;
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
    return new Intl.NumberFormat().format(n);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function statusBadge(status) {
    const cls = status === 'active' ? 'badge-active' : 'badge-inactive';
    return `<span class="badge ${cls}">${status || 'unknown'}</span>`;
}

function typeBadge(type) {
    const cls = `badge-${type || 'text'}`;
    return `<span class="badge ${cls}">${type || 'text'}</span>`;
}
