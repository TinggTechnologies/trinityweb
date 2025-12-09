/**
 * Admin Royalty Management JavaScript
 */

// Use AppConfig if available, otherwise fallback
const API_BASE_URL = (typeof AppConfig !== 'undefined')
    ? AppConfig.apiUrl
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? 'http://localhost/trinity/api'
        : 'https://trinity.futurewebhost.com.ng/api';

// Check authentication on page load
document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
    loadRoyalties();

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

/**
 * Check if admin is authenticated
 */
function checkAuth() {
    fetch(`${API_BASE_URL}/admin/me`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                window.location.href = './login';
                return;
            }
            // Update welcome message
            const adminName = data.data.first_name || 'Admin';
            document.getElementById('adminWelcome').textContent = `Welcome, ${adminName}`;
        })
        .catch(error => {
            console.error('Auth check failed:', error);
            window.location.href = './login';
        });
}

/**
 * Load royalties from API
 */
function loadRoyalties() {
    fetch(`${API_BASE_URL}/admin/royalties`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateAnalytics(data.data.analytics || {});
                populateRoyaltiesTable(data.data.royalties || []);
            } else {
                showMessage('Error loading royalties: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading royalties:', error);
            showMessage('Failed to load royalties. Please try again.', 'danger');
        });
}

/**
 * Populate analytics cards
 */
function populateAnalytics(analytics) {
    const cards = [
        { label: 'Total Royalties', value: '$' + (analytics.total_royalties || '0.00'), icon: 'dollar-sign' },
        { label: 'Approved', value: '$' + (analytics.approved || '0.00'), icon: 'check-circle' },
        { label: 'Pending', value: '$' + (analytics.pending || '0.00'), icon: 'clock' },
        { label: 'Rejected', value: '$' + (analytics.rejected || '0.00'), icon: 'times-circle' }
    ];

    const container = document.getElementById('analyticsCards');
    container.innerHTML = cards.map(card => `
        <div class="analysis-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="no-gap secondary-text">${escapeHtml(card.label)}</p>
                    <h4 class="no-gap card-value text-danger mb-0">${escapeHtml(card.value)}</h4>
                </div>
                <div class="analysis-icon text-danger">
                    <i class="fas fa-${card.icon} fa-2x"></i>
                </div>
            </div>
        </div>
    `).join('');
}

/**
 * Populate royalties table
 */
function populateRoyaltiesTable(royalties) {
    // Destroy existing DataTable first
    if ($.fn.DataTable.isDataTable('#royaltiesTable')) {
        $('#royaltiesTable').DataTable().destroy();
    }

    const tbody = document.getElementById('royaltiesTableBody');

    if (royalties.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <i class="fas fa-hand-holding-usd fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No royalty requests found.</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = royalties.map(royalty => {
        const createdDate = royalty.created_at ? new Date(royalty.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
        const displayName = royalty.stage_name || `${royalty.first_name || ''} ${royalty.last_name || ''}`.trim() || 'N/A';
        const period = royalty.period ? new Date(royalty.period).toLocaleDateString('en-US', { day: '2-digit', month: '2-digit', year: 'numeric' }) : 'N/A';
        const amount = parseFloat(royalty.amount || 0).toFixed(2);
        const statusClass = getStatusBadgeClass(royalty.status);

        return `
            <tr>
                <td><input type="checkbox" class="royalty-checkbox" value="${royalty.id}"></td>
                <td>${escapeHtml(createdDate)}</td>
                <td>${escapeHtml(displayName)}</td>
                <td>${escapeHtml(royalty.phone_number || 'N/A')}</td>
                <td>$${amount}</td>
                <td>${escapeHtml(period)}</td>
                <td><span class="badge ${statusClass} status-badge">${escapeHtml(royalty.status || 'N/A')}</span></td>
                <td>
                    <div class="dropdown action-dropdown">
                        <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="updateStatus(${royalty.id}, 'Approved'); return false;">
                                <i class="fas fa-check text-success me-2"></i> Mark as Approved
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateStatus(${royalty.id}, 'Rejected'); return false;">
                                <i class="fas fa-times text-danger me-2"></i> Mark as Rejected
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateStatus(${royalty.id}, 'Pending'); return false;">
                                <i class="fas fa-clock text-warning me-2"></i> Mark as Pending
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteRoyalty(${royalty.id}, '${escapeHtml(displayName)}'); return false;">
                                <i class="fas fa-trash me-2"></i> Delete
                            </a></li>
                        </ul>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    // Initialize DataTables after populating
    setTimeout(() => {
        $('#royaltiesTable').DataTable({
            order: [[1, 'desc']],
            pageLength: 25,
            language: {
                search: "",
                searchPlaceholder: "Search royalties..."
            }
        });
    }, 100);
}

/**
 * Update royalty status
 */
function updateStatus(royaltyId, status) {
    if (!confirm(`Are you sure you want to mark this royalty request as ${status}?`)) {
        return;
    }

    fetch(`${API_BASE_URL}/admin/royalties/${royaltyId}/status`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(`Royalty request marked as ${status}`, 'success');
            loadRoyalties();
        } else {
            showMessage('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        showMessage('Failed to update status. Please try again.', 'danger');
    });
}

/**
 * Delete royalty
 */
function deleteRoyalty(royaltyId, artistName) {
    if (!confirm(`Are you sure you want to delete the royalty request for ${artistName}?`)) {
        return;
    }

    fetch(`${API_BASE_URL}/admin/royalties/${royaltyId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Royalty request deleted successfully', 'success');
            loadRoyalties();
        } else {
            showMessage('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error deleting royalty:', error);
        showMessage('Failed to delete royalty. Please try again.', 'danger');
    });
}

/**
 * Get status badge class
 */
function getStatusBadgeClass(status) {
    switch(status) {
        case 'Pending': return 'bg-warning';
        case 'Approved': return 'bg-success';
        case 'Rejected': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Show message
 */
function showMessage(message, type) {
    const messageArea = document.getElementById('messageArea');
    messageArea.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = messageArea.querySelector('.alert');
        if (alert) {
            alert.classList.remove('show');
            setTimeout(() => messageArea.innerHTML = '', 150);
        }
    }, 5000);
}

/**
 * Toggle select all checkboxes
 */
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.royalty-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

