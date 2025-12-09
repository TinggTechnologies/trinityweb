<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and check authentication
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
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
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_user':
            $user_id = (int)($_POST['user_id'] ?? 0);
            $first_name = $_POST['first_name'] ?? '';
            $last_name = $_POST['last_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $stage_name = $_POST['stage_name'] ?? '';
            $mobile_number = $_POST['mobile_number'] ?? '';
            $origin_country = $_POST['origin_country'] ?? '';
            $residence_country = $_POST['residence_country'] ?? '';
            $artist_bio = $_POST['artist_bio'] ?? '';
            
            if (empty($first_name) || empty($last_name) || empty($email)) {
                $message = ['type' => 'danger', 'text' => 'First name, last name, and email are required'];
                break;
            }
            
            // Check if email already exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $message = ['type' => 'danger', 'text' => 'Email already exists for another user'];
                break;
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, stage_name = ?, mobile_number = ?, origin_country = ?, residence_country = ?, artist_bio = ?, updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$first_name, $last_name, $email, $stage_name, $mobile_number, $origin_country, $residence_country, $artist_bio, $user_id]);
                
                if ($result) {
                    $message = ['type' => 'success', 'text' => 'User updated successfully'];
                } else {
                    $message = ['type' => 'danger', 'text' => 'Failed to update user'];
                }
            } catch (Exception $e) {
                $message = ['type' => 'danger', 'text' => 'Error updating user: ' . $e->getMessage()];
            }
            break;
            
        case 'delete_user':
            $user_id = (int)($_POST['user_id'] ?? 0);
            
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Delete related records first (due to foreign key constraints)
                // Delete user social media
                $stmt = $pdo->prepare("DELETE FROM user_social_media WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete user images
                $stmt = $pdo->prepare("DELETE FROM user_images WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete help tickets
                $stmt = $pdo->prepare("DELETE FROM help_tickets WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete marketing campaigns
                $stmt = $pdo->prepare("DELETE FROM marketing_campaigns WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete royalties
                $stmt = $pdo->prepare("DELETE FROM royalties WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete payment methods
                $stmt = $pdo->prepare("DELETE FROM payment_methods WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete password reset tokens
                $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete social logins
                $stmt = $pdo->prepare("DELETE FROM social_logins WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete releases and related tracks
                $stmt = $pdo->prepare("SELECT id FROM releases WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($releases as $release) {
                    // Delete track-related data
                    $stmt = $pdo->prepare("SELECT id FROM tracks WHERE release_id = ?");
                    $stmt->execute([$release['id']]);
                    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($tracks as $track) {
                        // Delete analytics
                        $stmt = $pdo->prepare("DELETE FROM analytics WHERE track_id = ?");
                        $stmt->execute([$track['id']]);
                        
                        // Delete track artists
                        $stmt = $pdo->prepare("DELETE FROM track_artists WHERE track_id = ?");
                        $stmt->execute([$track['id']]);
                    }
                    
                    // Delete tracks
                    $stmt = $pdo->prepare("DELETE FROM tracks WHERE release_id = ?");
                    $stmt->execute([$release['id']]);
                }
                
                // Delete releases
                $stmt = $pdo->prepare("DELETE FROM releases WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Delete artists
                $stmt = $pdo->prepare("DELETE FROM artists WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Finally delete the user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                $pdo->commit();
                
                $message = ['type' => 'success', 'text' => 'User deleted successfully'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = ['type' => 'danger', 'text' => 'Error deleting user: ' . $e->getMessage()];
            }
            break;
            
        case 'bulk_delete':
            $user_ids = $_POST['user_ids'] ?? [];
            
            // Handle JSON encoded user_ids
            if (is_string($user_ids)) {
                $user_ids = json_decode($user_ids, true) ?: [];
            }
            
            if (empty($user_ids)) {
                $message = ['type' => 'danger', 'text' => 'No users selected'];
                break;
            }
            
            $pdo->beginTransaction();
            
            try {
                foreach ($user_ids as $user_id) {
                    // Delete related records for each user (simplified version)
                    $stmt = $pdo->prepare("DELETE FROM user_social_media WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM user_images WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM help_tickets WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM marketing_campaigns WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM royalties WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM payment_methods WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM social_logins WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM artists WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Delete releases and tracks (simplified)
                    $stmt = $pdo->prepare("DELETE FROM releases WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Delete the user
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                }
                
                $pdo->commit();
                
                $message = ['type' => 'success', 'text' => count($user_ids) . ' users deleted successfully'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = ['type' => 'danger', 'text' => 'Error deleting users: ' . $e->getMessage()];
            }
            break;
    }
}

// Get analytics data
$analytics_data = [];
try {
    // Get total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $analytics_data[] = ['label' => 'Total Users', 'value' => number_format($total_users), 'icon' => 'users'];
    
    // Get verified users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_verified = 1");
    $verified_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $analytics_data[] = ['label' => 'Verified Users', 'value' => number_format($verified_users), 'icon' => 'user-check'];
    
    // Get users with stage names (artists)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE stage_name IS NOT NULL AND stage_name != ''");
    $artists = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $analytics_data[] = ['label' => 'Artists', 'value' => number_format($artists), 'icon' => 'microphone'];
    
    // Get users registered this month
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $monthly_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $analytics_data[] = ['label' => 'This Month', 'value' => number_format($monthly_users), 'icon' => 'calendar'];
    
    // Get users with releases
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as count FROM releases");
    $users_with_releases = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $analytics_data[] = ['label' => 'With Releases', 'value' => number_format($users_with_releases), 'icon' => 'compact-disc'];
    
    // Get total countries
    $stmt = $pdo->query("SELECT COUNT(DISTINCT residence_country) as count FROM users WHERE residence_country IS NOT NULL AND residence_country != ''");
    $total_countries = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $analytics_data[] = ['label' => 'Countries', 'value' => number_format($total_countries), 'icon' => 'globe'];
} catch (PDOException $e) {
    // Silently fail - we'll show 0 values
}

// Get users data
$users = [];
try {
    $sql = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.stage_name,
            u.mobile_number,
            u.origin_country,
            u.residence_country,
            u.artist_bio,
            u.profile_image,
            u.created_at,
            u.updated_at,
            u.is_verified,
            COUNT(DISTINCT r.id) as total_releases,
            COUNT(DISTINCT pm.id) as payment_methods
        FROM users u
        LEFT JOIN releases r ON u.id = r.user_id
        LEFT JOIN payment_methods pm ON u.id = pm.user_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching users: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Trinity Distribution</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
        
        /* Logo styling to match dashboard */
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
        .custom-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .search-bar {
            position: relative;
            max-width: 300px;
        }
        .search-bar input {
            padding-left: 40px;
        }
        .search-bar .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .action-dropdown .dropdown-toggle {
            background: none;
            border: none;
            color: #6c757d;
        }
        .action-dropdown .dropdown-toggle:focus {
            box-shadow: none;
        }
        .user-row.selected {
            background-color: rgba(237, 50, 55, 0.1);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .user-name {
            font-weight: 500;
        }
        .user-email {
            font-size: 0.875rem;
            color: #6c757d;
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
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary-red) !important;
            border-color: var(--primary-red) !important;
            color: white !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary-red) !important;
            border-color: var(--primary-red) !important;
            color: white !important;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border" style="width: 3rem; height: 3rem; color: var(--primary-red);" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Toast container for notifications -->
    <div class="toast-container"></div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" id="editUserId" name="user_id">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editFirstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editLastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="editLastName" name="last_name" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="editEmail" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editStageName" class="form-label">Artist/Stage Name</label>
                                <input type="text" class="form-control" id="editStageName" name="stage_name">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editMobileNumber" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="editMobileNumber" name="mobile_number">
                            </div>
                            <div class="col-md-6">
                                <label for="editOriginCountry" class="form-label">Origin Country</label>
                                <input type="text" class="form-control" id="editOriginCountry" name="origin_country">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editResidenceCountry" class="form-label">Residence Country</label>
                                <input type="text" class="form-control" id="editResidenceCountry" name="residence_country">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="editArtistBio" class="form-label">Artist Bio</label>
                                <textarea class="form-control" id="editArtistBio" name="artist_bio" rows="4"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="saveUserChanges" style="background-color: var(--primary-red); border-color: var(--primary-red);">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="sidebar">
        <div class="logo-section">
            <h1 class="logo-text">trinity</h1>
            <p class="logo-subtitle">DISTRIBUTION</p>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
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
                <a class="nav-link active" href="users.php">
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
                    <span class="me-3 d-none d-md-block">Welcome, <?php echo isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : 'Admin'; ?></span>
                    <img src="images/admin.jpg" width="80" height="80" class="rounded-circle">
                </div>
            </div>
        </nav>

        <!-- Page Label -->
        <div class="page-label">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="page-label-heading text-danger">Users</h3>
                    <p class="page-label-subheading text-danger">Manage registered users and their information</p> 
                </div>
                <div>
                    <button class="btn btn-danger" onclick="bulkDelete()">
                        <i class="fas fa-trash me-1"></i> Delete Selected
                    </button>
                </div>
            </div>
        </div>

        <div class="container p-3">
            <!-- Display message if exists -->
            <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $message['text']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Error message display -->
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Dashboard Analysis -->
            <div class="board-analysis">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <?php foreach ($analytics_data as $item): ?>
                    <div class="analysis-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="no-gap secondary-text"><?php echo $item['label']; ?></p>
                                <h4 class="no-gap card-value text-danger mb-0"><?php echo $item['value']; ?></h4>
                            </div>
                            <div class="analysis-icon text-danger">
                                <i class="fas fa-<?php echo $item['icon']; ?>"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="page-divider mt-3"></div>
            </div>

            <!-- Users Table -->
            <div class="custom-card">
                <!-- Search and Filters -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h5>Registered Users</h5>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end gap-2">
                            <input type="date" class="form-control" id="startDate" placeholder="Start date">
                            <input type="date" class="form-control" id="endDate" placeholder="End date">
                            <div class="search-bar">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search users...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <div id="messageArea"></div>

                <!-- Users Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="usersTable">
                        <thead class="table-dark">
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Name</th>
                                <th>Artist Name</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th>Phone Number</th>
                                <th>Date Joined</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No users found in the database.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <?php
                            // Format address
                            $address = '';
                            if (!empty($user['residence_country'])) {
                                if (!empty($user['origin_country']) && $user['origin_country'] != $user['residence_country']) {
                                    $address = $user['residence_country'] . ' (from ' . $user['origin_country'] . ')';
                                } else {
                                    $address = $user['residence_country'];
                                }
                            } elseif (!empty($user['origin_country'])) {
                                $address = $user['origin_country'];
                            } else {
                                $address = 'Not specified';
                            }
                            
                            // Format date
                            $date_joined = $user['created_at'] ? date('M d, Y', strtotime($user['created_at'])) : 'N/A';
                            
                            // User display name
                            $full_name = trim($user['first_name'] . ' ' . $user['last_name']);
                            
                            // Profile image
                            $profile_img = !empty($user['profile_image']) ? 
                                $user['profile_image'] : 
                                'https://via.placeholder.com/40x40/ED3237/FFFFFF?text=' . substr($user['first_name'], 0, 1);
                            ?>
                            <tr class="user-row">
                                <td>
                                    <input type="checkbox" class="user-checkbox" value="<?php echo $user['id']; ?>">
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                       
                                        <div>
                                            <div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                            <?php if ($user['is_verified']): ?>
                                                <span class="badge bg-success status-badge">Verified</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning status-badge">Unverified</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($user['stage_name'])): ?>
                                        <span class="fw-bold text-danger"><?php echo htmlspecialchars($user['stage_name']); ?></span>
                                        <br><small class="text-muted"><?php echo $user['total_releases']; ?> release(s)</small>
                                    <?php else: ?>
                                        <span class="text-muted">Not an artist</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($address); ?></td>
                                <td><?php echo htmlspecialchars($user['mobile_number'] ?: 'Not provided'); ?></td>
                                <td><?php echo $date_joined; ?></td>
                                <td>
                                    <div class="dropdown action-dropdown">
                                        <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="viewUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-eye text-info me-2"></i>View Details
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="editUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-edit text-primary me-2"></i>Edit User
                                                </a>
                                            </li>
                                            <?php if (!$user['is_verified']): ?>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="verifyUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-check text-success me-2"></i>Verify User
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-trash me-2"></i>Delete User
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewUserModalLabel">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="userDetailsContent">
                        <!-- User details will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        let dataTable;
        let usersData = <?php echo json_encode($users); ?>;
        
        // Initialize the page
        $(document).ready(function() {
            // Initialize DataTable
            dataTable = $('#usersTable').DataTable({
                pageLength: 20,
                lengthMenu: [[20, 40, 50, 100], [20, 40, 50, 100]],
                order: [[6, 'desc']], // Order by date joined
                columnDefs: [
                    { orderable: false, targets: [0, -1] }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search users..."
                }
            });
            
            // Setup search functionality
            $('#searchInput').on('keyup', function() {
                dataTable.search(this.value).draw();
            });
            
            // Save user changes handler
            document.getElementById('saveUserChanges').addEventListener('click', saveUserChanges);
        });

        // Show loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        // Hide loading overlay
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            const toastContainer = document.querySelector('.toast-container');
            const toastId = 'toast-' + Date.now();
            
            const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            // Remove toast from DOM after it's hidden
            toastElement.addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        }

        // Toggle select all checkboxes
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.user-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            // Toggle row selection style
            const rows = document.querySelectorAll('#usersTable tbody tr');
            rows.forEach(row => {
                if (selectAll.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
        }

        // Get selected user IDs
        function getSelectedUsers() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            return Array.from(checkboxes).map(cb => parseInt(cb.value));
        }

        // View user details
        function viewUser(userId) {
            const user = usersData.find(u => u.id == userId);
            
            if (user) {
                const address = user.residence_country || user.origin_country || 'Not specified';
                const profileImg = user.profile_image || `https://via.placeholder.com/80x80/ED3237/FFFFFF?text=${user.first_name.charAt(0)}`;
                
                const userDetailsHTML = `
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <img src="${profileImg}" alt="Profile" class="img-fluid rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                            <h5>${user.first_name} ${user.last_name}</h5>
                            ${user.stage_name ? `<p class="text-danger fw-bold">"${user.stage_name}"</p>` : ''}
                            <span class="badge ${user.is_verified ? 'bg-success' : 'bg-warning'}">${user.is_verified ? 'Verified' : 'Unverified'}</span>
                        </div>
                        <div class="col-md-8">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Email:</th>
                                    <td>${user.email}</td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td>${user.mobile_number || 'Not provided'}</td>
                                </tr>
                                <tr>
                                    <th>Origin Country:</th>
                                    <td>${user.origin_country || 'Not specified'}</td>
                                </tr>
                                <tr>
                                    <th>Residence:</th>
                                    <td>${user.residence_country || 'Not specified'}</td>
                                </tr>
                                <tr>
                                    <th>Total Releases:</th>
                                    <td>${user.total_releases}</td>
                                </tr>
                                <tr>
                                    <th>Payment Methods:</th>
                                    <td>${user.payment_methods}</td>
                                </tr>
                                <tr>
                                    <th>Joined:</th>
                                    <td>${new Date(user.created_at).toLocaleDateString()}</td>
                                </tr>
                                <tr>
                                    <th>Last Updated:</th>
                                    <td>${new Date(user.updated_at).toLocaleDateString()}</td>
                                </tr>
                            </table>
                            ${user.artist_bio ? `
                                <div class="mt-3">
                                    <h6>Artist Bio:</h6>
                                    <p class="text-muted">${user.artist_bio}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                document.getElementById('userDetailsContent').innerHTML = userDetailsHTML;
                const viewModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
                viewModal.show();
            } else {
                showToast('User data not found', 'danger');
            }
        }

        // Edit user - open modal with user data
        function editUser(userId) {
            const user = usersData.find(u => u.id == userId);
            
            if (user) {
                // Populate the form with user data
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editFirstName').value = user.first_name || '';
                document.getElementById('editLastName').value = user.last_name || '';
                document.getElementById('editEmail').value = user.email || '';
                document.getElementById('editStageName').value = user.stage_name || '';
                document.getElementById('editMobileNumber').value = user.mobile_number || '';
                document.getElementById('editOriginCountry').value = user.origin_country || '';
                document.getElementById('editResidenceCountry').value = user.residence_country || '';
                document.getElementById('editArtistBio').value = user.artist_bio || '';
                
                // Show the modal
                const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                editModal.show();
            } else {
                showToast('User data not found', 'danger');
            }
        }

        // Save user changes
        function saveUserChanges() {
            // Get form values
            const userId = document.getElementById('editUserId').value;
            const firstName = document.getElementById('editFirstName').value.trim();
            const lastName = document.getElementById('editLastName').value.trim();
            const email = document.getElementById('editEmail').value.trim();
            const stageName = document.getElementById('editStageName').value.trim();
            const mobileNumber = document.getElementById('editMobileNumber').value.trim();
            const originCountry = document.getElementById('editOriginCountry').value.trim();
            const residenceCountry = document.getElementById('editResidenceCountry').value.trim();
            const artistBio = document.getElementById('editArtistBio').value.trim();
            
            if (!firstName || !lastName || !email) {
                showToast('First name, last name, and email are required', 'warning');
                return;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showToast('Please enter a valid email address', 'warning');
                return;
            }
            
            showLoading();
            
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'update_user';
            form.appendChild(actionInput);
            
            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = userId;
            form.appendChild(userIdInput);
            
            // Add all form fields
            const fields = [
                { name: 'first_name', value: firstName },
                { name: 'last_name', value: lastName },
                { name: 'email', value: email },
                { name: 'stage_name', value: stageName },
                { name: 'mobile_number', value: mobileNumber },
                { name: 'origin_country', value: originCountry },
                { name: 'residence_country', value: residenceCountry },
                { name: 'artist_bio', value: artistBio }
            ];
            
            fields.forEach(field => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = field.name;
                input.value = field.value;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }

        // Verify user
        function verifyUser(userId) {
            if (confirm('Are you sure you want to verify this user?')) {
                // You can implement user verification logic here
                showToast('User verification feature needs to be implemented', 'info');
            }
        }

        // Delete single user
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This will also delete all their releases, tracks, and related data. This action cannot be undone.')) {
                showLoading();
                
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_user';
                form.appendChild(actionInput);
                
                const userInput = document.createElement('input');
                userInput.type = 'hidden';
                userInput.name = 'user_id';
                userInput.value = userId;
                form.appendChild(userInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Bulk delete
        function bulkDelete() {
            const selectedUsers = getSelectedUsers();
            
            if (selectedUsers.length === 0) {
                showToast('Please select at least one user to delete', 'warning');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${selectedUsers.length} selected users? This will also delete all their releases, tracks, and related data. This action cannot be undone.`)) {
                showLoading();
                
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'bulk_delete';
                form.appendChild(actionInput);
                
                // Send as individual inputs
                selectedUsers.forEach((userId, index) => {
                    const userInput = document.createElement('input');
                    userInput.type = 'hidden';
                    userInput.name = `user_ids[${index}]`;
                    userInput.value = userId;
                    form.appendChild(userInput);
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Toggle row selection style when checkbox is clicked
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('user-checkbox')) {
                const row = e.target.closest('tr');
                if (e.target.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
                
                // Update select all checkbox state
                const selectAll = document.getElementById('selectAll');
                const checkboxes = document.querySelectorAll('.user-checkbox');
                const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
                
                if (checkedBoxes.length === 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                } else if (checkedBoxes.length === checkboxes.length) {
                    selectAll.checked = true;
                    selectAll.indeterminate = false;
                } else {
                    selectAll.checked = false;
                    selectAll.indeterminate = true;
                }
            }
        });

        // Date filtering functionality
        $('#startDate, #endDate').on('change', function() {
            dataTable.draw();
        });

        // Custom date range filter
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                var startDate = $('#startDate').val();
                var endDate = $('#endDate').val();
                var dateCol = data[6]; // Date column index

                if (!startDate && !endDate) {
                    return true;
                }

                var rowDate = new Date(dateCol);
                var start = startDate ? new Date(startDate) : null;
                var end = endDate ? new Date(endDate) : null;

                if (start && rowDate < start) {
                    return false;
                }
                if (end && rowDate > end) {
                    return false;
                }

                return true;
            }
        );
    </script>
</body>
</html>