// Dashboard functionality

// Use API_BASE_URL from api.js, or fallback to AppConfig
const DASHBOARD_API_URL = (typeof API_BASE_URL !== 'undefined')
    ? API_BASE_URL
    : (typeof AppConfig !== 'undefined')
        ? AppConfig.apiUrl
        : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
            ? 'http://localhost/trinity/api'
            : 'https://trinity.futurewebhost.com.ng/api';

// Get the correct base path for uploads
const DASHBOARD_UPLOADS_PATH = (typeof AppConfig !== 'undefined')
    ? AppConfig.uploadsPath
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? '/trinity/uploads'
        : '/uploads';

let currentArtistPage = 1;
let currentTrackPage = 1;
const itemsPerPage = 3;
let earningsChart = null;

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('currentYear').textContent = new Date().getFullYear();

    // Load user profile
    await loadUserProfile();

    // Load earnings chart
    await loadEarningsChart();

    // Load artists and tracks
    await loadArtists(1);
    await loadTracks(1);
});

async function loadUserProfile() {
    try {
        const response = await API.getCurrentUser();
        if (response.success && response.data) {
            const fullName = `${response.data.first_name} ${response.data.last_name}`;
            document.getElementById('userName').textContent = fullName;
        }
    } catch (error) {
        console.error('Failed to load user profile:', error);
        document.getElementById('userName').textContent = 'User';
    }
}

/**
 * Load earnings chart
 */
async function loadEarningsChart() {
    try {
        const response = await fetch(`${DASHBOARD_API_URL}/earnings`, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success && data.data && data.data.by_platform) {
            updateEarningsChart(data.data.by_platform);
        } else {
            updateEarningsChart([]);
        }
    } catch (error) {
        console.error('Failed to load earnings:', error);
        updateEarningsChart([]);
    }
}

/**
 * Update chart with earnings by platform
 */
function updateEarningsChart(platformData) {
    const ctx = document.getElementById('earningsChart').getContext('2d');

    if (earningsChart) {
        earningsChart.destroy();
    }

    if (!platformData || platformData.length === 0) {
        // Show empty chart
        earningsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['No Data'],
                datasets: [{
                    label: 'No earnings data available',
                    data: [0],
                    backgroundColor: 'rgba(200, 200, 200, 0.3)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                },
                legend: {
                    display: false
                }
            }
        });
        return;
    }

    // Prepare data for chart
    const platforms = platformData.map(p => p.dsp || 'Unknown');
    const streams = platformData.map(p => parseInt(p.streams || 0));
    const earnings = platformData.map(p => parseFloat(p.earnings || 0));

    earningsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: platforms,
            datasets: [
                {
                    label: 'Streams',
                    data: streams,
                    backgroundColor: 'rgba(237, 50, 55, 0.6)',
                    borderColor: 'rgba(237, 50, 55, 1)',
                    borderWidth: 1,
                    yAxisID: 'y-axis-streams'
                },
                {
                    label: 'Earnings ($)',
                    data: earnings,
                    backgroundColor: 'rgba(40, 167, 69, 0.6)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1,
                    yAxisID: 'y-axis-earnings'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [
                    {
                        id: 'y-axis-streams',
                        type: 'linear',
                        position: 'left',
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        },
                        scaleLabel: {
                            display: true,
                            labelString: 'Streams'
                        }
                    },
                    {
                        id: 'y-axis-earnings',
                        type: 'linear',
                        position: 'right',
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        },
                        scaleLabel: {
                            display: true,
                            labelString: 'Earnings ($)'
                        },
                        gridLines: {
                            drawOnChartArea: false
                        }
                    }
                ]
            },
            legend: {
                display: true,
                position: 'bottom'
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        const datasetLabel = data.datasets[tooltipItem.datasetIndex].label || '';
                        const value = tooltipItem.yLabel;

                        if (datasetLabel === 'Streams') {
                            return datasetLabel + ': ' + value.toLocaleString();
                        } else {
                            return datasetLabel + ': $' + value.toFixed(6);
                        }
                    }
                }
            }
        }
    });
}

async function loadArtists(page = 1) {
    currentArtistPage = page;
    const container = document.getElementById('artistsContainer');

    if (!container) {
        console.error('Artists container not found!');
        return;
    }

    try {
        const response = await API.getTopArtists(itemsPerPage, (page - 1) * itemsPerPage);

        if (response.success && response.data && response.data.artists && response.data.artists.length > 0) {
            const artists = response.data.artists;
            const totalPages = Math.ceil(response.data.total / itemsPerPage);

            let html = '';
            artists.forEach(artist => {
                const streams = formatNumber(artist.total_streams || 0);
                // Fix artwork path - convert relative to absolute
                let artworkUrl = '';
                if (artist.artwork_path) {
                    const cleanPath = artist.artwork_path.replace(/^uploads\//, '');
                    artworkUrl = `${DASHBOARD_UPLOADS_PATH}/${cleanPath}`;
                }
                const avatarHtml = artist.artwork_path
                    ? `<img src="${artworkUrl}" alt="${escapeHtml(artist.stage_name)}" class="playlist-avatar">`
                    : `<div class="default-avatar">${artist.stage_name.charAt(0).toUpperCase()}</div>`;

                html += `
                    <div class="artist-row">
                        <div class="artist-name">
                            <div class="playlist-avatar-wrapper">
                                ${avatarHtml}
                            </div>
                            <div class="playlist-title">
                                <h6 class="no-space">${escapeHtml(artist.stage_name)}</h6>
                            </div>
                        </div>
                        <div class="streams-container">
                            <div class="streams-scrollable">
                                <div class="no-space">
                                    <p class="no-space">Streams</p>
                                </div>
                                <div class="no-space">
                                    <p class="no-space"><strong>${streams}</strong></p>
                                </div>
                            </div>
                        </div>

                        <div class="px-2">
                            <i class="bi bi-chevron-double-right"></i>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;

            // Render pagination
            renderPagination('artistsPagination', currentArtistPage, totalPages, loadArtists);
        } else {
            container.innerHTML = '<div class="no-data-message"><i class="bi bi-music-note-beamed" style="font-size: 3rem; color: #ccc;"></i><p class="mt-3">No artists found. Create your first release to get started!</p></div>';
            // Still show pagination even with no data (will show disabled buttons)
            renderPagination('artistsPagination', 1, 0, loadArtists);
        }
    } catch (error) {
        console.error('Failed to load artists:', error);
        container.innerHTML = '<div class="no-data-message"><p>Failed to load artists</p></div>';
    }
}

async function loadTracks(page = 1) {
    currentTrackPage = page;
    const container = document.getElementById('tracksContainer');

    if (!container) {
        console.error('Tracks container not found!');
        return;
    }

    try {
        const response = await API.getTopTracks(itemsPerPage, (page - 1) * itemsPerPage);

        if (response.success && response.data && response.data.tracks && response.data.tracks.length > 0) {
            const tracks = response.data.tracks;
            const totalPages = Math.ceil(response.data.total / itemsPerPage);

            let html = '';
            tracks.forEach(track => {
                const streams = formatNumber(track.total_streams || 0);
                const trackTitle = track.track_version
                    ? `${track.track_title} (${track.track_version})`
                    : track.track_title;
                // Fix artwork path - convert relative to absolute
                let trackArtworkUrl = '';
                if (track.artwork_path) {
                    const cleanPath = track.artwork_path.replace(/^uploads\//, '');
                    trackArtworkUrl = `${DASHBOARD_UPLOADS_PATH}/${cleanPath}`;
                }
                const avatarHtml = track.artwork_path
                    ? `<img src="${trackArtworkUrl}" alt="${escapeHtml(track.track_title)}" class="playlist-avatar">`
                    : `<div class="default-avatar">${track.track_title.charAt(0).toUpperCase()}</div>`;

                html += `
                    <div class="artist-row">
                        <div class="artist-name">
                            <div class="playlist-avatar-wrapper">
                                ${avatarHtml}
                            </div>
                            <div class="playlist-title">
                                <h6 class="no-space">${escapeHtml(trackTitle)}</h6>
                            </div>
                        </div>
                        <div class="streams-container">
                            <div class="streams-scrollable">
                                <div class="no-space">
                                    <p class="no-space">Streams</p>
                                </div>
                                <div class="no-space">
                                    <p class="no-space"><strong>${streams}</strong></p>
                                </div>
                            </div>
                        </div>

                        <div class="px-2">
                            <i class="bi bi-chevron-double-right"></i>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;

            // Render pagination
            renderPagination('tracksPagination', currentTrackPage, totalPages, loadTracks);
        } else {
            container.innerHTML = '<div class="no-data-message"><i class="bi bi-disc" style="font-size: 3rem; color: #ccc;"></i><p class="mt-3">No tracks found. Upload your first track to get started!</p></div>';
            // Still show pagination even with no data (will show disabled buttons)
            renderPagination('tracksPagination', 1, 0, loadTracks);
        }
    } catch (error) {
        console.error('Failed to load tracks:', error);
        container.innerHTML = '<div class="no-data-message"><p>Failed to load tracks</p></div>';
    }
}

function renderPagination(containerId, currentPage, totalPages, loadFunction) {
    const container = document.getElementById(containerId);

    // Always show pagination, ensure at least 1 page
    const pages = Math.max(1, totalPages);

    let html = '<div class="pagination-wrapper"><nav aria-label="Pagination"><ul class="pagination justify-content-center mb-0">';

    // Previous button (disabled if on first page)
    html += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>
    </li>`;

    // Page numbers (at least 1 page button)
    for (let i = 1; i <= pages; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" data-page="${i}">${i}</a>
        </li>`;
    }

    // Next button (disabled if on last page)
    html += `<li class="page-item ${currentPage >= pages ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>
    </li>`;

    html += '</ul></nav></div>';
    container.innerHTML = html;

    // Add click handlers
    container.querySelectorAll('.page-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = parseInt(e.target.dataset.page);
            if (page && page !== currentPage && page >= 1 && page <= pages) {
                loadFunction(page);
            }
        });
    });
}

