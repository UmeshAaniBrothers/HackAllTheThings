<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cpu me-2"></i>AI Intelligence Center</h4>
    <select id="intelAdvertiser" class="form-select w-auto" onchange="loadIntelligence()">
        <option value="">All Advertisers</option>
    </select>
</div>

<!-- Intelligence KPIs -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">Ads Analyzed</div>
                    <div class="kpi-value text-primary" id="intelAnalyzed">-</div>
                </div>
                <div class="kpi-icon text-primary"><i class="bi bi-cpu"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">A/B Tests Found</div>
                    <div class="kpi-value text-warning" id="intelAbTests">-</div>
                </div>
                <div class="kpi-icon text-warning"><i class="bi bi-diagram-2"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">Creative Clusters</div>
                    <div class="kpi-value text-success" id="intelClusters">-</div>
                </div>
                <div class="kpi-icon text-success"><i class="bi bi-collection"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">Avg Performance</div>
                    <div class="kpi-value text-info" id="intelAvgPerf">-</div>
                </div>
                <div class="kpi-icon text-info"><i class="bi bi-graph-up"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Sentiment & Hooks -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="chart-container">
            <h5>Sentiment Distribution</h5>
            <canvas id="sentimentChart"></canvas>
        </div>
    </div>
    <div class="col-md-6">
        <div class="chart-container">
            <h5>Hook Types Detected</h5>
            <canvas id="hooksChart"></canvas>
        </div>
    </div>
</div>

<!-- Trends & Patterns -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="table-container">
            <h5>Detected Patterns</h5>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Advertiser</th>
                            <th>Pattern</th>
                            <th>Confidence</th>
                            <th>Detected</th>
                        </tr>
                    </thead>
                    <tbody id="patternsTable">
                        <tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="table-container">
            <h5>Top Keywords</h5>
            <div id="keywordsCloud">
                <p class="text-muted">Loading...</p>
            </div>
        </div>
    </div>
</div>

<!-- A/B Test Candidates & Performance -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="table-container">
            <h5>A/B Test Candidates</h5>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Creative A</th>
                            <th>Creative B</th>
                            <th>Similarity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="abTestsTable">
                        <tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="table-container">
            <h5>Performance Scores</h5>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Creative</th>
                            <th>Headline</th>
                            <th>Score</th>
                            <th>Longevity</th>
                        </tr>
                    </thead>
                    <tbody id="performanceTable">
                        <tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>document.addEventListener('DOMContentLoaded', loadIntelligence);</script>

<?php require_once 'includes/footer.php'; ?>
