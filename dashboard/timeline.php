<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Campaign Timeline</h4>
    <div id="timelineStats"></div>
</div>

<!-- Filters -->
<div class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Advertiser</label>
            <select id="timelineAdvertiser" class="form-select form-select-sm" onchange="loadTimeline()">
                <option value="">All Advertisers</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">From</label>
            <input type="date" id="timelineFrom" class="form-control form-control-sm" onchange="loadTimeline()">
        </div>
        <div class="col-md-2">
            <label class="form-label small">To</label>
            <input type="date" id="timelineTo" class="form-control form-control-sm" onchange="loadTimeline()">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary btn-sm" onclick="loadTimeline()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>
</div>

<!-- Timeline Chart -->
<div class="chart-container">
    <h5>Ad Creation Over Time</h5>
    <canvas id="timelineChart" height="80"></canvas>
</div>

<!-- Timeline List -->
<div class="row">
    <div class="col-12">
        <div class="chart-container">
            <h5>Recent Ad Lifecycle</h5>
            <div id="timelineList">
                <div class="loading-overlay">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    // Populate advertiser filter
    try {
        const data = await fetchAPI('overview.php');
        if (data.success && data.advertisers) {
            const select = document.getElementById('timelineAdvertiser');
            data.advertisers.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.advertiser_id;
                opt.textContent = a.advertiser_id + ' (' + a.total_ads + ' ads)';
                select.appendChild(opt);
            });
        }
    } catch(e) {}
    loadTimeline();
});
</script>

<?php require_once 'includes/footer.php'; ?>
