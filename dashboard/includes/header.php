<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Intelligence Dashboard<?php
        $pageTitles = [
            'app_profile' => ' - App Profile',
            'advertiser_profile' => ' - Advertiser Profile',
            'youtube_profile' => ' - Video Profile',
            'ads_viewer' => ' - Ads Viewer',
            'search' => ' - Search',
            'app_groups' => ' - App Groups',
            'video_groups' => ' - Video Groups',
            'manage' => ' - Manage',
        ];
        echo $pageTitles[$currentPage] ?? '';
    ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/dashboard.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="assets/js/dashboard.js?v=20260401"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-bar-chart-fill me-2"></i>Ad Intelligence
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>" href="index.php">
                            <i class="bi bi-speedometer2 me-1"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'ads_viewer' ? 'active' : '' ?>" href="ads_viewer.php">
                            <i class="bi bi-eye me-1"></i>Ads Viewer
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'search' ? 'active' : '' ?>" href="search.php">
                            <i class="bi bi-search me-1"></i>Search
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'app_groups' ? 'active' : '' ?>" href="app_groups.php">
                            <i class="bi bi-collection me-1"></i>App Groups
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'video_groups' ? 'active' : '' ?>" href="video_groups.php">
                            <i class="bi bi-camera-video me-1"></i>Video Groups
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'manage' ? 'active' : '' ?>" href="manage.php">
                            <i class="bi bi-gear me-1"></i>Manage
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    <li class="nav-item">
                        <select id="globalAdvertiser" class="form-select form-select-sm bg-dark text-light border-secondary" style="width:200px;font-size:.8rem" title="Filter by advertiser">
                            <option value="">All Advertisers</option>
                        </select>
                    </li>
                    <li class="nav-item">
                        <div class="btn-group btn-group-sm" role="group" id="globalTimePeriod">
                            <button type="button" class="btn btn-outline-light btn-sm gtp-btn" data-period="1d">24h</button>
                            <button type="button" class="btn btn-outline-light btn-sm gtp-btn" data-period="7d">7D</button>
                            <button type="button" class="btn btn-outline-light btn-sm gtp-btn" data-period="30d">30D</button>
                            <button type="button" class="btn btn-outline-light btn-sm gtp-btn" data-period="90d">90D</button>
                            <button type="button" class="btn btn-light btn-sm gtp-btn active" data-period="all">All</button>
                        </div>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link text-muted small" id="navClock"></span>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container-fluid mt-3">
