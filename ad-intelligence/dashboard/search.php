<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-search me-2"></i>Advanced Search</h4>
</div>

<!-- Search Form -->
<div class="filter-bar mb-4">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Keyword Search</label>
            <input type="text" class="form-control" id="searchKeyword" placeholder="Search headlines, descriptions, CTAs...">
        </div>
        <div class="col-md-3">
            <label class="form-label">Domain</label>
            <input type="text" class="form-control" id="searchDomain" placeholder="e.g. example.com">
        </div>
        <div class="col-md-3">
            <label class="form-label">CTA Type</label>
            <input type="text" class="form-control" id="searchCta" placeholder="e.g. Sign Up">
        </div>
    </div>
    <div class="row g-3 mt-1">
        <div class="col-md-2">
            <label class="form-label">Country</label>
            <input type="text" class="form-control" id="searchCountry" placeholder="e.g. US">
        </div>
        <div class="col-md-2">
            <label class="form-label">Platform</label>
            <select class="form-select" id="searchPlatform">
                <option value="">All</option>
                <option value="google_search">Google Search</option>
                <option value="youtube">YouTube</option>
                <option value="display">Display</option>
                <option value="shopping">Shopping</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Ad Type</label>
            <select class="form-select" id="searchAdType">
                <option value="">All</option>
                <option value="text">Text</option>
                <option value="image">Image</option>
                <option value="video">Video</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Sentiment</label>
            <select class="form-select" id="searchSentiment">
                <option value="">All</option>
                <option value="aggressive">Aggressive</option>
                <option value="moderate">Moderate</option>
                <option value="soft">Soft</option>
                <option value="neutral">Neutral</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Hook Type</label>
            <select class="form-select" id="searchHook">
                <option value="">All</option>
                <option value="urgency">Urgency</option>
                <option value="scarcity">Scarcity</option>
                <option value="social_proof">Social Proof</option>
                <option value="free_offer">Free Offer</option>
                <option value="discount">Discount</option>
                <option value="guarantee">Guarantee</option>
                <option value="authority">Authority</option>
                <option value="curiosity">Curiosity</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Tag</label>
            <select class="form-select" id="searchTag">
                <option value="">All Tags</option>
            </select>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-12 text-end">
            <button class="btn btn-secondary me-2" onclick="clearSearch()">
                <i class="bi bi-x-circle me-1"></i>Clear
            </button>
            <button class="btn btn-primary" onclick="runSearch()">
                <i class="bi bi-search me-1"></i>Search
            </button>
        </div>
    </div>
</div>

<!-- Search Results -->
<div id="searchResultsInfo" class="mb-3" style="display: none;">
    <span class="text-muted" id="searchResultCount"></span>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Creative</th>
                    <th>Headline</th>
                    <th>CTA</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Countries</th>
                    <th>Platforms</th>
                    <th>Last Seen</th>
                </tr>
            </thead>
            <tbody id="searchResultsTable">
                <tr><td colspan="8" class="text-center text-muted">Enter search criteria and click Search</td></tr>
            </tbody>
        </table>
    </div>
    <div id="searchPagination"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadSearchTags();
    document.getElementById('searchKeyword').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') runSearch();
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
