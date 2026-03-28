<?php require_once 'includes/header.php'; ?>

<h4 class="mb-4">Ad Explorer</h4>

<!-- Filter Bar -->
<div class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label small">Advertiser</label>
            <select id="filterAdvertiser" class="form-select form-select-sm" onchange="loadExplorer()">
                <option value="">All</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Country</label>
            <select id="filterCountry" class="form-select form-select-sm" onchange="loadExplorer()">
                <option value="">All</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Platform</label>
            <select id="filterPlatform" class="form-select form-select-sm" onchange="loadExplorer()">
                <option value="">All</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label small">Type</label>
            <select id="filterType" class="form-select form-select-sm" onchange="loadExplorer()">
                <option value="">All</option>
                <option value="text">Text</option>
                <option value="image">Image</option>
                <option value="video">Video</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label small">Status</label>
            <select id="filterStatus" class="form-select form-select-sm" onchange="loadExplorer()">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label small">From</label>
            <input type="date" id="filterDateFrom" class="form-control form-control-sm" onchange="loadExplorer()">
        </div>
        <div class="col-md-1">
            <label class="form-label small">To</label>
            <input type="date" id="filterDateTo" class="form-control form-control-sm" onchange="loadExplorer()">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Search</label>
            <div class="input-group input-group-sm">
                <input type="text" id="filterSearch" class="form-control" placeholder="Headline...">
                <button class="btn btn-primary" onclick="loadExplorer()"><i class="bi bi-search"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Ad Grid -->
<div class="row" id="adGrid">
    <div class="col-12">
        <div class="loading-overlay">
            <div class="spinner-border text-primary" role="status"></div>
        </div>
    </div>
</div>

<!-- Pagination -->
<div id="pagination" class="mt-3"></div>

<script>
document.addEventListener('DOMContentLoaded', loadExplorer);
document.getElementById('filterSearch')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') loadExplorer();
});
</script>

<?php require_once 'includes/footer.php'; ?>
