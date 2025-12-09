/**
 * Admin Release Tracks Page
 * View and manage tracks for a release
 */

let releaseId = null;
let releaseData = null;

document.addEventListener('DOMContentLoaded', async function() {
    // Check admin authentication
    const admin = await checkAdminAuth();
    if (!admin) return;

    // Get release ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    releaseId = urlParams.get('release_id');

    if (!releaseId) {
        showAlert('No release ID provided', 'danger');
        document.getElementById('loadingSpinner').style.display = 'none';
        return;
    }

    // Set back link
    document.getElementById('backToReleaseBtn').href = `./edit-release?id=${releaseId}`;

    // Setup mobile menu
    setupMobileMenu();

    // Load release data
    await loadRelease();
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
 * Setup mobile menu toggle
 */
function setupMobileMenu() {
    const toggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
}

/**
 * Load release data
 */
async function loadRelease() {
    try {
        const response = await API.get(`/admin/songs/${releaseId}`);

        if (response.success && response.data) {
            // Handle both data structures: data.release or data directly
            releaseData = response.data.release || response.data;
            document.getElementById('releaseTitle').textContent = releaseData.release_title || 'Untitled Release';
            document.getElementById('artistName').textContent = releaseData.artists || 'Unknown Artist';

            renderTracks(releaseData.tracks || []);

            document.getElementById('loadingSpinner').style.display = 'none';
            document.getElementById('pageContent').style.display = 'block';
        } else {
            throw new Error(response.message || 'Failed to load release');
        }
    } catch (error) {
        console.error('Error loading release:', error);
        showAlert('Error loading release: ' + error.message, 'danger');
        document.getElementById('loadingSpinner').style.display = 'none';
    }
}

/**
 * Render tracks with editable forms
 */
function renderTracks(tracks) {
    const container = document.getElementById('tracksContainer');

    if (!tracks || tracks.length === 0) {
        container.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> No tracks have been added to this release yet.</div>';
        return;
    }

    let html = '';
    tracks.forEach((track, index) => {
        const trackTitle = track.track_title || track.song_name || '';
        const trackVersion = track.track_version || track.version || '';
        const audioUrl = track.audio_file_path ? `${AppConfig.uploadsPath}/${track.audio_file_path.replace(/^uploads\//, '')}` : '';
        const releaseDate = track.release_date && track.release_date !== '0000-00-00' ? track.release_date.split(' ')[0].split('T')[0] : '';

        html += `
            <div class="track-card" id="track-${track.id}">
                <form class="track-form" data-track-id="${track.id}">
                    <div class="track-header">
                        <div>
                            <span class="track-number">#${track.track_number || index + 1}</span>
                            <strong class="ms-2 fs-5">Track Details</strong>
                        </div>
                        <span class="badge bg-secondary">ID: ${track.id}</span>
                    </div>

                    ${audioUrl ? `
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-music-note"></i> Audio Preview</label>
                        <audio controls class="audio-player">
                            <source src="${audioUrl}" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                    ` : '<p class="text-muted"><i class="bi bi-exclamation-circle"></i> No audio file</p>'}

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Track Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="track_title" value="${escapeHtml(trackTitle)}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Version</label>
                            <input type="text" class="form-control form-control-sm" name="track_version" value="${escapeHtml(trackVersion)}">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Language</label>
                            <select class="form-control form-control-sm" name="language">
                                <option value="">Select Language</option>
                                <option value="English" ${track.language === 'English' ? 'selected' : ''}>English</option>
                                <option value="Yoruba" ${track.language === 'Yoruba' ? 'selected' : ''}>Yoruba</option>
                                <option value="Igbo" ${track.language === 'Igbo' ? 'selected' : ''}>Igbo</option>
                                <option value="Hausa" ${track.language === 'Hausa' ? 'selected' : ''}>Hausa</option>
                                <option value="French" ${track.language === 'French' ? 'selected' : ''}>French</option>
                                <option value="Spanish" ${track.language === 'Spanish' ? 'selected' : ''}>Spanish</option>
                                <option value="Other" ${track.language === 'Other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Explicit Content</label>
                            <select class="form-control form-control-sm" name="explicit_content">
                                <option value="no" ${track.explicit_content !== 'yes' ? 'selected' : ''}>No</option>
                                <option value="yes" ${track.explicit_content === 'yes' ? 'selected' : ''}>Yes</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Release Date</label>
                            <input type="date" class="form-control form-control-sm" name="release_date" value="${releaseDate}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Release Time</label>
                            <input type="time" class="form-control form-control-sm" name="release_time" value="${track.release_time || ''}">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Recording Year</label>
                            <input type="number" class="form-control form-control-sm" name="recording_year" value="${track.recording_year || ''}" min="1900" max="2099">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Recording Country</label>
                            <input type="text" class="form-control form-control-sm" name="recording_country" value="${escapeHtml(track.recording_country || '')}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Preview Start (sec)</label>
                            <input type="number" class="form-control form-control-sm" name="preview_start" value="${track.preview_start || 0}" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Duration</label>
                            <input type="text" class="form-control form-control-sm" value="${formatDuration(track.duration)}" readonly style="background-color: #e9ecef;">
                        </div>
                    </div>

                    ${renderTrackArtists(track.artists)}

                    <div class="track-actions d-flex justify-content-end">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-check-lg"></i> Save Track
                        </button>
                    </div>
                </form>
            </div>
        `;
    });

    container.innerHTML = html;

    // Attach form submit handlers
    document.querySelectorAll('.track-form').forEach(form => {
        form.addEventListener('submit', handleTrackSubmit);
    });
}

/**
 * Handle track form submission
 */
async function handleTrackSubmit(e) {
    e.preventDefault();

    const form = e.target;
    const trackId = form.dataset.trackId;
    const formData = new FormData(form);
    const loaderOverlay = document.getElementById('loaderOverlay');
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;

    // Build data object
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    try {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
        if (loaderOverlay) loaderOverlay.classList.add('active');

        const response = await API.put(`/admin/tracks/${trackId}`, data);

        if (response.success) {
            showAlert(`Track "${data.track_title}" saved successfully!`, 'success');
        } else {
            showAlert(response.message || 'Failed to save track', 'danger');
        }
    } catch (error) {
        console.error('Error saving track:', error);
        showAlert('Error saving track: ' + error.message, 'danger');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        if (loaderOverlay) loaderOverlay.classList.remove('active');
    }
}

/**
 * Render track artists
 */
function renderTrackArtists(artists) {
    if (!artists || artists.length === 0) return '<p class="text-muted small mt-2"><i class="bi bi-people"></i> No contributors added</p>';

    let html = '<div class="artist-list mt-3"><label class="form-label"><i class="bi bi-people"></i> Contributors</label><div>';
    artists.forEach(artist => {
        html += `<span class="artist-badge">${escapeHtml(artist.artist_name)} <small class="text-muted">(${artist.role})</small></span>`;
    });
    html += '</div></div>';
    return html;
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
 * Format date
 */
function formatDate(dateString) {
    if (!dateString || dateString === '0000-00-00' || dateString.startsWith('0000-00-00')) {
        return '-';
    }
    try {
        let date;
        if (dateString.includes(' ')) {
            date = new Date(dateString.replace(' ', 'T'));
        } else {
            date = new Date(dateString);
        }
        if (isNaN(date.getTime()) || date.getFullYear() < 1900) {
            return '-';
        }
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch (e) {
        return '-';
    }
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
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
 * Logout function
 */
function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = './login';
}

