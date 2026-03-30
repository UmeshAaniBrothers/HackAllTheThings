<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-browser-chrome me-2"></i>Landing Page Intelligence</h4>
    <select id="landingAdvertiser" class="form-select w-auto" onchange="loadLandingPages()">
        <option value="">All Advertisers</option>
    </select>
</div>

<!-- Funnel Distribution -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="chart-container">
            <h5>Funnel Type Distribution</h5>
            <canvas id="funnelChart"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-container">
            <h5>Technology Stack</h5>
            <canvas id="techChart"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="table-container" style="min-height: 280px;">
            <h5>Key Stats</h5>
            <div class="mt-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total Pages</span>
                    <strong id="lpTotalPages">-</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Recent Changes</span>
                    <strong id="lpRecentChanges">-</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Unique Domains</span>
                    <strong id="lpUniqueDomains">-</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Has Forms</span>
                    <strong id="lpHasForms">-</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Landing Pages Table -->
<div class="table-container mb-4">
    <h5>Landing Pages</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>URL</th>
                    <th>Domain</th>
                    <th>Funnel Type</th>
                    <th>Technologies</th>
                    <th>Last Scraped</th>
                    <th>Changes</th>
                </tr>
            </thead>
            <tbody id="landingPagesTable">
                <tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Changes -->
<div class="table-container">
    <h5>Recent Landing Page Changes</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Change Type</th>
                    <th>Old Value</th>
                    <th>New Value</th>
                    <th>Detected</th>
                </tr>
            </thead>
            <tbody id="lpChangesTable">
                <tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>document.addEventListener('DOMContentLoaded', loadLandingPages);</script>

<?php require_once 'includes/footer.php'; ?>
