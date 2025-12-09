// Analytics page functionality

// Get the correct base paths
const ANALYTICS_API_URL = (typeof AppConfig !== 'undefined')
    ? AppConfig.apiUrl
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? 'http://localhost/trinity/api'
        : 'https://trinity.futurewebhost.com.ng/api';

const ANALYTICS_UPLOADS_PATH = (typeof AppConfig !== 'undefined')
    ? AppConfig.uploadsPath
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? '/trinity/uploads'
        : '/uploads';

let currentArtistPage = 1;
let currentTrackPage = 1;
const itemsPerPage = 3;
let myChart = null;

/**
 * Load earnings data
 */
async function loadEarnings() {
    try {
        const response = await fetch(`${ANALYTICS_API_URL}/earnings`, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success && data.data) {
            const { earnings, summary, by_platform } = data.data;

            // Display earnings table
            displayEarningsTable(earnings);

            // Update chart with platform data
            updateEarningsChart(by_platform);
        } else {
            console.log('No earnings data available');

            document.getElementById('earningsTableBody').innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No earnings data available yet</p>
                    </td>
                </tr>
            `;

            // Show empty chart
            updateEarningsChart([]);
        }
    } catch (error) {
        console.error('Failed to load earnings:', error);
        document.getElementById('earningsTableBody').innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <p>Failed to load earnings data</p>
                </td>
            </tr>
        `;
    }
}

/**
 * Update chart with earnings by platform
 */
function updateEarningsChart(platformData) {
    const ctx = document.getElementById('myChart').getContext('2d');

    if (myChart) {
        myChart.destroy();
    }

    if (!platformData || platformData.length === 0) {
        // Show empty chart
        myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: []
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
                }
            }
        });
        return;
    }

    // Prepare data for chart
    const platforms = platformData.map(p => p.dsp || 'Unknown');
    const streams = platformData.map(p => parseInt(p.streams || 0));
    const earnings = platformData.map(p => parseFloat(p.earnings || 0));

    myChart = new Chart(ctx, {
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

/**
 * Display earnings in table
 */
function displayEarningsTable(earnings) {
    const tbody = document.getElementById('earningsTableBody');

    if (!earnings || earnings.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No earnings data available yet</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = earnings.map(earning => {
        const royaltyFormatted = parseFloat(earning.royalty || 0).toFixed(6);
        const statusBadge = earning.sale_or_void === 'Sale'
            ? '<span class="badge bg-success">Sale</span>'
            : '<span class="badge bg-secondary">Void</span>';

        return `
            <tr>
                <td><strong>${escapeHtml(earning.track_title || 'N/A')}</strong></td>
                <td>${escapeHtml(earning.track_artists || 'N/A')}</td>
                <td><span class="badge bg-primary">${escapeHtml(earning.dsp || 'N/A')}</span></td>
                <td>${escapeHtml(earning.activity_period || 'N/A')}</td>
                <td><span class="badge bg-info">${escapeHtml(earning.territory || 'N/A')}</span></td>
                <td><strong>${parseInt(earning.streams || 0).toLocaleString()}</strong></td>
                <td class="text-success">$${royaltyFormatted}</td>
                <td>${statusBadge}</td>
            </tr>
        `;
    }).join('');
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

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('currentYear').textContent = new Date().getFullYear();

    // Load analytics data
    await loadEarnings();
    await loadArtists();
    await loadTracks();
});

async function loadAnalyticsChart() {
    try {
        const response = await API.getAnalytics();
        
        if (response.success && response.data) {
            const chartData = response.data;
            
            // Prepare datasets for Chart.js
            const datasets = [];
            const colors = [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 206, 86)',
                'rgb(75, 192, 192)',
                'rgb(153, 102, 255)',
                'rgb(255, 159, 64)'
            ];
            
            let colorIndex = 0;
            for (const platform in chartData) {
                datasets.push({
                    label: platform,
                    data: chartData[platform].streams,
                    borderColor: colors[colorIndex % colors.length],
                    backgroundColor: colors[colorIndex % colors.length].replace('rgb', 'rgba').replace(')', ', 0.1)'),
                    fill: false,
                    tension: 0.1
                });
                colorIndex++;
            }
            
            // Get all unique dates
            const allDates = new Set();
            for (const platform in chartData) {
                chartData[platform].dates.forEach(date => allDates.add(date));
            }
            const labels = Array.from(allDates).sort();
            
            // Create chart
            const ctx = document.getElementById('myChart').getContext('2d');
            
            if (myChart) {
                myChart.destroy();
            }
            
            myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets
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
                        display: true,
                        position: 'bottom'
                    }
                }
            });
        } else {
            // Show empty chart
            const ctx = document.getElementById('myChart').getContext('2d');
            myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    } catch (error) {
        console.error('Failed to load analytics chart:', error);
    }
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
                // Fix artwork path
                let artistArtworkUrl = '';
                if (artist.artwork_path) {
                    const cleanPath = artist.artwork_path.replace(/^uploads\//, '');
                    artistArtworkUrl = `${ANALYTICS_UPLOADS_PATH}/${cleanPath}`;
                }
                const avatarHtml = artist.artwork_path
                    ? `<img src="${artistArtworkUrl}" alt="${escapeHtml(artist.stage_name)}" class="playlist-avatar">`
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
                // Fix artwork path
                let trackArtworkUrl = '';
                if (track.artwork_path) {
                    const cleanPath = track.artwork_path.replace(/^uploads\//, '');
                    trackArtworkUrl = `${ANALYTICS_UPLOADS_PATH}/${cleanPath}`;
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
