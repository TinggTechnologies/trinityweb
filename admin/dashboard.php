<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Trinity Distribution</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-red: #ED3237;
            --secondary: #6c757d;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .sidebar {
            background: var(--primary-red);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            padding-top: 20px;
            z-index: 1000;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        /* Logo styling to match image */
        .logo-section {
            text-align: left;
            padding: 0 20px 30px 20px;
        }
        .logo-text {
            font-size: 2rem;
            font-weight: 300;
            margin: 0;
            letter-spacing: 1px;
        }
        .logo-subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 0;
            font-weight: 300;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.9);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 5px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .nav-link.active {
            background-color: white;
            color: var(--primary-red);
        }
        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .page-label {
            background-color: white;
            border-bottom: 1px solid #dee2e6;
            margin: -20px -20px 20px -20px;
            padding: 15px 20px;
        }
        .page-label-heading {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #333;
        }
        .page-label-subheading {
            color: #6c757d;
            margin-bottom: 0;
        }
        .board-analysis {
            margin-bottom: 20px;
        }
        .analysis-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin: 10px;
            transition: transform 0.3s;
            flex: 1;
            min-width: 200px;
        }
        .analysis-card:hover {
            transform: translateY(-5px);
        }
        .analysis-icon {
            font-size: 2rem;
            opacity: 0.7;
        }
        .card-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        .secondary-text {
            color: #6c757d;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        .page-divider {
            height: 1px;
            background-color: #dee2e6;
            margin: 20px 0;
        }
        .navbar-custom {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            height: 450px; /* Fixed height for the container */
        }
        .chart-wrapper {
            position: relative;
            height: 350px; /* Fixed height for the chart */
            width: 100%;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
            .chart-container {
                height: 400px;
            }
            .chart-wrapper {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <?php
    // Start session and check authentication
    session_start();
    
    // For testing purposes, create a mock session if none exists
    if (empty($_SESSION['user_id'])) {
        // Comment out the redirect for testing
        // header("Location: login.php");
        // exit();
        
        // Set a mock session for testing
        $_SESSION['user_id'] = 1;
        $_SESSION['first_name'] = 'Admin';
    }

    // Database configuration
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'trinity');

    // Connect to database
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Test connection
        $pdo->query("SELECT 1");
        
    } catch (PDOException $e) {
        // For debugging - show the actual error
        echo "<div style='background:red;color:white;padding:10px;margin:20px;'>Database connection failed: " . $e->getMessage() . "</div>";
        
        // Set default values to prevent further errors
        $pdo = null;
    }

    // Get dashboard statistics
    $users_count = 0;
    $tracks_count = 0;
    $tickets_count = 0;
    $releases_count = 0;
    $artists_count = 0;
    $royalties_amount = 0;

    if ($pdo) {
        try {
            // Get total users count
            $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
            $users_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
            
            // Get total tracks count
            $stmt = $pdo->query("SELECT COUNT(*) as total_tracks FROM tracks");
            $tracks_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_tracks'];
            
            // Get total tickets count
            $stmt = $pdo->query("SELECT COUNT(*) as total_tickets FROM help_tickets");
            $tickets_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_tickets'];
            
            // Get total releases count
            $stmt = $pdo->query("SELECT COUNT(*) as total_releases FROM releases");
            $releases_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_releases'];
            
            // Get total artists count
            $stmt = $pdo->query("SELECT COUNT(*) as total_artists FROM artists");
            $artists_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_artists'];
            
            // Get total royalties amount
            $stmt = $pdo->query("SELECT SUM(amount) as total_royalties FROM royalties WHERE status = 'paid'");
            $royalties_result = $stmt->fetch(PDO::FETCH_ASSOC);
            $royalties_amount = $royalties_result['total_royalties'] ?? 0;
            
        } catch (PDOException $e) {
            // Log error but don't break the page
            error_log("Database query error: " . $e->getMessage());
        }
    }

    // Get chart data for the last 30 days
    $chart_data = [];
    if ($pdo) {
        try {
            $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
            
            // User registrations by day
            $stmt = $pdo->prepare("
                SELECT DATE(created_at) as day, COUNT(*) as user_count 
                FROM users 
                WHERE created_at >= :thirty_days_ago 
                GROUP BY DATE(created_at)
                ORDER BY day
            ");
            $stmt->execute(['thirty_days_ago' => $thirty_days_ago]);
            $user_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Release submissions by day
            $stmt = $pdo->prepare("
                SELECT DATE(created_at) as day, COUNT(*) as release_count 
                FROM releases 
                WHERE created_at >= :thirty_days_ago 
                GROUP BY DATE(created_at)
                ORDER BY day
            ");
            $stmt->execute(['thirty_days_ago' => $thirty_days_ago]);
            $release_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ticket submissions by day
            $stmt = $pdo->prepare("
                SELECT DATE(created_at) as day, COUNT(*) as ticket_count 
                FROM help_tickets 
                WHERE created_at >= :thirty_days_ago 
                GROUP BY DATE(created_at)
                ORDER BY day
            ");
            $stmt->execute(['thirty_days_ago' => $thirty_days_ago]);
            $ticket_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Generate all dates for the last 30 days
            for ($i = 29; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $formatted_date = date('M j', strtotime($date));
                $chart_data[$date] = [
                    'user' => 0,
                    'releases' => 0,
                    'tickets' => 0,
                    'day' => $formatted_date
                ];
            }
            
            // Fill user data
            foreach ($user_registrations as $registration) {
                $day = $registration['day'];
                if (isset($chart_data[$day])) {
                    $chart_data[$day]['user'] = (int)$registration['user_count'];
                }
            }
            
            // Fill release data
            foreach ($release_submissions as $submission) {
                $day = $submission['day'];
                if (isset($chart_data[$day])) {
                    $chart_data[$day]['releases'] = (int)$submission['release_count'];
                }
            }
            
            // Fill ticket data
            foreach ($ticket_submissions as $submission) {
                $day = $submission['day'];
                if (isset($chart_data[$day])) {
                    $chart_data[$day]['tickets'] = (int)$submission['ticket_count'];
                }
            }
            
            // Convert to array for JSON response
            $chart_data = array_values($chart_data);
            
        } catch (PDOException $e) {
            error_log("Chart data query error: " . $e->getMessage());
            // Create empty chart data
            for ($i = 29; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $formatted_date = date('M j', strtotime($date));
                $chart_data[] = [
                    'user' => 0,
                    'releases' => 0,
                    'tickets' => 0,
                    'day' => $formatted_date
                ];
            }
        }
    } else {
        // Create empty chart data when no database connection
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $formatted_date = date('M j', strtotime($date));
            $chart_data[] = [
                'user' => 0,
                'releases' => 0,
                'tickets' => 0,
                'day' => $formatted_date
            ];
        }
    }
    ?>

    <div class="sidebar">
        <div class="logo-section">
            <h1 class="logo-text">trinity</h1>
            <p class="logo-subtitle">DISTRIBUTION</p>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="songs.php">
                    <i class="fas fa-music"></i> All Songs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="royalty.php">
                    <i class="fas fa-hand-holding-usd"></i> Royalty Request
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="ticket.php">
                    <i class="fas fa-comments"></i> Messaging
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users"></i> Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="administration.php">
                    <i class="fas fa-user-shield"></i> Administrators
                </a>
            </li>

        </ul>
    </div>

    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-custom mb-4">
            <div class="container-fluid">
                <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="d-flex align-items-center ms-auto">
                    <span class="me-3 d-none d-md-block">Welcome, <?php echo isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'Admin'; ?></span>
                    <img src="images/admin.jpg" width="80" height="80" class="rounded-circle" >
                </div>
            </div>
        </nav>

        <!-- Page Label -->
        <div class="page-label">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="page-label-heading text-danger">Dashboard</h3>
                    <p class="page-label-subheading text-danger">Welcome admin</p> 
                </div>
            </div>
        </div>

        <div class="container p-3">
            <!-- Dashboard Analysis -->
            <div class="board-analysis">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="analysis-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="no-gap secondary-text">Users</p>
                                <h4 class="no-gap card-value text-danger mb-0"><?php echo number_format($users_count); ?></h4>
                            </div>
                            <div class="analysis-icon text-danger">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                    <div class="analysis-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="no-gap secondary-text">Tracks</p>
                                <h4 class="no-gap card-value text-danger mb-0"><?php echo number_format($tracks_count); ?></h4>
                            </div>
                            <div class="analysis-icon text-danger">
                                <i class="fas fa-music"></i>
                            </div>
                        </div>
                    </div>
                    <div class="analysis-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="no-gap secondary-text">Tickets</p>
                                <h4 class="no-gap card-value text-danger mb-0"><?php echo number_format($tickets_count); ?></h4>
                            </div>
                            <div class="analysis-icon text-danger">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                        </div>
                    </div>
                    <div class="analysis-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="no-gap secondary-text">Releases</p>
                                <h4 class="no-gap card-value text-danger mb-0"><?php echo number_format($releases_count); ?></h4>
                            </div>
                            <div class="analysis-icon text-danger">
                                <i class="fas fa-compact-disc"></i>
                            </div>
                        </div>
                    </div>
                    <div class="analysis-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="no-gap secondary-text">Artists</p>
                                <h4 class="no-gap card-value text-danger mb-0"><?php echo number_format($artists_count); ?></h4>
                            </div>
                            <div class="analysis-icon text-danger">
                                <i class="fas fa-microphone"></i>
                            </div>
                        </div>
                    </div>
                    <div class="analysis-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="no-gap secondary-text">Royalties</p>
                                <h4 class="no-gap card-value text-danger mb-0">$<?php echo number_format($royalties_amount, 2); ?></h4>
                            </div>
                            <div class="analysis-icon text-danger">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="page-divider mt-3"></div>
            </div>

            <!-- Chart Section -->
            <div class="row mt-4">
                <div class="col">
                    <div class="chart-container">
                        <h6 class="primary-text text-center mb-3">Users & Submissions (Last 30 Days)</h6>
                        <div class="chart-wrapper">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart data from PHP
            const chartData = <?php echo json_encode($chart_data); ?>;
            
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
                    maintainAspectRatio: false, // This is key!
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
                    },
                    onClick: (e, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const datasetIndex = elements[0].datasetIndex;
                            const clickedData = {
                                index: index,
                                datasetIndex: datasetIndex,
                                data: chartData[index]
                            };
                            console.log('Chart clicked:', clickedData);
                        }
                    }
                }
            });

            // Toggle sidebar on mobile
            const sidebarToggler = document.querySelector('[data-bs-toggle="collapse"]');
            if (sidebarToggler) {
                sidebarToggler.addEventListener('click', function() {
                    const sidebar = document.querySelector('.sidebar');
                    sidebar.classList.toggle('show');
                });
            }
        });
    </script>
</body>
</html>