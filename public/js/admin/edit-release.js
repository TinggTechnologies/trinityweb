/**
 * Admin Edit Release Page
 */

let releaseId = null;
let releaseData = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', async () => {
    // Check admin authentication
    const admin = await checkAdminAuth();
    if (!admin) return;

    // Get release ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    releaseId = urlParams.get('id');

    if (!releaseId) {
        showAlert('No release ID provided', 'danger');
        document.getElementById('loadingSpinner').style.display = 'none';
        return;
    }

    // Load release data
    await loadReleaseData();

    // Setup mobile menu
    setupMobileMenu();
});

/**
 * Check admin authentication
 */
async function checkAdminAuth() {
    try {
        const response = await API.get('/admin/me');
        if (response.success && response.data && response.data.admin) {
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
 * Load release data from API
 */
async function loadReleaseData() {
    try {
        console.log('Loading release data for ID:', releaseId);
        const response = await API.get(`/admin/songs/${releaseId}`);
        console.log('API Response:', response);

        if (response.success && response.data) {
            // Handle both data structures: data.release or data directly
            releaseData = response.data.release || response.data;
            console.log('Release data:', releaseData);

            try {
                populateForm(releaseData);
                document.getElementById('loadingSpinner').style.display = 'none';
                document.getElementById('releaseContent').style.display = 'block';
            } catch (formError) {
                console.error('Error populating form:', formError);
                showAlert('Error displaying release data: ' + formError.message, 'danger');
                document.getElementById('loadingSpinner').style.display = 'none';
            }
        } else {
            showAlert(response.message || 'Failed to load release', 'danger');
            document.getElementById('loadingSpinner').style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading release:', error);
        showAlert('Error loading release data: ' + error.message, 'danger');
        document.getElementById('loadingSpinner').style.display = 'none';
    }
}

/**
 * Populate form with release data
 */
function populateForm(release) {
    console.log('Populating form with release:', release);

    // Release info
    document.getElementById('releaseTitle').value = release.release_title || '';
    document.getElementById('releaseVersion').value = release.release_version || '';
    document.getElementById('catalogNumber').value = release.catalog_number || '';
    document.getElementById('primaryArtist').value = release.artists || '';

    // Handle release date - use track's release_date as fallback
    const validReleaseDate = getValidReleaseDate(release.release_date, release.tracks);
    if (validReleaseDate) {
        const dateValue = validReleaseDate.split(' ')[0].split('T')[0]; // Handle both "2024-01-01 00:00:00" and "2024-01-01T00:00:00"
        document.getElementById('releaseDate').value = dateValue;
    } else {
        document.getElementById('releaseDate').value = '';
    }
    document.getElementById('genre').value = release.genre || '';
    document.getElementById('subgenre').value = release.subgenre || '';
    document.getElementById('status').value = release.status || 'draft';
    document.getElementById('upcCode').value = release.upc || '';
    document.getElementById('isrcCode').value = release.isrc || '';
    document.getElementById('labelName').value = release.label_name || '';
    document.getElementById('numTracks').value = release.num_tracks || '0';
    document.getElementById('pricingTier').value = formatPricingTier(release.pricing_tier) || '';

    // C-Line and P-Line
    const cLine = release.c_line_year ? `${release.c_line_year} ${release.c_line_text || ''}` : '';
    const pLine = release.p_line_year ? `${release.p_line_year} ${release.p_line_text || ''}` : '';
    document.getElementById('cLine').value = cLine;
    document.getElementById('pLine').value = pLine;

    // Created date
    if (release.created_at) {
        const createdDate = new Date(release.created_at);
        document.getElementById('createdAt').value = createdDate.toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });
    }

    // Status badge
    const statusBadge = document.getElementById('statusBadge');
    const status = release.status || 'draft';
    const statusLabels = {
        'draft': 'Draft',
        'pending': 'Pending Review',
        'approved': 'Approved',
        'rejected': 'Rejected',
        'distributed': 'Distributed',
        'live': 'Live',
        'taken_down': 'Taken Down'
    };
    statusBadge.textContent = statusLabels[status] || status;
    statusBadge.className = `status-badge status-${status}`;

    // Artwork
    const artworkPreview = document.getElementById('artworkPreview');
    const artworkPlaceholder = document.getElementById('artworkPlaceholder');
    if (release.artwork_path) {
        const cleanPath = release.artwork_path.replace(/^uploads\//, '');
        artworkPreview.src = `${AppConfig.uploadsPath}/${cleanPath}`;
        artworkPreview.style.display = 'block';
        artworkPlaceholder.style.display = 'none';
        artworkPreview.onerror = () => {
            artworkPreview.style.display = 'none';
            artworkPlaceholder.style.display = 'block';
        };
    } else {
        artworkPreview.style.display = 'none';
        artworkPlaceholder.style.display = 'block';
    }

    // Owner info
    if (release.user) {
        document.getElementById('ownerName').textContent =
            `${release.user.first_name || ''} ${release.user.last_name || ''}`.trim() || '-';
        document.getElementById('ownerEmail').textContent = release.user.email || '-';
        document.getElementById('ownerId').textContent = release.user.id || '-';
    }

    // Set view tracks link - link to admin release-cont page
    const viewTracksBtn = document.getElementById('viewTracksBtn');
    if (viewTracksBtn) {
        viewTracksBtn.href = `./release-cont?release_id=${release.id}`;
    }

    // Tracks
    populateTracks(release.tracks || []);
}

/**
 * Format pricing tier for display
 */
function formatPricingTier(tier) {
    const tiers = {
        'single_black': 'Single - Black',
        'single_white': 'Single - White',
        'album': 'Album'
    };
    return tiers[tier] || tier || '';
}

/**
 * Get valid release date - checks main release_date first, then falls back to first track's release_date
 */
function getValidReleaseDate(releaseDate, tracks) {
    // Check if main release date is valid
    if (releaseDate && releaseDate !== '0000-00-00' && !releaseDate.startsWith('0000-00-00')) {
        return releaseDate;
    }

    // Fallback to first track's release date
    if (tracks && tracks.length > 0 && tracks[0].release_date) {
        const trackDate = tracks[0].release_date;
        if (trackDate && trackDate !== '0000-00-00' && !trackDate.startsWith('0000-00-00')) {
            return trackDate;
        }
    }

    return null;
}

/**
 * Populate tracks section (removed from UI but kept for compatibility)
 */
function populateTracks(tracks) {
    const container = document.getElementById('tracksContainer');

    // Skip if container doesn't exist (tracks section removed)
    if (!container) {
        return;
    }

    if (!tracks || tracks.length === 0) {
        container.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> No tracks have been added to this release yet.</div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-hover">';
    html += `
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Song Name</th>
                <th>Version</th>
                <th>ISRC</th>
                <th>Duration</th>
            </tr>
        </thead>
        <tbody>
    `;

    tracks.forEach((track, index) => {
        // Use correct field names from API: track_title, track_version, isrc
        const trackName = track.track_title || track.song_name || 'Untitled';
        const trackVersion = track.track_version || track.version || '';
        const trackIsrc = track.isrc || track.isrc_code || '';

        html += `
            <tr>
                <td>${track.track_number || index + 1}</td>
                <td><strong>${escapeHtml(trackName)}</strong></td>
                <td>${trackVersion ? escapeHtml(trackVersion) : '-'}</td>
                <td><code>${trackIsrc || '-'}</code></td>
                <td>${formatDuration(track.duration)}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

/**
 * Format duration in seconds to mm:ss
 */
function formatDuration(seconds) {
    if (!seconds) return '-';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

/**
 * Save release changes
 */
async function saveRelease() {
    const saveBtn = document.querySelector('button[onclick="saveRelease()"]');
    const originalText = saveBtn.innerHTML;
    const loaderOverlay = document.getElementById('loaderOverlay');

    try {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        if (loaderOverlay) loaderOverlay.classList.add('active');

        const data = {
            release_title: document.getElementById('releaseTitle').value,
            release_date: document.getElementById('releaseDate').value,
            status: document.getElementById('status').value,
            upc: document.getElementById('upcCode').value,
            isrc: document.getElementById('isrcCode').value
        };

        const response = await API.put(`/admin/songs/${releaseId}`, data);

        if (response.success) {
            showAlert('Release updated successfully!', 'success');
            // Update status badge
            const status = data.status;
            const statusBadge = document.getElementById('statusBadge');
            const statusLabels = {
                'draft': 'Draft',
                'pending': 'Pending Review',
                'approved': 'Approved',
                'rejected': 'Rejected',
                'distributed': 'Distributed',
                'live': 'Live',
                'taken_down': 'Taken Down'
            };
            statusBadge.textContent = statusLabels[status] || status;
            statusBadge.className = `status-badge status-${status}`;
        } else {
            showAlert(response.message || 'Failed to update release', 'danger');
        }
    } catch (error) {
        console.error('Error saving release:', error);
        showAlert('Error saving release: ' + error.message, 'danger');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
        if (loaderOverlay) loaderOverlay.classList.remove('active');
    }
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    container.innerHTML = alertHtml;

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
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
 * Logout function
 */
function logout() {
    localStorage.removeItem('admin_token');
    window.location.href = './login';
}

/**
 * Setup mobile menu
 */
function setupMobileMenu() {
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
}

