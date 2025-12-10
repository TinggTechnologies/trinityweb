/**
 * Released Songs - View and manage releases
 */

// Get the correct base paths for assets and uploads
const ASSETS_PATH = (typeof AppConfig !== 'undefined')
    ? AppConfig.assetsPath
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? '/trinity/assets'
        : '/assets';

const UPLOADS_PATH = (typeof AppConfig !== 'undefined')
    ? AppConfig.uploadsPath
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? '/trinity/uploads'
        : '/uploads';

let currentPage = 1;
let totalPages = 1;
let totalReleases = 0;
let currentReleases = [];
const recordsPerPage = 10;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    console.log('Released Songs page initializing...');

    // Set current year in footer
    const currentYearElement = document.getElementById('currentYear');
    if (currentYearElement) {
        currentYearElement.textContent = new Date().getFullYear();
    }

    // Load releases
    loadReleases();

    // Setup modal event listeners
    setupModalListeners();
});

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

        console.log('Making API request to:', `/releases?page=${currentPage}&limit=${recordsPerPage}`);
        const response = await API.get(`/releases?page=${currentPage}&limit=${recordsPerPage}`);
        console.log('API Response:', response);
        console.log('Response success:', response.success);
        console.log('Response data:', response.data);

        if (response.success) {
            currentReleases = response.data.releases || [];

            // Get pagination info from server
            if (response.data.pagination) {
                totalPages = response.data.pagination.pages;
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
                <h3>No Releases Yet</h3>
                <p class="text-muted">You haven't created any releases yet. Start by creating your first release.</p>
                <a href="./create-release" class="btn btn-danger mt-3">
                    <i class="bi bi-plus-circle"></i> Create New Release
                </a>
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
        // Fix artwork path - convert relative path to absolute
        let artworkUrl = `${ASSETS_PATH}/images/user.png`;
        if (release.artwork_path) {
            // artwork_path is like "uploads/artworks/file.jpg"
            const cleanPath = release.artwork_path.replace(/^uploads\//, '');
            artworkUrl = `${UPLOADS_PATH}/${cleanPath}`;
        }
        // Use track_release_date as fallback if main release_date is not set
        const dateToUse = getValidReleaseDate(release.release_date, release.track_release_date);
        const releaseDate = dateToUse ? formatDate(dateToUse) : '<span class="text-muted">Not set</span>';
        const artists = release.artists || 'Unknown Artist';

        html += `
            <tr>
                <td class="table-img">
                    <img src="${artworkUrl}" alt="art" onerror="this.src='${ASSETS_PATH}/images/user.png'">
                </td>
                <td><h6>${escapeHtml(release.release_title)}</h6></td>
                <td>${releaseDate}</td>
                <td><span class="">${escapeHtml(artists)}</span></td>
                <td>
                    <div class="badge-outline ${statusColor}">
                        ${release.status.charAt(0).toUpperCase() + release.status.slice(1)}
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
                        href="./create-release?edit=${release.id}"
                        class="btn btn-link text-primary p-0 me-3"
                        title="Edit Release"
                        style="font-size: 1.1rem;">
                        <i class="bi bi-pencil-square"></i>
                    </a>
                    <button
                        class="btn btn-link text-danger p-0 delete-btn"
                        data-id="${release.id}"
                        data-bs-toggle="modal"
                        data-bs-target="#deleteModal"
                        title="Delete Release"
                        style="font-size: 1.1rem;">
                        <i class="bi bi-trash"></i>
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

/**
 * Calculate release progress
 */
function calculateProgress(release) {
    let progress = 0;

    if (release.release_title) progress += 10;
    if (release.genre) progress += 10;
    if (release.label_name) progress += 10;
    if (release.artwork_path) progress += 10;
    if (release.track_count > 0) progress += 30;
    if (release.store_count > 0) progress += 20;
    if (release.status !== 'draft') progress += 10;

    return Math.min(100, progress);
}

/**
 * Get status color class
 */
function getStatusColor(status) {
    const colors = {
        'draft': 'col-blue',
        'pending': 'col-yellow',
        'approved': 'col-green',
        'rejected': 'col-red',
        'live': 'col-green',
        'taken_down': 'col-red'
    };
    return colors[status] || 'col-blue';
}

/**
 * Get progress bar color class
 */
function getProgressColor(progress) {
    if (progress < 50) return 'bg-danger';
    if (progress < 80) return 'bg-warning';
    return 'bg-success';
}

/**
 * Display pagination
 */
function displayPagination() {
    const container = document.getElementById('paginationContainer');

    console.log('Displaying pagination - Total pages:', totalPages, 'Current page:', currentPage);

    if (totalPages <= 1) {
        // Show page info even with 1 page
        if (totalReleases > 0) {
            container.innerHTML = `
                <div class="text-center text-muted mt-3">
                    <small>Page 1 of 1 (${totalReleases} total releases)</small>
                </div>
            `;
        } else {
            container.innerHTML = '';
        }
        return;
    }

    let html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';

    // Previous button
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span> Previous
            </a>
        </li>
    `;

    // Smart pagination - show max 7 page numbers
    const maxPagesToShow = 7;
    let startPage = 1;
    let endPage = totalPages;

    if (totalPages > maxPagesToShow) {
        const halfWay = Math.ceil(maxPagesToShow / 2);

        if (currentPage > halfWay) {
            startPage = currentPage - halfWay + 1;
            endPage = currentPage + halfWay - 1;
        } else {
            endPage = maxPagesToShow;
        }

        if (endPage > totalPages) {
            endPage = totalPages;
            startPage = totalPages - maxPagesToShow + 1;
        }
    }

    // First page button
    if (startPage > 1) {
        html += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="changePage(1); return false;">1</a>
            </li>
        `;
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

    // Last page button
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        html += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="changePage(${totalPages}); return false;">${totalPages}</a>
            </li>
        `;
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

    container.innerHTML = html;
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
 * Setup modal event listeners
 */
function setupModalListeners() {
    // Delete modal
    document.addEventListener('click', (e) => {
        if (e.target.closest('.delete-btn')) {
            const btn = e.target.closest('.delete-btn');
            document.getElementById('delete_id').value = btn.dataset.id;
        }
    });

    // Delete confirm button
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async () => {
            await handleDeleteConfirm();
        });
    }
}

/**
 * Handle delete confirmation
 */
async function handleDeleteConfirm() {
    const releaseId = document.getElementById('delete_id').value;

    try {
        const response = await API.delete(`/releases/${releaseId}`);

        if (response.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
            modal.hide();

            showSuccess('Release deleted successfully');

            // Reload releases
            await loadReleases();
        } else {
            showError(response.message || 'Failed to delete release');
        }
    } catch (error) {
        console.error('Error deleting release:', error);
        showError('You do not have access to delete release');
    }
}

/**
 * Format date to dd-mm-yyyy
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

        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}-${month}-${year}`;
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
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Show success message
 */
function showSuccess(message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    setTimeout(() => {
        container.innerHTML = '';
    }, 5000);
}

/**
 * Show error message
 */
function showError(message) {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

