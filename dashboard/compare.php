<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Cross-Advertiser Comparison</h4>
</div>

<!-- Comparison Selector -->
<div class="filter-bar mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Advertiser A</label>
            <select class="form-select" id="compareA">
                <option value="">Select Advertiser...</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Advertiser B</label>
            <select class="form-select" id="compareB">
                <option value="">Select Advertiser...</option>
            </select>
        </div>
        <div class="col-md-4">
            <button class="btn btn-primary w-100" onclick="runComparison()">
                <i class="bi bi-arrow-left-right me-1"></i>Compare
            </button>
        </div>
    </div>
</div>

<!-- Comparison Results -->
<div id="comparisonResults" style="display: none;">
    <!-- Side-by-Side Stats -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card kpi-card p-3">
                <h6 class="text-primary" id="compareAName">Advertiser A</h6>
                <div class="row text-center mt-2">
                    <div class="col-4">
                        <div class="kpi-label">Total Ads</div>
                        <div class="fw-bold fs-5" id="compareATotalAds">-</div>
                    </div>
                    <div class="col-4">
                        <div class="kpi-label">Active</div>
                        <div class="fw-bold fs-5" id="compareAActive">-</div>
                    </div>
                    <div class="col-4">
                        <div class="kpi-label">Countries</div>
                        <div class="fw-bold fs-5" id="compareACountries">-</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card kpi-card p-3">
                <h6 class="text-danger" id="compareBName">Advertiser B</h6>
                <div class="row text-center mt-2">
                    <div class="col-4">
                        <div class="kpi-label">Total Ads</div>
                        <div class="fw-bold fs-5" id="compareBTotalAds">-</div>
                    </div>
                    <div class="col-4">
                        <div class="kpi-label">Active</div>
                        <div class="fw-bold fs-5" id="compareBActive">-</div>
                    </div>
                    <div class="col-4">
                        <div class="kpi-label">Countries</div>
                        <div class="fw-bold fs-5" id="compareBCountries">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparison Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="chart-container">
                <h5>Ad Volume Comparison</h5>
                <canvas id="compareVolumeChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h5>Ad Type Distribution</h5>
                <canvas id="compareTypeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Overlap Analysis -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="table-container">
                <h5>Shared Countries</h5>
                <div id="sharedCountries"><p class="text-muted">No data</p></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="table-container">
                <h5>Strategy Differences</h5>
                <div id="strategyDiffs"><p class="text-muted">No data</p></div>
            </div>
        </div>
    </div>

    <!-- Rankings Table -->
    <div class="table-container">
        <h5>Advertiser Rankings</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Advertiser</th>
                        <th>Total Ads</th>
                        <th>Active</th>
                        <th>Countries</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody id="rankingsTable">
                    <tr><td colspan="6" class="text-center text-muted">Run a comparison to see rankings</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>document.addEventListener('DOMContentLoaded', loadCompareAdvertisers);</script>

<?php require_once 'includes/footer.php'; ?>
