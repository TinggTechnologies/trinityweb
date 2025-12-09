/**
 * Admin Administration JavaScript
 */

// Use AppConfig if available, otherwise fallback
const API_BASE_URL = (typeof AppConfig !== 'undefined')
    ? AppConfig.apiUrl
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? 'http://localhost/trinity/api'
        : 'https://trinity.futurewebhost.com.ng/api';
let adminsTable;
let allAdmins = [];
let availableUsers = [];
let adminRoles = [];

// Check authentication on page load
async function checkAuth() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/me`, {
            credentials: 'include'
        });

        if (!response.ok) {
            window.location.href = './login';
            return null;
        }

        const data = await response.json();
        if (data.success && data.data && data.data.admin) {
            const adminName = data.data.admin.first_name || 'Admin';
            document.getElementById('adminWelcome').textContent = 'Welcome, ' + adminName;
            return data.data.admin;
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        window.location.href = './login';
        return null;
    }
}

// Load administrators data
async function loadAdministrators() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/administrators`, {
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success && data.data) {
            allAdmins = data.data.administrators || [];
            availableUsers = data.data.available_users || [];
            adminRoles = data.data.admin_roles || [];
            const analytics = data.data.analytics || {};

            // Populate analytics cards
            populateAnalytics(analytics);

            // Populate administrators table
            populateAdminsTable(allAdmins);
            
            // Populate dropdowns
            populateUserDropdown();
            populateRoleDropdowns();
        }
    } catch (error) {
        console.error('Failed to load administrators:', error);
        showMessage('Failed to load administrators data', 'danger');
    }
}

// Populate analytics cards
function populateAnalytics(analytics) {
    const container = document.getElementById('analyticsContainer');
    
    const cards = [
        { label: 'Total Administrators', value: analytics.total_admins || 0, icon: 'user-shield' },
        { label: 'Admin Roles', value: analytics.total_roles || 0, icon: 'key' },
        { label: 'Total Users', value: analytics.total_users || 0, icon: 'users' },
        { label: 'Active Admins', value: analytics.active_admins || 0, icon: 'user-check' }
    ];

    container.innerHTML = cards.map(card => `
        <div class="analysis-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="no-gap secondary-text">${card.label}</p>
                    <h4 class="no-gap card-value text-danger mb-0">${card.value}</h4>
                </div>
                <div class="analysis-icon text-danger">
                    <i class="fas fa-${card.icon}"></i>
                </div>
            </div>
        </div>
    `).join('');
}

// Populate administrators table
function populateAdminsTable(admins) {
    // Destroy existing DataTable first
    if ($.fn.DataTable.isDataTable('#adminsTable')) {
        $('#adminsTable').DataTable().destroy();
    }
    
    const tbody = document.getElementById('adminsTableBody');
    
    if (admins.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4">
                    <i class="fas fa-user-shield fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No administrators found.</p>
                    <button class="btn btn-danger mt-2" data-bs-toggle="modal" data-bs-target="#createAdminModal">
                        <i class="fas fa-plus me-1"></i> Create First Administrator
                    </button>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = admins.map(admin => {
        const createdDate = admin.created_at ? new Date(admin.created_at).toLocaleDateString('en-US', { 
            year: 'numeric', month: 'short', day: 'numeric' 
        }) : 'N/A';
        
        const displayName = admin.stage_name || `${admin.first_name} ${admin.last_name}`.trim();
        
        return `
            <tr>
                <td>${createdDate}</td>
                <td>${displayName}</td>
                <td>${admin.phone_number || 'N/A'}</td>
                <td>
                    <span class="badge bg-primary status-badge">${admin.role_title}</span>
                </td>
                <td>
                    <div class="dropdown action-dropdown">
                        <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="#" onclick="editAdmin(${admin.admin_id}, '${displayName.replace(/'/g, "\\'")}', ${admin.role_id})">
                                    <i class="fas fa-edit text-primary me-2"></i>Update Role
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="#" onclick="deleteAdmin(${admin.admin_id}, '${displayName.replace(/'/g, "\\'")}')">
                                    <i class="fas fa-trash me-2"></i>Remove Admin
                                </a>
                            </li>
                        </ul>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    // Initialize DataTables after populating
    setTimeout(() => {
        adminsTable = $('#adminsTable').DataTable({
            pageLength: 20,
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: [4] }
            ]
        });
    }, 100);
}

// Populate user dropdown for creating admin
function populateUserDropdown() {
    const select = document.getElementById('createMember');

    if (availableUsers.length === 0) {
        select.innerHTML = '<option value="">No users found</option>';
        select.disabled = true;
        document.getElementById('createAdminButton').disabled = true;
        return;
    }

    select.innerHTML = '<option value="">Select a user</option>' +
        availableUsers.map(user => {
            const displayName = user.stage_name || `${user.first_name} ${user.last_name}`.trim();
            const isAdmin = user.is_admin == 1;
            const adminLabel = isAdmin ? ' (Already Admin)' : '';
            const email = user.email ? ` - ${user.email}` : '';
            return `<option value="${user.id}" ${isAdmin ? 'disabled' : ''}>${displayName}${email}${adminLabel}</option>`;
        }).join('');
}

// Populate role dropdowns
function populateRoleDropdowns() {
    const createRoleSelect = document.getElementById('createRole');
    const editRoleSelect = document.getElementById('editRole');

    const roleOptions = '<option value="">Select a role</option>' +
        adminRoles.map(role => `<option value="${role.id}">${role.title}</option>`).join('');

    createRoleSelect.innerHTML = roleOptions;
    editRoleSelect.innerHTML = roleOptions;
}

// Show message
function showMessage(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    const container = document.querySelector('.container.p-3');
    container.insertBefore(alertDiv, container.firstChild);

    setTimeout(() => alertDiv.remove(), 5000);
}

// Edit administrator
function editAdmin(adminId, adminName, roleId) {
    document.getElementById('editAdminId').value = adminId;
    document.getElementById('editAdminName').textContent = adminName;
    document.getElementById('editRole').value = roleId;

    const editModal = new bootstrap.Modal(document.getElementById('editAdminModal'));
    editModal.show();
}

// Delete administrator
async function deleteAdmin(adminId, adminName) {
    if (!confirm(`Are you sure you want to remove ${adminName} as an administrator?`)) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/admin/administrators/${adminId}`, {
            method: 'DELETE',
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            showMessage('Administrator removed successfully', 'success');
            await loadAdministrators();
        } else {
            showMessage(data.message || 'Failed to remove administrator', 'danger');
        }
    } catch (error) {
        console.error('Error deleting administrator:', error);
        showMessage('Failed to remove administrator', 'danger');
    }
}

// Create administrator
async function createAdmin() {
    const userId = document.getElementById('createMember').value;
    const roleId = document.getElementById('createRole').value;

    if (!userId || !roleId) {
        showMessage('Please select both a user and a role', 'warning');
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/admin/administrators`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ user_id: userId, role_id: roleId })
        });

        const data = await response.json();

        if (data.success) {
            showMessage('Administrator created successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createAdminModal')).hide();
            document.getElementById('createMember').value = '';
            document.getElementById('createRole').value = '';
            await loadAdministrators();
        } else {
            showMessage(data.message || 'Failed to create administrator', 'danger');
        }
    } catch (error) {
        console.error('Error creating administrator:', error);
        showMessage('Failed to create administrator', 'danger');
    }
}

// Update administrator
async function updateAdmin() {
    const adminId = document.getElementById('editAdminId').value;
    const roleId = document.getElementById('editRole').value;

    if (!adminId || !roleId) {
        showMessage('Please select a role', 'warning');
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/admin/administrators/${adminId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ role_id: roleId })
        });

        const data = await response.json();

        if (data.success) {
            showMessage('Administrator role updated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editAdminModal')).hide();
            await loadAdministrators();
        } else {
            showMessage(data.message || 'Failed to update administrator', 'danger');
        }
    } catch (error) {
        console.error('Error updating administrator:', error);
        showMessage('Failed to update administrator', 'danger');
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', async () => {
    await checkAuth();
    await loadAdministrators();

    // Event listeners
    document.getElementById('createAdminButton').addEventListener('click', createAdmin);
    document.getElementById('updateAdminButton').addEventListener('click', updateAdmin);

    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });
    }
});

