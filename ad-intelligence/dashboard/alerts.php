<?php require_once 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bell me-2"></i>Alerts & Notifications</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAlertModal">
        <i class="bi bi-plus-circle me-1"></i>Create Alert Rule
    </button>
</div>

<!-- Alert Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">Total Alerts Today</div>
                    <div class="kpi-value text-danger" id="alertsToday">-</div>
                </div>
                <div class="kpi-icon text-danger"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">Active Rules</div>
                    <div class="kpi-value text-primary" id="activeRules">-</div>
                </div>
                <div class="kpi-icon text-primary"><i class="bi bi-gear"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">New Ads Detected</div>
                    <div class="kpi-value text-success" id="newAdsDetected">-</div>
                </div>
                <div class="kpi-icon text-success"><i class="bi bi-plus-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-card p-3">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="kpi-label">Channels Configured</div>
                    <div class="kpi-value text-info" id="channelsCount">-</div>
                </div>
                <div class="kpi-icon text-info"><i class="bi bi-broadcast"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Rules -->
<div class="table-container mb-4">
    <h5>Alert Rules</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Rule</th>
                    <th>Type</th>
                    <th>Advertiser</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>Last Triggered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="alertRulesTable">
                <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Alert Log -->
<div class="table-container">
    <h5>Recent Alert Log</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Rule</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody id="alertLogTable">
                <tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Alert Modal -->
<div class="modal fade" id="createAlertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Alert Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Rule Name</label>
                    <input type="text" class="form-control" id="alertRuleName" placeholder="e.g. New competitor ads">
                </div>
                <div class="mb-3">
                    <label class="form-label">Alert Type</label>
                    <select class="form-select" id="alertRuleType">
                        <option value="new_ad">New Ad Detected</option>
                        <option value="ad_stopped">Ad Stopped</option>
                        <option value="new_country">New Country Targeting</option>
                        <option value="landing_change">Landing Page Changed</option>
                        <option value="burst">Activity Burst</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Advertiser ID (optional)</label>
                    <input type="text" class="form-control" id="alertAdvertiserId" placeholder="Leave blank for all">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notification Channel</label>
                    <select class="form-select" id="alertChannel">
                        <option value="email">Email</option>
                        <option value="telegram">Telegram</option>
                        <option value="slack">Slack</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Threshold (for burst alerts)</label>
                    <input type="number" class="form-control" id="alertThreshold" value="10">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createAlertRule()">Create Rule</button>
            </div>
        </div>
    </div>
</div>

<script>document.addEventListener('DOMContentLoaded', loadAlerts);</script>

<?php require_once 'includes/footer.php'; ?>
