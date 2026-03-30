<?php require_once 'includes/header.php'; ?>

<?php
$creativeId = isset($_GET['id']) ? htmlspecialchars(trim($_GET['id']), ENT_QUOTES, 'UTF-8') : '';
if (empty($creativeId)) {
    echo '<div class="alert alert-warning">No creative ID specified. <a href="explorer.php">Browse ads</a></div>';
    require_once 'includes/footer.php';
    exit;
}
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="explorer.php">Explorer</a></li>
        <li class="breadcrumb-item active"><?= $creativeId ?></li>
    </ol>
</nav>

<!-- Creative Header -->
<div class="chart-container mb-4">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h5 class="mb-1">Creative: <span id="creativeId"><?= $creativeId ?></span></h5>
            <p class="text-muted mb-0">Advertiser: <span id="creativeAdvertiser">-</span></p>
        </div>
        <div class="col-md-6 text-md-end">
            <span id="creativeStatus"></span>
            <span id="creativeType" class="ms-2"></span>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card kpi-card p-3 text-center">
            <div class="kpi-label">Campaign Duration</div>
            <div class="kpi-value text-primary" id="creativeDuration">-</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3 text-center">
            <div class="kpi-label">Content Versions</div>
            <div class="kpi-value text-info" id="creativeVersions">-</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3 text-center">
            <div class="kpi-label">First Seen</div>
            <div class="kpi-value fs-5 text-success" id="creativeFirstSeen">-</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3 text-center">
            <div class="kpi-label">Last Seen</div>
            <div class="kpi-value fs-5 text-warning" id="creativeLastSeen">-</div>
        </div>
    </div>
</div>

<!-- Content & Assets -->
<div class="row">
    <div class="col-md-7">
        <div class="chart-container">
            <h5>Ad Content</h5>
            <table class="table">
                <tr>
                    <th style="width: 120px;">Headline</th>
                    <td id="creativeHeadline">-</td>
                </tr>
                <tr>
                    <th>Description</th>
                    <td id="creativeDescription">-</td>
                </tr>
                <tr>
                    <th>CTA</th>
                    <td id="creativeCta">-</td>
                </tr>
                <tr>
                    <th>Landing URL</th>
                    <td id="creativeLanding">-</td>
                </tr>
            </table>
        </div>

        <!-- Targeting -->
        <div class="chart-container">
            <h5>Targeting</h5>
            <div id="creativeTargeting">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="chart-container">
            <h5>Media Assets</h5>
            <div class="row" id="creativeAssets">
                <div class="col-12 text-center">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Version History -->
<div class="chart-container mt-4">
    <h5>Content Version History</h5>
    <div id="creativeHistory">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadCreative('<?= addslashes($creativeId) ?>');
});
</script>

<?php require_once 'includes/footer.php'; ?>
