/**
 * Admin All Releases - View and manage all releases from all users
 */

let currentPage = 1;
let totalPages = 1;
let totalReleases = 0;
let currentReleases = [];
const recordsPerPage = 10;

// Check authentication on page load
async function checkAuth() {
    try {
        const response = await API.get('/admin/me');

        if (response.success && response.data && response.data.admin) {
            const adminName = response.data.admin.first_name || 'Admin';
            document.getElementById('adminWelcome').textContent = 'Welcome, ' + adminName;
            return response.data.admin;
        } else {
            window.location.href = './login';
            return null;
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        window.location.href = './login';
        return null;
    }
}

/**
 * Load releases for current page (server-side pagination)
 */
async function loadReleases() {
    try {
        // Show loading state
        const container = document.getElementById('releasesContainer');
        container.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-danger" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading releases...</p>
            </div>
        `;

        const response = await API.get(`/admin/songs?page=${currentPage}&limit=${recordsPerPage}`);
        console.log('API Response:', response);

        if (response.success) {
            currentReleases = response.data.releases || [];
            const analytics = response.data.analytics || {};

            // Get pagination info from server
            if (response.data.pagination) {
                totalPages = response.data.pagination.total_pages || response.data.pagination.pages;
                totalReleases = response.data.pagination.total;
                currentPage = response.data.pagination.page;
            } else {
                // Fallback if no pagination info
                totalPages = 1;
                totalReleases = currentReleases.length;
            }

            console.log('Current page:', currentPage);
            console.log('Total pages:', totalPages);
            console.log('Total releases:', totalReleases);
            console.log('Releases on this page:', currentReleases.length);

            // Populate analytics cards
            populateAnalytics(analytics);

            displayReleases();
            displayPagination();
        } else {
            showError(response.message || 'Failed to load releases');
        }
    } catch (error) {
        console.error('Error loading releases:', error);
        showError('Failed to load releases');
    }
}

/**
 * Display releases for current page
 */
function displayReleases() {
    const container = document.getElementById('releasesContainer');

    if (!currentReleases || currentReleases.length === 0) {
        container.innerHTML = `
            <div class="no-releases">
                <i class="bi bi-music-note-list"></i>
                <h3>No Releases Found</h3>
                <p class="text-muted">There are no releases in the system yet.</p>
            </div>
        `;
        return;
    }

    // Calculate showing range
    const showingStart = ((currentPage - 1) * recordsPerPage) + 1;
    const showingEnd = Math.min(currentPage * recordsPerPage, totalReleases);

    let html = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-muted">
                Showing ${showingStart} to ${showingEnd} of ${totalReleases} releases
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Art-Cover</th>
                        <th>Title</th>
                        <th>Release Date</th>
                        <th>Artist</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;

    currentReleases.forEach(release => {
        const progress = calculateProgress(release);
        const statusColor = getStatusColor(release.status);
        const progressColor = getProgressColor(progress);
        const artworkUrl = release.artwork_path ? `../../${release.artwork_path}` : '../../assets/images/user.png';
        // Use track_release_date as fallback if main release_date is not set
        const dateToUse = getValidReleaseDate(release.release_date, release.track_release_date);
        const releaseDate = dateToUse ? formatDate(dateToUse) : '<span class="text-muted">Not set</span>';
        const artists = release.artists || 'Unknown Artist';
        const userName = release.user_name || 'Unknown User';

        html += `
            <tr>
                <td class="table-img">
                    <img src="${artworkUrl}" alt="art" onerror="this.src='../../assets/images/user.png'">
                </td>
                <td><h6>${escapeHtml(release.release_title)}</h6></td>
                <td>${releaseDate}</td>
                <td><span class="">${escapeHtml(artists)}</span></td>
                <td><span class="text-muted">${escapeHtml(userName)}</span></td>
                <td>
                    <div class="badge-outline ${statusColor}">
                        ${formatStatus(release.status)}
                    </div>
                </td>
                <td>
                    <div class="progress-text">${progress}%</div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar ${progressColor}" style="width:${progress}%;"></div>
                    </div>
                </td>
                <td class="align-middle text-center">
                    <a
                        href="./edit-release?id=${release.id}"
                        class="btn btn-link text-primary p-0 me-2"
                        title="View/Edit Release"
                        style="font-size: 1.1rem;">
                        <i class="bi bi-pencil-square"></i>
                    </a>
                    <a
                        href="./release-cont?release_id=${release.id}"
                        class="btn btn-link text-info p-0 me-2"
                        title="View/Edit Tracks"
                        style="font-size: 1.1rem;">
                        <i class="bi bi-music-note-list"></i>
                    </a>
                    <button
                        onclick="deleteRelease(${release.id}, '${escapeHtml(release.release_title)}')"
                        class="btn btn-link text-danger p-0"
                        title="Take Down Release"
                        style="font-size: 1.1rem;">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;
}

// Populate analytics cards
function populateAnalytics(analytics) {
    const analyticsCards = document.getElementById('analyticsCards');

    const cards = [
        { label: 'Total Releases', value: analytics.total_releases || 0, icon: 'music' },
        { label: 'Draft', value: analytics.draft || 0, icon: 'file-earmark' },
        { label: 'Pending', value: analytics.pending || 0, icon: 'clock' },
        { label: 'Approved', value: analytics.approved || 0, icon: 'check-circle' },
        { label: 'Live', value: analytics.live || 0, icon: 'broadcast-tower' },
        { label: 'Total Users', value: analytics.total_users || 0, icon: 'people' }
    ];

    analyticsCards.innerHTML = cards.map(card => `
        <div class="analysis-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="no-gap secondary-text">${card.label}</p>
                    <h4 class="no-gap card-value text-danger mb-0">${card.value.toLocaleString()}</h4>
                </div>
                <div class="analysis-icon text-danger">
                    <i class="bi bi-${card.icon}"></i>
                </div>
            </div>
        </div>
    `).join('');
}

/**
 * Display pagination
 */
function displayPagination() {
    const container = document.getElementById('releasesContainer');

    if (totalPages <= 1) {
        // No pagination needed
        return;
    }

    let html = '<nav aria-label="Page navigation" class="mt-4"><ul class="pagination justify-content-center">';

    // Previous button
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span> Previous
            </a>
        </li>
    `;

    // Page numbers
    const maxPagesToShow = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

    // Adjust start if we're near the end
    if (endPage - startPage < maxPagesToShow - 1) {
        startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }

    // First page
    if (startPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(1); return false;">1</a></li>`;
        if (startPage > 2) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        html += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
            </li>
        `;
    }

    // Last page
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${totalPages}); return false;">${totalPages}</a></li>`;
    }

    // Next button
    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;" aria-label="Next">
                Next <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    `;

    html += '</ul></nav>';

    // Add page info
    html += `
        <div class="text-center text-muted mt-2">
            <small>Page ${currentPage} of ${totalPages} (${totalReleases} total releases)</small>
        </div>
    `;

    container.innerHTML += html;
}

/**
 * Change page
 */
function changePage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    loadReleases(); // Reload data from server for the new page
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Calculate release progress
 */
function calculateProgress(release) {
    let progress = 0;
    const steps = 5; // Total steps in release creation

    if (release.release_title) progress += 20;
    if (release.artwork_path) progress += 20;
    if (release.release_date) progress += 20;
    if (release.track_count > 0) progress += 20;
    if (release.store_count > 0) progress += 20;

    return Math.min(100, progress);
}

/**
 * Format status text for display
 */
function formatStatus(status) {
    if (!status) return 'Unknown';

    const statusLabels = {
        'draft': 'Draft',
        'pending': 'Pending',
        'approved': 'Approved',
        'live': 'Live',
        'rejected': 'Rejected',
        'taken_down': 'Taken Down',
        'distributed': 'Distributed'
    };
    return statusLabels[status] || status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');
}

/**
 * Get status color class
 */
function getStatusColor(status) {
    if (!status) return 'col-yellow';

    const statusMap = {
        'draft': 'col-yellow',
        'pending': 'col-blue',
        'approved': 'col-green',
        'live': 'col-green',
        'rejected': 'col-red',
        'taken_down': 'col-red',
        'distributed': 'col-green'
    };
    return statusMap[status] || 'col-yellow';
}

/**
 * Get progress bar color
 */
function getProgressColor(progress) {
    if (progress >= 100) return 'bg-success';
    if (progress >= 60) return 'bg-info';
    if (progress >= 30) return 'bg-warning';
    return 'bg-danger';
}

/**
 * Format date
 */
function formatDate(dateString) {
    if (!dateString) return '<span class="text-muted">Not set</span>';

    // Handle invalid MySQL dates like "0000-00-00"
    if (dateString === '0000-00-00' || dateString.startsWith('0000-00-00')) {
        return '<span class="text-muted">Not set</span>';
    }

    try {
        // Handle different date formats
        let date;
        if (dateString.includes(' ')) {
            // Format: "2024-01-01 00:00:00"
            date = new Date(dateString.replace(' ', 'T'));
        } else {
            date = new Date(dateString);
        }

        // Check if date is valid
        if (isNaN(date.getTime()) || date.getFullYear() < 1900) {
            return '<span class="text-muted">Not set</span>';
        }

        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch (e) {
        console.error('Error parsing date:', dateString, e);
        return '<span class="text-muted">Not set</span>';
    }
}

/**
 * Get valid release date - returns the first valid date or null
 */
function getValidReleaseDate(releaseDate, trackReleaseDate) {
    // Check if release date is valid
    if (releaseDate && releaseDate !== '0000-00-00' && !releaseDate.startsWith('0000-00-00')) {
        return releaseDate;
    }

    // Fallback to track release date
    if (trackReleaseDate && trackReleaseDate !== '0000-00-00' && !trackReleaseDate.startsWith('0000-00-00')) {
        return trackReleaseDate;
    }

    return null;
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Show error message
 */
function showError(message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Show success message
 */
function showSuccess(message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Take down release (change status to taken_down)
 */
async function deleteRelease(releaseId, releaseTitle) {
    if (!confirm(`Are you sure you want to take down "${releaseTitle}"? The release status will be changed to "Taken Down".`)) {
        return;
    }

    try {
        const response = await API.put(`/admin/songs/${releaseId}`, {
            status: 'taken_down'
        });

        if (response.success) {
            showSuccess('Release has been taken down successfully');
            // Reload releases
            await loadReleases();
        } else {
            showError(response.message || 'Failed to take down release');
        }
    } catch (error) {
        console.error('Error taking down release:', error);
        showError('Failed to take down release');
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Admin Songs page initializing...');

    await checkAuth();
    await loadReleases();

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

