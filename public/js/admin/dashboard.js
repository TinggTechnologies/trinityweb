/**
 * Admin Dashboard JavaScript
 */

// Use AppConfig if available, otherwise fallback
const API_BASE_URL = (typeof AppConfig !== 'undefined')
    ? AppConfig.apiUrl
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? 'http://localhost/trinity/api'
        : 'https://trinity.futurewebhost.com.ng/api';

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

// Load dashboard statistics
async function loadStats() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/dashboard/stats`, {
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success && data.data) {
            const stats = data.data;

            // Update stat cards
            document.getElementById('usersCount').textContent = Number(stats.total_users || 0).toLocaleString();
            document.getElementById('tracksCount').textContent = Number(stats.total_tracks || 0).toLocaleString();
            document.getElementById('ticketsCount').textContent = Number(stats.open_tickets || 0).toLocaleString();
            document.getElementById('releasesCount').textContent = Number(stats.total_releases || 0).toLocaleString();
            document.getElementById('artistsCount').textContent = Number(stats.total_artists || 0).toLocaleString();
            document.getElementById('royaltiesAmount').textContent = '$' + parseFloat(stats.total_royalties || 0).toFixed(2);
        }
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
}

// Load chart data
async function loadChartData() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/dashboard/chart-data`, {
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success && data.data) {
            const chartData = data.data;

            // Prepare data for Chart.js
            const labels = chartData.map(item => item.day);
            const userData = chartData.map(item => item.user);
            const releasesData = chartData.map(item => item.releases);
            const ticketsData = chartData.map(item => item.tickets);

            // Create chart
            const ctx = document.getElementById('activityChart').getContext('2d');
            const activityChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Users',
                            data: userData,
                            backgroundColor: '#ED3237',
                            borderColor: '#ED3237',
                            borderWidth: 1
                        },
                        {
                            label: 'Releases',
                            data: releasesData,
                            backgroundColor: '#D0AF6F',
                            borderColor: '#D0AF6F',
                            borderWidth: 1
                        },
                        {
                            label: 'Tickets',
                            data: ticketsData,
                            backgroundColor: '#3498db',
                            borderColor: '#3498db',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Days'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Failed to load chart data:', error);
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', async () => {
    await checkAuth();
    await loadStats();
    await loadChartData();

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
