<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-binoculars me-2"></i>Competitor Watchlists</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWatchlistModal">
        <i class="bi bi-plus-circle me-1"></i>New Watchlist
    </button>
</div>

<!-- Watchlist Tabs -->
<div class="table-container mb-4">
    <ul class="nav nav-tabs mb-3" id="watchlistTabs">
        <li class="nav-item">
            <a class="nav-link active" href="#" onclick="loadWatchlists(); return false;">All Watchlists</a>
        </li>
    </ul>

    <div id="watchlistContent">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Watchlist</th>
                        <th>Advertisers</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="watchlistTable">
                    <tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Daily Summary -->
<div class="row">
    <div class="col-md-6">
        <div class="table-container">
            <h5>Daily Summary</h5>
            <div id="dailySummary">
                <p class="text-muted">Select a watchlist to view daily summary</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="table-container">
            <h5>Recent Change Log</h5>
            <div id="changeLog">
                <p class="text-muted">Select a watchlist to view changes</p>
            </div>
        </div>
    </div>
</div>

<!-- Create Watchlist Modal -->
<div class="modal fade" id="createWatchlistModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Watchlist</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Watchlist Name</label>
                    <input type="text" class="form-control" id="watchlistName" placeholder="e.g. Top Competitors">
                </div>
                <div class="mb-3">
                    <label class="form-label">Advertiser IDs (comma-separated)</label>
                    <textarea class="form-control" id="watchlistAdvertisers" rows="3"
                              placeholder="AR00000000001, AR00000000002"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createWatchlist()">Create</button>
            </div>
        </div>
    </div>
</div>

<script>document.addEventListener('DOMContentLoaded', loadWatchlists);</script>

<?php require_once 'includes/footer.php'; ?>
