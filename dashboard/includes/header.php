<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Intelligence Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php if ($currentPage === 'geo'): ?>
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <?php endif; ?>
    <link href="assets/css/dashboard.css" rel="stylesheet">
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
                        <a class="nav-link <?= $currentPage === 'explorer' ? 'active' : '' ?>" href="explorer.php">
                            <i class="bi bi-layout-text-window me-1"></i>Explorer
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'search' ? 'active' : '' ?>" href="search.php">
                            <i class="bi bi-search me-1"></i>Search
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'timeline' ? 'active' : '' ?>" href="timeline.php">
                            <i class="bi bi-clock-history me-1"></i>Timeline
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'geo' ? 'active' : '' ?>" href="geo.php">
                            <i class="bi bi-globe me-1"></i>Geo
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'intelligence' ? 'active' : '' ?>" href="intelligence.php">
                            <i class="bi bi-cpu me-1"></i>AI Intel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'landing' ? 'active' : '' ?>" href="landing.php">
                            <i class="bi bi-browser-chrome me-1"></i>Landing
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'compare' ? 'active' : '' ?>" href="compare.php">
                            <i class="bi bi-arrow-left-right me-1"></i>Compare
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'watchlists' ? 'active' : '' ?>" href="watchlists.php">
                            <i class="bi bi-binoculars me-1"></i>Watchlists
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'alerts' ? 'active' : '' ?>" href="alerts.php">
                            <i class="bi bi-bell me-1"></i>Alerts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'manage' ? 'active' : '' ?>" href="manage.php">
                            <i class="bi bi-gear me-1"></i>Manage
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container-fluid mt-3">
