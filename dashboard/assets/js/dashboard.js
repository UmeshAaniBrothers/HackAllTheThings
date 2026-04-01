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
// Global Filters — shared across all pages
// ============================================================
const GlobalFilters = {
    _advertiserId: '',
    _timePeriod: 'all',
    _advertiserName: '',
    _loaded: false,

    get advertiserId() { return this._advertiserId; },
    get timePeriod() { return this._timePeriod; },
    get advertiserName() { return this._advertiserName; },

    setAdvertiser(id, name) {
        this._advertiserId = id || '';
        this._advertiserName = name || '';
        sessionStorage.setItem('gf_advertiser', this._advertiserId);
        sessionStorage.setItem('gf_advertiser_name', this._advertiserName);
        this._dispatch();
    },

    setTimePeriod(period) {
        if (!['1d','7d','30d','90d','all'].includes(period)) period = 'all';
        this._timePeriod = period;
        sessionStorage.setItem('gf_time_period', period);
        this._dispatch();
    },

    getApiParams() {
        const p = {};
        if (this._advertiserId) p.advertiser_id = this._advertiserId;
        if (this._timePeriod !== 'all') p.time_period = this._timePeriod;
        return p;
    },

    restore() {
        this._advertiserId = sessionStorage.getItem('gf_advertiser') || '';
        this._advertiserName = sessionStorage.getItem('gf_advertiser_name') || '';
        this._timePeriod = sessionStorage.getItem('gf_time_period') || 'all';
    },

    _dispatch() {
        window.dispatchEvent(new CustomEvent('globalfilter:change', { detail: this.getApiParams() }));
    },

    init() {
        this.restore();

        // Time period buttons
        document.querySelectorAll('#globalTimePeriod .gtp-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#globalTimePeriod .gtp-btn').forEach(b => {
                    b.classList.remove('btn-light','active');
                    b.classList.add('btn-outline-light');
                });
                btn.classList.remove('btn-outline-light');
                btn.classList.add('btn-light','active');
                GlobalFilters.setTimePeriod(btn.dataset.period);
            });
        });

        // Sync time period button state
        const activeBtn = document.querySelector(`#globalTimePeriod .gtp-btn[data-period="${this._timePeriod}"]`);
        if (activeBtn) {
            document.querySelectorAll('#globalTimePeriod .gtp-btn').forEach(b => {
                b.classList.remove('btn-light','active');
                b.classList.add('btn-outline-light');
            });
            activeBtn.classList.remove('btn-outline-light');
            activeBtn.classList.add('btn-light','active');
        }

        // Advertiser dropdown
        const advSel = document.getElementById('globalAdvertiser');
        if (advSel) {
            advSel.addEventListener('change', () => {
                const opt = advSel.options[advSel.selectedIndex];
                GlobalFilters.setAdvertiser(advSel.value, opt ? opt.textContent : '');
            });
        }

        // Load advertiser list
        this._loadAdvertisers();
        this._loaded = true;
    },

    async _loadAdvertisers() {
        try {
            const data = await fetchAPI('advertisers_list.php');
            if (!data.success || !data.advertisers) return;
            const sel = document.getElementById('globalAdvertiser');
            if (!sel || sel.options.length > 1) return;
            data.advertisers.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.advertiser_id;
                opt.textContent = (a.name || a.advertiser_id) + ' (' + (a.total_ads || 0) + ')';
                sel.appendChild(opt);
            });
            // Restore selection
            if (this._advertiserId) sel.value = this._advertiserId;
        } catch(e) { console.warn('Failed to load advertisers:', e); }
    }
};

document.addEventListener('DOMContentLoaded', () => GlobalFilters.init());

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
    const timeoutId = setTimeout(() => controller.abort(), 60000);

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

// Country code → name + flag emoji mapping
const COUNTRY_MAP = {
    'AF':'Afghanistan','AL':'Albania','DZ':'Algeria','AD':'Andorra','AO':'Angola',
    'AR':'Argentina','AM':'Armenia','AU':'Australia','AT':'Austria','AZ':'Azerbaijan',
    'BD':'Bangladesh','BE':'Belgium','BR':'Brazil','BG':'Bulgaria','KH':'Cambodia',
    'CM':'Cameroon','CA':'Canada','CL':'Chile','CN':'China','CO':'Colombia',
    'CR':'Costa Rica','HR':'Croatia','CY':'Cyprus','CZ':'Czechia','DK':'Denmark',
    'EC':'Ecuador','EG':'Egypt','EE':'Estonia','ET':'Ethiopia','FI':'Finland',
    'FR':'France','GE':'Georgia','DE':'Germany','GH':'Ghana','GR':'Greece',
    'GT':'Guatemala','HK':'Hong Kong','HU':'Hungary','IS':'Iceland','IN':'India',
    'ID':'Indonesia','IR':'Iran','IQ':'Iraq','IE':'Ireland','IL':'Israel',
    'IT':'Italy','JP':'Japan','JO':'Jordan','KZ':'Kazakhstan','KE':'Kenya',
    'KR':'South Korea','KW':'Kuwait','LB':'Lebanon','LY':'Libya','LT':'Lithuania',
    'LU':'Luxembourg','MY':'Malaysia','MX':'Mexico','MA':'Morocco','MZ':'Mozambique',
    'NP':'Nepal','NL':'Netherlands','NZ':'New Zealand','NG':'Nigeria','NO':'Norway',
    'PK':'Pakistan','PE':'Peru','PH':'Philippines','PL':'Poland','PT':'Portugal',
    'QA':'Qatar','RO':'Romania','RU':'Russia','SA':'Saudi Arabia','RS':'Serbia',
    'SG':'Singapore','SK':'Slovakia','ZA':'South Africa','ES':'Spain','LK':'Sri Lanka',
    'SE':'Sweden','CH':'Switzerland','TW':'Taiwan','TH':'Thailand','TR':'Turkey',
    'UA':'Ukraine','AE':'UAE','GB':'United Kingdom','US':'United States',
    'UY':'Uruguay','UZ':'Uzbekistan','VN':'Vietnam'
};
const COUNTRY_FLAGS = {};
Object.keys(COUNTRY_MAP).forEach(code => {
    COUNTRY_FLAGS[code] = String.fromCodePoint(...[...code.toUpperCase()].map(c => 0x1F1E6 + c.charCodeAt(0) - 65));
});

function countryBadge(code, clickable = true) {
    const name = COUNTRY_MAP[code] || code;
    const flag = COUNTRY_FLAGS[code] || '';
    const cls = clickable ? 'viewer-clickable' : '';
    return `<span class="badge bg-secondary bg-opacity-75 ${cls}" ${clickable ? `data-filter="country" data-value="${escapeHtml(code)}" style="cursor:pointer"` : ''} title="${escapeHtml(name)}">${flag} ${escapeHtml(code)}</span>`;
}

function countryName(code) {
    return COUNTRY_MAP[code] || code;
}

function countryFlag(code) {
    return COUNTRY_FLAGS[code] || '';
}

function statusBadge(status) {
    const cls = status === 'active' ? 'badge-active' : 'badge-inactive';
    return `<span class="badge ${cls}">${status || 'unknown'}</span>`;
}

function typeBadge(type) {
    const cls = `badge-${type || 'text'}`;
    return `<span class="badge ${cls}">${type || 'text'}</span>`;
}
