<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Dashboard Overview</h4>
    <select id="advertiserFilter" class="form-select w-auto" onchange="loadOverview()">
        <option value="">All Advertisers</option>
    </select>
</div>

<!-- KPI Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">Total Ads</div>
                    <div class="kpi-value text-primary" id="totalAds">-</div>
                </div>
                <div class="kpi-icon text-primary"><i class="bi bi-collection"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">Active Ads</div>
                    <div class="kpi-value text-success" id="activeAds">-</div>
                </div>
                <div class="kpi-icon text-success"><i class="bi bi-check-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">New Today</div>
                    <div class="kpi-value text-warning" id="newToday">-</div>
                </div>
                <div class="kpi-icon text-warning"><i class="bi bi-plus-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">Avg Duration</div>
                    <div class="kpi-value text-info" id="avgDuration">-</div>
                </div>
                <div class="kpi-icon text-info"><i class="bi bi-clock"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="chart-container">
            <h5>Ad Creation Velocity (30 Days)</h5>
            <canvas id="velocityChart" height="100"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-container">
            <h5>Ad Type Distribution</h5>
            <canvas id="typeChart"></canvas>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="table-container">
    <h5>Recent Activity</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Creative ID</th>
                    <th>Headline</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody id="activityTable">
                <tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>document.addEventListener('DOMContentLoaded', loadOverview);</script>

<?php require_once 'includes/footer.php'; ?>
