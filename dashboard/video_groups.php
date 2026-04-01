<?php include 'includes/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-camera-video me-2"></i>Video Groups</h4>
            <p class="text-muted mb-0">Group YouTube ad videos by keywords. Auto-assigns by title, channel, description.</p>
        </div>
        <div>
            <button class="btn btn-outline-secondary me-2" onclick="autoAssignAll()">
                <i class="bi bi-magic me-1"></i>Auto-Assign All
            </button>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                <i class="bi bi-plus-lg me-1"></i>Create Group
            </button>
        </div>
    </div>

    <div class="row g-4" id="groupsGrid">
        <div class="col-12 text-center py-5"><div class="spinner-border text-danger"></div></div>
    </div>

    <div class="mt-5" id="unassignedSection" style="display:none">
        <h5 class="mb-3"><i class="bi bi-question-circle me-2"></i>Unassigned Videos <span class="badge bg-secondary" id="unassignedCount">0</span></h5>
        <div class="table-responsive">
            <table class="table table-sm table-hover" id="unassignedTable">
                <thead><tr><th></th><th>Title</th><th>Channel</th><th>Views</th><th>Action</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Create Video Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editGroupId" value="">
                <div class="mb-3">
                    <label class="form-label fw-bold">Group Name</label>
                    <input type="text" class="form-control" id="groupName" placeholder="e.g. VPN & Security Apps">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Description</label>
                    <input type="text" class="form-control" id="groupDesc" placeholder="Videos promoting VPN, security, privacy apps">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Keywords <small class="text-muted">(comma separated — matched against video title, channel, description)</small></label>
                    <textarea class="form-control" id="groupKeywords" rows="3" placeholder="vpn, secure, privacy, protect, antivirus, cyber security"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Color</label>
                        <input type="color" class="form-control form-control-color" id="groupColor" value="#dc3545">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Icon</label>
                        <select class="form-select" id="groupIcon">
                            <option value="bi-camera-video">Video</option>
                            <option value="bi-play-circle">Play</option>
                            <option value="bi-youtube">YouTube</option>
                            <option value="bi-film">Film</option>
                            <option value="bi-broadcast">Broadcast</option>
                            <option value="bi-megaphone">Promo</option>
                            <option value="bi-music-note">Music</option>
                            <option value="bi-controller">Gaming</option>
                            <option value="bi-phone">Phone</option>
                            <option value="bi-shield">Security</option>
                            <option value="bi-geo-alt">Location</option>
                            <option value="bi-cart">Shopping</option>
                            <option value="bi-heart">Health</option>
                            <option value="bi-bank">Finance</option>
                            <option value="bi-book">Education</option>
                            <option value="bi-people">Social</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="saveGroup()">
                    <i class="bi bi-check-lg me-1"></i><span id="saveBtn">Create Group</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="groupDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailTitle">Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody">
                <div class="text-center py-4"><div class="spinner-border text-danger"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
const API = 'api/video_groups.php';

document.addEventListener('DOMContentLoaded', loadGroups);

async function apiCall(action, data = null) {
    const url = API + '?action=' + action;
    const opts = data ? { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) } : {};
    return (await fetch(url, opts)).json();
}

function fmtViews(n) {
    if (!n) return '0';
    n = parseInt(n);
    if (n >= 1e7) return (n/1e7).toFixed(1) + ' Cr';
    if (n >= 1e5) return (n/1e5).toFixed(1) + ' L';
    if (n >= 1e3) return (n/1e3).toFixed(1) + 'K';
    return n.toLocaleString();
}

async function loadGroups() {
    const data = await apiCall('list');
    const grid = document.getElementById('groupsGrid');

    if (!data.groups || data.groups.length === 0) {
        grid.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-camera-video display-1 text-muted"></i>
                <h5 class="mt-3 text-muted">No video groups yet</h5>
                <p class="text-muted">Create your first group to categorize YouTube ad videos by keywords.</p>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                    <i class="bi bi-plus-lg me-1"></i>Create Group
                </button>
            </div>`;
        return;
    }

    grid.innerHTML = data.groups.map(g => `
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card h-100 shadow-sm" style="border-top: 4px solid ${g.color || '#dc3545'}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="card-title mb-1">
                                <i class="${g.icon || 'bi-camera-video'} me-2" style="color:${g.color}"></i>${escHtml(g.name)}
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
                            <small class="text-muted">Videos</small>
                        </div>
                        <div class="text-center">
                            <div class="h4 mb-0">${g.keyword_count}</div>
                            <small class="text-muted">Keywords</small>
                        </div>
                        <div class="text-center">
                            <div class="h4 mb-0">${fmtViews(g.total_views)}</div>
                            <small class="text-muted">Total Views</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <button class="btn btn-sm btn-outline-danger w-100" onclick="viewGroup(${g.id})">
                        <i class="bi bi-eye me-1"></i>View Videos
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
        if (data.auto_assigned > 0) alert(`Group saved! ${data.auto_assigned} videos auto-assigned.`);
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
    document.getElementById('groupColor').value = g.color || '#dc3545';
    document.getElementById('groupIcon').value = g.icon || 'bi-camera-video';
    document.getElementById('modalTitle').textContent = 'Edit Group';
    document.getElementById('saveBtn').textContent = 'Save Changes';
    new bootstrap.Modal(document.getElementById('createGroupModal')).show();
}

document.getElementById('createGroupModal').addEventListener('hidden.bs.modal', () => {
    document.getElementById('editGroupId').value = '';
    document.getElementById('groupName').value = '';
    document.getElementById('groupDesc').value = '';
    document.getElementById('groupKeywords').value = '';
    document.getElementById('groupColor').value = '#dc3545';
    document.getElementById('groupIcon').value = 'bi-camera-video';
    document.getElementById('modalTitle').textContent = 'Create Video Group';
    document.getElementById('saveBtn').textContent = 'Create Group';
});

async function viewGroup(id) {
    const modal = new bootstrap.Modal(document.getElementById('groupDetailModal'));
    document.getElementById('detailBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-danger"></div></div>';
    modal.show();

    const data = await apiCall('get&id=' + id);
    if (!data.success) { document.getElementById('detailBody').innerHTML = '<p class="text-danger">Error loading group</p>'; return; }

    const g = data.group;
    const totalViews = g.members.reduce((s, m) => s + parseInt(m.view_count || 0), 0);
    document.getElementById('detailTitle').innerHTML = `<i class="${g.icon} me-2" style="color:${g.color}"></i>${escHtml(g.name)} <span class="badge bg-danger">${g.members.length} videos</span> <span class="badge bg-dark">${fmtViews(totalViews)} views</span>`;

    let html = `
        <div class="mb-3">
            <strong>Keywords:</strong>
            ${(g.keywords || []).map(k => `<span class="badge bg-light text-dark border me-1">${escHtml(k)}</span>`).join('')}
        </div>`;

    if (g.members.length === 0) {
        html += '<p class="text-muted">No videos matched yet. Try adding more keywords.</p>';
    } else {
        html += `<div class="table-responsive"><table class="table table-sm table-hover align-middle">
            <thead><tr><th></th><th>Title</th><th>Channel</th><th>Views</th><th>Likes</th><th>Matched By</th><th></th></tr></thead><tbody>`;
        for (const m of g.members) {
            const thumb = m.thumbnail_url ? `<img src="${escHtml(m.thumbnail_url)}" width="80" class="rounded" onerror="this.style.display='none'">` : '<i class="bi bi-play-btn fs-3"></i>';
            html += `<tr>
                <td>${thumb}</td>
                <td><a href="youtube_profile.php?id=${escHtml(m.video_id)}">${escHtml(m.title || m.video_id)}</a></td>
                <td><small class="text-muted">${escHtml(m.channel_name || '-')}</small></td>
                <td><strong>${fmtViews(m.view_count)}</strong></td>
                <td>${fmtViews(m.like_count)}</td>
                <td><small class="text-muted">${m.auto_assigned ? escHtml(m.matched_keyword || 'auto') : '<em>manual</em>'}</small></td>
                <td><button class="btn btn-sm btn-outline-danger" onclick="removeMember(${g.id}, '${escHtml(m.video_id)}')" title="Remove"><i class="bi bi-x"></i></button></td>
            </tr>`;
        }
        html += '</tbody></table></div>';
    }

    document.getElementById('detailBody').innerHTML = html;
}

async function deleteGroup(id, name) {
    if (!confirm(`Delete group "${name}"?`)) return;
    await apiCall('delete&id=' + id);
    loadGroups();
}

async function removeMember(groupId, videoId) {
    await apiCall('remove_member', { group_id: groupId, video_id: videoId });
    viewGroup(groupId);
}

async function autoAssignAll() {
    const data = await apiCall('auto_assign');
    if (data.success) {
        alert(`Auto-assigned ${data.total_assigned} videos to groups.`);
        loadGroups();
    }
}

async function loadUnassigned() {
    const data = await apiCall('unassigned');
    if (!data.videos || data.videos.length === 0) {
        document.getElementById('unassignedSection').style.display = 'none';
        return;
    }
    document.getElementById('unassignedSection').style.display = '';
    document.getElementById('unassignedCount').textContent = data.videos.length;
    const tbody = document.querySelector('#unassignedTable tbody');
    tbody.innerHTML = data.videos.slice(0, 50).map(v => `<tr>
        <td>${v.thumbnail_url ? `<img src="${escHtml(v.thumbnail_url)}" width="60" class="rounded">` : ''}</td>
        <td><a href="youtube_profile.php?id=${escHtml(v.video_id)}">${escHtml(v.title || v.video_id)}</a></td>
        <td>${escHtml(v.channel_name || '-')}</td>
        <td>${fmtViews(v.view_count)}</td>
        <td><button class="btn btn-sm btn-outline-danger" onclick="assignToGroup('${escHtml(v.video_id)}')"><i class="bi bi-plus"></i></button></td>
    </tr>`).join('');
}

async function assignToGroup(videoId) {
    const data = await apiCall('list');
    if (!data.groups || data.groups.length === 0) return alert('Create a group first');
    const name = prompt('Enter group name:\n' + data.groups.map(g => '- ' + g.name).join('\n'));
    if (!name) return;
    const group = data.groups.find(g => g.name.toLowerCase() === name.toLowerCase());
    if (!group) return alert('Group not found');
    await apiCall('add_member', { group_id: group.id, video_id: videoId });
    loadGroups();
}

function escHtml(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
</script>

<?php include 'includes/footer.php'; ?>
