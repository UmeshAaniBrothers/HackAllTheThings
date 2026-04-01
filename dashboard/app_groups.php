<?php include 'includes/header.php'; ?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-collection me-2"></i>App Groups</h4>
            <p class="text-muted mb-0">Create custom categories to group similar apps. Auto-assigns based on keywords.</p>
        </div>
        <div>
            <button class="btn btn-outline-secondary me-2" onclick="autoAssignAll()">
                <i class="bi bi-magic me-1"></i>Auto-Assign All
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                <i class="bi bi-plus-lg me-1"></i>Create Group
            </button>
        </div>
    </div>

    <!-- Groups Grid -->
    <div class="row g-4" id="groupsGrid">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
        </div>
    </div>

    <!-- Unassigned Apps Section -->
    <div class="mt-5" id="unassignedSection" style="display:none">
        <h5 class="mb-3"><i class="bi bi-question-circle me-2"></i>Unassigned Apps <span class="badge bg-secondary" id="unassignedCount">0</span></h5>
        <div class="table-responsive">
            <table class="table table-sm table-hover" id="unassignedTable">
                <thead><tr><th>App</th><th>Type</th><th>Platform</th><th>Advertiser</th><th>Action</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Create App Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editGroupId" value="">
                <div class="mb-3">
                    <label class="form-label fw-bold">Group Name</label>
                    <input type="text" class="form-control" id="groupName" placeholder="e.g. GPS & Location Tracking">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Description</label>
                    <input type="text" class="form-control" id="groupDesc" placeholder="Apps related to GPS, geolocation, phone tracking">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Keywords <small class="text-muted">(comma separated — matched against app name, category, description)</small></label>
                    <textarea class="form-control" id="groupKeywords" rows="3" placeholder="gps, geolocation, phone locater, mobile number tracker, location tracker, find my phone"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Color</label>
                        <input type="color" class="form-control form-control-color" id="groupColor" value="#0d6efd">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Icon</label>
                        <select class="form-select" id="groupIcon">
                            <option value="bi-collection">Collection</option>
                            <option value="bi-geo-alt">Location</option>
                            <option value="bi-phone">Phone</option>
                            <option value="bi-camera">Camera</option>
                            <option value="bi-controller">Gaming</option>
                            <option value="bi-cart">Shopping</option>
                            <option value="bi-chat">Chat</option>
                            <option value="bi-music-note">Music</option>
                            <option value="bi-film">Video</option>
                            <option value="bi-heart">Health</option>
                            <option value="bi-bank">Finance</option>
                            <option value="bi-book">Education</option>
                            <option value="bi-tools">Utility</option>
                            <option value="bi-shield">Security</option>
                            <option value="bi-people">Social</option>
                            <option value="bi-briefcase">Business</option>
                            <option value="bi-palette">Design</option>
                            <option value="bi-cloud">Cloud</option>
                            <option value="bi-truck">Delivery</option>
                            <option value="bi-cup-hot">Food</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveGroup()">
                    <i class="bi bi-check-lg me-1"></i><span id="saveBtn">Create Group</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Group Detail Modal -->
<div class="modal fade" id="groupDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailTitle">Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
const API = 'api/app_groups.php';

document.addEventListener('DOMContentLoaded', loadGroups);

async function apiCall(action, data = null) {
    const url = data ? API + '?action=' + action : API + '?action=' + action;
    const opts = data ? { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) } : {};
    const resp = await fetch(url, opts);
    return resp.json();
}

async function loadGroups() {
    const data = await apiCall('list');
    const grid = document.getElementById('groupsGrid');

    if (!data.groups || data.groups.length === 0) {
        grid.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-collection display-1 text-muted"></i>
                <h5 class="mt-3 text-muted">No app groups yet</h5>
                <p class="text-muted">Create your first group to categorize apps by keywords.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                    <i class="bi bi-plus-lg me-1"></i>Create Group
                </button>
            </div>`;
        return;
    }

    grid.innerHTML = data.groups.map(g => `
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card h-100 shadow-sm" style="border-top: 4px solid ${g.color || '#6c757d'}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="card-title mb-1">
                                <i class="${g.icon || 'bi-collection'} me-2" style="color:${g.color}"></i>${escHtml(g.name)}
                            </h5>
                            <p class="text-muted small mb-2">${escHtml(g.description || '')}</p>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="editGroup(${g.id}); return false"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteGroup(${g.id}, ${JSON.stringify(g.name)}); return false"><i class="bi bi-trash me-2"></i>Delete</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mt-3">
                        <div class="text-center">
                            <div class="h4 mb-0" style="color:${g.color}">${g.member_count}</div>
                            <small class="text-muted">Apps</small>
                        </div>
                        <div class="text-center">
                            <div class="h4 mb-0">${g.keyword_count}</div>
                            <small class="text-muted">Keywords</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <button class="btn btn-sm btn-outline-primary w-100" onclick="viewGroup(${g.id})">
                        <i class="bi bi-eye me-1"></i>View Apps
                    </button>
                </div>
            </div>
        </div>
    `).join('');

    loadUnassigned();
}

async function saveGroup() {
    const id = document.getElementById('editGroupId').value;
    const payload = {
        name: document.getElementById('groupName').value,
        description: document.getElementById('groupDesc').value,
        keywords: document.getElementById('groupKeywords').value.split(',').map(k => k.trim()).filter(k => k),
        color: document.getElementById('groupColor').value,
        icon: document.getElementById('groupIcon').value,
    };

    if (!payload.name) return alert('Group name is required');
    if (!payload.keywords.length) return alert('At least one keyword is required');

    if (id) payload.id = parseInt(id);
    const data = await apiCall(id ? 'update' : 'create', payload);

    if (data.success) {
        bootstrap.Modal.getInstance(document.getElementById('createGroupModal')).hide();
        loadGroups();
        if (data.auto_assigned > 0) {
            alert(`Group saved! ${data.auto_assigned} apps auto-assigned.`);
        }
    } else {
        alert('Error: ' + (data.error || 'Unknown'));
    }
}

async function editGroup(id) {
    const data = await apiCall('get&id=' + id);
    if (!data.success) return;
    const g = data.group;

    document.getElementById('editGroupId').value = g.id;
    document.getElementById('groupName').value = g.name;
    document.getElementById('groupDesc').value = g.description || '';
    document.getElementById('groupKeywords').value = (g.keywords || []).join(', ');
    document.getElementById('groupColor').value = g.color || '#6c757d';
    document.getElementById('groupIcon').value = g.icon || 'bi-collection';
    document.getElementById('modalTitle').textContent = 'Edit Group';
    document.getElementById('saveBtn').textContent = 'Save Changes';

    new bootstrap.Modal(document.getElementById('createGroupModal')).show();
}

// Reset modal on close
document.getElementById('createGroupModal').addEventListener('hidden.bs.modal', () => {
    document.getElementById('editGroupId').value = '';
    document.getElementById('groupName').value = '';
    document.getElementById('groupDesc').value = '';
    document.getElementById('groupKeywords').value = '';
    document.getElementById('groupColor').value = '#0d6efd';
    document.getElementById('groupIcon').value = 'bi-collection';
    document.getElementById('modalTitle').textContent = 'Create App Group';
    document.getElementById('saveBtn').textContent = 'Create Group';
});

async function viewGroup(id) {
    const modal = new bootstrap.Modal(document.getElementById('groupDetailModal'));
    document.getElementById('detailBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    modal.show();

    const data = await apiCall('get&id=' + id);
    if (!data.success) { document.getElementById('detailBody').innerHTML = '<p class="text-danger">Error loading group</p>'; return; }

    const g = data.group;
    document.getElementById('detailTitle').innerHTML = `<i class="${g.icon} me-2" style="color:${g.color}"></i>${escHtml(g.name)} <span class="badge bg-primary">${g.members.length} apps</span>`;

    let html = `
        <div class="mb-3">
            <strong>Keywords:</strong>
            ${(g.keywords || []).map(k => `<span class="badge bg-light text-dark border me-1">${escHtml(k)}</span>`).join('')}
        </div>`;

    if (g.members.length === 0) {
        html += '<p class="text-muted">No apps matched yet. Try adding more keywords.</p>';
    } else {
        html += `<div class="table-responsive"><table class="table table-sm table-hover">
            <thead><tr><th></th><th>App</th><th>Category</th><th>Advertiser</th><th>Platform</th><th>Rating</th><th>Matched By</th><th></th></tr></thead><tbody>`;
        for (const m of g.members) {
            const icon = m.icon_url ? `<img src="${escHtml(m.icon_url)}" width="28" height="28" class="rounded" onerror="this.style.display='none'">` : '<i class="bi bi-app fs-4"></i>';
            html += `<tr>
                <td>${icon}</td>
                <td><a href="app_profile.php?id=${m.product_id}">${escHtml(m.app_name || m.product_name)}</a></td>
                <td><small class="text-muted">${escHtml(m.category || '-')}</small></td>
                <td><a href="advertiser_profile.php?id=${escHtml(m.advertiser_id)}">${escHtml(m.advertiser_name || m.advertiser_id)}</a></td>
                <td><span class="badge ${m.store_platform === 'playstore' ? 'bg-success' : m.store_platform === 'ios' ? 'bg-dark' : 'bg-secondary'}">${escHtml(m.store_platform || 'web')}</span></td>
                <td>${m.rating ? '⭐ ' + parseFloat(m.rating).toFixed(1) : '-'}</td>
                <td><small class="text-muted">${m.auto_assigned ? escHtml(m.matched_keyword || 'auto') : '<em>manual</em>'}</small></td>
                <td><button class="btn btn-sm btn-outline-danger" onclick="removeMember(${g.id}, ${m.product_id})" title="Remove"><i class="bi bi-x"></i></button></td>
            </tr>`;
        }
        html += '</tbody></table></div>';
    }

    document.getElementById('detailBody').innerHTML = html;
}

async function deleteGroup(id, name) {
    if (!confirm(`Delete group "${name}" and all its assignments?`)) return;
    await apiCall('delete&id=' + id);
    loadGroups();
}

async function removeMember(groupId, productId) {
    await apiCall('remove_member', { group_id: groupId, product_id: productId });
    viewGroup(groupId);
}

async function autoAssignAll() {
    const data = await apiCall('auto_assign');
    if (data.success) {
        alert(`Auto-assigned ${data.total_assigned} apps to groups.`);
        loadGroups();
    }
}

async function loadUnassigned() {
    const data = await apiCall('unassigned');
    if (!data.apps || data.apps.length === 0) {
        document.getElementById('unassignedSection').style.display = 'none';
        return;
    }
    document.getElementById('unassignedSection').style.display = '';
    document.getElementById('unassignedCount').textContent = data.apps.length;
    const tbody = document.querySelector('#unassignedTable tbody');
    tbody.innerHTML = data.apps.slice(0, 50).map(a => `<tr>
        <td><a href="app_profile.php?id=${a.product_id}">${escHtml(a.app_name || a.product_name)}</a></td>
        <td>${escHtml(a.product_type || '-')}</td>
        <td>${escHtml(a.store_platform || 'web')}</td>
        <td>${escHtml(a.advertiser_name || a.advertiser_id || '-')}</td>
        <td><button class="btn btn-sm btn-outline-primary" onclick="assignToGroup(${a.product_id})"><i class="bi bi-plus"></i></button></td>
    </tr>`).join('');
}

async function assignToGroup(productId) {
    const data = await apiCall('list');
    if (!data.groups || data.groups.length === 0) return alert('Create a group first');
    const name = prompt('Enter group name to assign to:\n' + data.groups.map(g => '- ' + g.name).join('\n'));
    if (!name) return;
    const group = data.groups.find(g => g.name.toLowerCase() === name.toLowerCase());
    if (!group) return alert('Group not found');
    await apiCall('add_member', { group_id: group.id, product_id: productId });
    loadGroups();
}

function escHtml(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
</script>

<?php include 'includes/footer.php'; ?>
