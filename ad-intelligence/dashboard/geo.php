<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Geo Targeting Dashboard</h4>
    <select id="geoAdvertiser" class="form-select w-auto" onchange="loadGeo()">
        <option value="">All Advertisers</option>
    </select>
</div>

<!-- Map -->
<div class="chart-container">
    <h5>Country Targeting Map</h5>
    <div id="geoMap"></div>
</div>

<!-- Distribution & Platform -->
<div class="row">
    <div class="col-md-7">
        <div class="table-container">
            <h5>Country Distribution</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Country</th>
                            <th>Ads</th>
                            <th>Share</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody id="geoTable">
                        <tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="chart-container">
            <h5>Platform Distribution</h5>
            <canvas id="platformChart"></canvas>
        </div>
    </div>
</div>

<!-- Expansion Timeline -->
<div class="chart-container">
    <h5>Geo Expansion Timeline</h5>
    <div id="expansionTimeline">
        <div class="loading-overlay">
            <div class="spinner-border text-primary" role="status"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    // Populate advertiser filter
    try {
        const data = await fetchAPI('overview.php');
        if (data.success && data.advertisers) {
            const select = document.getElementById('geoAdvertiser');
            data.advertisers.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.advertiser_id;
                opt.textContent = a.advertiser_id + ' (' + a.total_ads + ' ads)';
                select.appendChild(opt);
            });
        }
    } catch(e) {}
    loadGeo();
});
</script>

<?php require_once 'includes/footer.php'; ?>
