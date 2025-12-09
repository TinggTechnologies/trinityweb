/**
 * Admin Earnings Upload JavaScript
 */

let selectedFile = null;
let uploadsTable = null;
let earningsTable = null;

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
 * Load earnings data
 */
async function loadEarnings() {
    try {
        const response = await API.get('/admin/earnings/data');

        if (response.success) {
            const earnings = response.data.earnings || [];
            displayEarnings(earnings);
        } else {
            showError(response.message || 'Failed to load earnings data');
        }
    } catch (error) {
        console.error('Error loading earnings:', error);
        showError('Failed to load earnings data');
    }
}

/**
 * Display earnings in table
 */
function displayEarnings(earnings) {
    const tbody = document.getElementById('earningsTableBody');

    if (earnings.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No earnings data yet</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = earnings.map(earning => {
        const royaltyFormatted = parseFloat(earning.royalty).toFixed(6);
        const statusBadge = earning.sale_or_void === 'Sale'
            ? '<span class="badge bg-success">Sale</span>'
            : '<span class="badge bg-secondary">Void</span>';

        return `
            <tr>
                <td><strong>${escapeHtml(earning.track_title || 'N/A')}</strong></td>
                <td>${escapeHtml(earning.track_artists || 'N/A')}</td>
                <td>${escapeHtml(earning.release_name || 'N/A')}</td>
                <td><span class="badge bg-primary">${escapeHtml(earning.dsp || 'N/A')}</span></td>
                <td>${escapeHtml(earning.activity_period || 'N/A')}</td>
                <td><span class="badge bg-info">${escapeHtml(earning.territory || 'N/A')}</span></td>
                <td><strong>${parseInt(earning.streams || 0).toLocaleString()}</strong></td>
                <td>$${royaltyFormatted}</td>
                <td>${statusBadge}</td>
            </tr>
        `;
    }).join('');

    // Initialize DataTable if not already initialized
    if (!earningsTable) {
        earningsTable = $('#earningsTable').DataTable({
            order: [[6, 'desc']], // Sort by streams descending
            pageLength: 25,
            language: {
                search: "Search earnings:"
            }
        });
    }
}

/**
 * Load upload history
 */
async function loadUploads() {
    try {
        const response = await API.get('/admin/earnings/uploads');

        if (response.success) {
            const uploads = response.data.uploads || [];
            displayUploads(uploads);
        } else {
            showError(response.message || 'Failed to load upload history');
        }
    } catch (error) {
        console.error('Error loading uploads:', error);
        showError('Failed to load upload history');
    }
}

/**
 * Display uploads in table
 */
function displayUploads(uploads) {
    const tbody = document.getElementById('uploadsTableBody');

    if (uploads.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No uploads yet</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = uploads.map(upload => {
        const uploadDate = new Date(upload.uploaded_at).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        const uploaderName = upload.first_name && upload.last_name 
            ? `${upload.first_name} ${upload.last_name}` 
            : 'Unknown';

        return `
            <tr>
                <td>${uploadDate}</td>
                <td><i class="fas fa-file-csv text-danger me-2"></i>${escapeHtml(upload.filename)}</td>
                <td><span class="badge bg-success">${upload.rows_imported.toLocaleString()}</span></td>
                <td>${escapeHtml(uploaderName)}</td>
                <td><code class="small">${escapeHtml(upload.batch_id)}</code></td>
            </tr>
        `;
    }).join('');

    // Initialize DataTables
    if (uploadsTable) {
        uploadsTable.destroy();
    }
    uploadsTable = $('#uploadsTable').DataTable({
        pageLength: 10,
        order: [[0, 'desc']]
    });
}

/**
 * Handle file selection
 */
function handleFileSelect(file) {
    if (!file) return;

    // Validate file type
    if (!file.name.endsWith('.csv')) {
        showError('Please select a CSV file');
        return;
    }

    // Validate file size (max 50MB)
    if (file.size > 50 * 1024 * 1024) {
        showError('File size exceeds 50MB limit');
        return;
    }

    selectedFile = file;

    // Show file info
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatFileSize(file.size);
    document.getElementById('fileInfo').style.display = 'block';
    document.getElementById('uploadBtn').disabled = false;
}

/**
 * Clear selected file
 */
function clearFile() {
    selectedFile = null;
    document.getElementById('csvFileInput').value = '';
    document.getElementById('fileInfo').style.display = 'none';
    document.getElementById('uploadBtn').disabled = true;
}

/**
 * Upload file
 */
async function uploadFile() {
    if (!selectedFile) {
        showError('Please select a file first');
        return;
    }

    const formData = new FormData();
    formData.append('csv_file', selectedFile);

    // Show progress
    document.getElementById('progressContainer').style.display = 'block';
    document.getElementById('uploadBtn').disabled = true;

    try {
        const response = await fetch(window.location.origin + '/api/admin/earnings/upload', {
            method: 'POST',
            credentials: 'include',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showSuccess(`Successfully uploaded ${data.data.rows_imported.toLocaleString()} rows from ${data.data.filename}`);
            clearFile();
            await loadEarnings();
            await loadUploads();
        } else {
            showError(data.message || 'Failed to upload file');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showError('Failed to upload file');
    } finally {
        document.getElementById('progressContainer').style.display = 'none';
        document.getElementById('uploadBtn').disabled = false;
    }
}

/**
 * Format file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
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
            <i class="fas fa-exclamation-triangle me-2"></i>
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
            <i class="fas fa-check-circle me-2"></i>
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Initialize page
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Admin Earnings page initializing...');

    await checkAuth();
    await loadEarnings();
    await loadUploads();

    // File input change
    document.getElementById('csvFileInput').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });

    // Drag and drop
    const uploadArea = document.getElementById('uploadArea');

    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');

        if (e.dataTransfer.files.length > 0) {
            handleFileSelect(e.dataTransfer.files[0]);
        }
    });

    uploadArea.addEventListener('click', function() {
        document.getElementById('csvFileInput').click();
    });

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


