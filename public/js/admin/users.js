/**
 * Admin Users JavaScript
 */

// Use AppConfig if available, otherwise fallback
const API_BASE_URL = (typeof AppConfig !== 'undefined')
    ? AppConfig.apiUrl
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? 'http://localhost/trinity/api'
        : 'https://trinity.futurewebhost.com.ng/api';
let usersTable;
let allUsers = [];

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

// Load users data
async function loadUsers() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/users`, {
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success && data.data) {
            allUsers = data.data.users || [];
            const analytics = data.data.analytics || {};

            // Populate analytics cards
            populateAnalytics(analytics);

            // Populate users table
            populateUsersTable(allUsers);
        }
    } catch (error) {
        console.error('Failed to load users:', error);
        showMessage('Failed to load users data', 'danger');
    }
}

// Populate analytics cards
function populateAnalytics(analytics) {
    const analyticsCards = document.getElementById('analyticsCards');

    const cards = [
        { label: 'Total Users', value: analytics.total_users || 0, icon: 'users' },
        { label: 'Verified Users', value: analytics.verified_users || 0, icon: 'user-check' },
        { label: 'Artists', value: analytics.total_artists || 0, icon: 'microphone' },
        { label: 'This Month', value: analytics.users_this_month || 0, icon: 'calendar' },
        { label: 'With Releases', value: analytics.users_with_releases || 0, icon: 'compact-disc' },
        { label: 'Countries', value: analytics.total_countries || 0, icon: 'globe' }
    ];

    analyticsCards.innerHTML = cards.map(card => `
        <div class="analysis-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="no-gap secondary-text">${card.label}</p>
                    <h4 class="no-gap card-value text-danger mb-0">${card.value.toLocaleString()}</h4>
                </div>
                <div class="analysis-icon text-danger">
                    <i class="fas fa-${card.icon}"></i>
                </div>
            </div>
        </div>
    `).join('');
}

// Populate users table
function populateUsersTable(users) {
    const tbody = document.getElementById('usersTableBody');

    if (users.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No users found in the database.</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = users.map(user => {
        const fullName = `${user.first_name || ''} ${user.last_name || ''}`.trim();
        const address = formatAddress(user);
        const dateJoined = user.created_at ? new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
        const verifiedBadge = user.is_verified ?
            '<span class="badge bg-success status-badge">Verified</span>' :
            '<span class="badge bg-warning status-badge">Unverified</span>';

        return `
            <tr class="user-row">
                <td>
                    <input type="checkbox" class="user-checkbox" value="${user.id}">
                </td>
                <td>
                    <div>
                        <div class="user-name">${escapeHtml(fullName)}</div>
                        <div class="user-email">${escapeHtml(user.email)}</div>
                        ${verifiedBadge}
                    </div>
                </td>
                <td>
                    ${user.stage_name ?
                        `<span class="fw-bold text-danger">${escapeHtml(user.stage_name)}</span><br><small class="text-muted">${user.total_releases || 0} release(s)</small>` :
                        '<span class="text-muted">Not an artist</span>'}
                </td>
                <td>${escapeHtml(user.email)}</td>
                <td>${escapeHtml(address)}</td>
                <td>${escapeHtml(user.mobile_number || 'Not provided')}</td>
                <td>${dateJoined}</td>
                <td>
                    <div class="dropdown action-dropdown">
                        <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="#" onclick="editUser(${user.id}); return false;">
                                    <i class="fas fa-edit text-primary me-2"></i>Edit User
                                </a>
                            </li>
                            ${!user.is_verified ? `
                            <li>
                                <a class="dropdown-item" href="#" onclick="verifyUser(${user.id}); return false;">
                                    <i class="fas fa-check text-success me-2"></i>Verify User
                                </a>
                            </li>
                            ` : ''}
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="#" onclick="deleteUser(${user.id}); return false;">
                                    <i class="fas fa-trash me-2"></i>Delete User
                                </a>
                            </li>
                        </ul>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    // Initialize DataTables if not already initialized
    if ($.fn.DataTable.isDataTable('#usersTable')) {
        $('#usersTable').DataTable().destroy();
    }

    usersTable = $('#usersTable').DataTable({
        pageLength: 10,
        order: [[6, 'desc']], // Sort by date joined descending
        columnDefs: [
            { orderable: false, targets: [0, 7] } // Disable sorting for checkbox and action columns
        ]
    });
}

// Utility functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatAddress(user) {
    let address = '';
    if (user.residence_country) {
        if (user.origin_country && user.origin_country !== user.residence_country) {
            address = `${user.residence_country} (from ${user.origin_country})`;
        } else {
            address = user.residence_country;
        }
    } else if (user.origin_country) {
        address = user.origin_country;
    } else {
        address = 'Not specified';
    }
    return address;
}

function showMessage(message, type) {
    const messageArea = document.getElementById('messageArea');
    messageArea.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

// Edit user
async function editUser(userId) {
    try {
        // Find user in allUsers array
        const user = allUsers.find(u => u.id === userId);
        if (!user) {
            showMessage('User not found', 'danger');
            return;
        }

        // Populate the edit form
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editFirstName').value = user.first_name || '';
        document.getElementById('editLastName').value = user.last_name || '';
        document.getElementById('editEmail').value = user.email || '';
        document.getElementById('editStageName').value = user.stage_name || '';
        document.getElementById('editMobileNumber').value = user.mobile_number || '';
        document.getElementById('editOriginCountry').value = user.origin_country || '';
        document.getElementById('editResidenceCountry').value = user.residence_country || '';
        document.getElementById('editArtistBio').value = user.artist_bio || '';

        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
        modal.show();
    } catch (error) {
        console.error('Error loading user data:', error);
        showMessage('Failed to load user data', 'danger');
    }
}

// Save user changes
async function saveUserChanges() {
    const userId = document.getElementById('editUserId').value;
    const formData = {
        first_name: document.getElementById('editFirstName').value,
        last_name: document.getElementById('editLastName').value,
        email: document.getElementById('editEmail').value,
        stage_name: document.getElementById('editStageName').value,
        mobile_number: document.getElementById('editMobileNumber').value,
        origin_country: document.getElementById('editOriginCountry').value,
        residence_country: document.getElementById('editResidenceCountry').value,
        artist_bio: document.getElementById('editArtistBio').value
    };

    try {
        const response = await fetch(`${API_BASE_URL}/admin/users/${userId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
            showMessage('User updated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
            await loadUsers(); // Reload users
        } else {
            showMessage(data.message || 'Failed to update user', 'danger');
        }
    } catch (error) {
        console.error('Error updating user:', error);
        showMessage('Failed to update user', 'danger');
    }
}

// Verify user
async function verifyUser(userId) {
    if (!confirm('Are you sure you want to verify this user?')) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/admin/users/${userId}/verify`, {
            method: 'POST',
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            showMessage('User verified successfully', 'success');
            await loadUsers(); // Reload users
        } else {
            showMessage(data.message || 'Failed to verify user', 'danger');
        }
    } catch (error) {
        console.error('Error verifying user:', error);
        showMessage('Failed to verify user', 'danger');
    }
}

// Delete user
async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/admin/users/${userId}`, {
            method: 'DELETE',
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            showMessage('User deleted successfully', 'success');
            await loadUsers(); // Reload users
        } else {
            showMessage(data.message || 'Failed to delete user', 'danger');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        showMessage('Failed to delete user', 'danger');
    }
}

// Bulk delete
async function bulkDelete() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    if (checkboxes.length === 0) {
        showMessage('Please select at least one user to delete', 'warning');
        return;
    }

    if (!confirm(`Are you sure you want to delete ${checkboxes.length} user(s)? This action cannot be undone.`)) {
        return;
    }

    const userIds = Array.from(checkboxes).map(cb => cb.value);

    try {
        const response = await fetch(`${API_BASE_URL}/admin/users/bulk-delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ user_ids: userIds })
        });

        const data = await response.json();

        if (data.success) {
            showMessage(`${checkboxes.length} user(s) deleted successfully`, 'success');
            document.getElementById('selectAll').checked = false;
            await loadUsers(); // Reload users
        } else {
            showMessage(data.message || 'Failed to delete users', 'danger');
        }
    } catch (error) {
        console.error('Error deleting users:', error);
        showMessage('Failed to delete users', 'danger');
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', async () => {
    await checkAuth();
    await loadUsers();

    // Add event listener for save button
    document.getElementById('saveUserChanges').addEventListener('click', saveUserChanges);

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
