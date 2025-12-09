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

// Create necessary tables if they don't exist
try {
    // Create admin_roles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_roles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(100) NOT NULL,
            privileges TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create administrators table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS administrators (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            role_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (role_id) REFERENCES admin_roles(id),
            UNIQUE KEY unique_user_admin (user_id)
        )
    ");
    
    // Insert default admin roles if they don't exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_roles");
    $role_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($role_count == 0) {
        $default_roles = [
            ['Super Administrator', 'Full system access'],
            ['Media Personnel', 'Access to media management'],
            ['Support Staff', 'Access to user support and tickets'],
            ['Content Moderator', 'Access to content moderation']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO admin_roles (title, privileges) VALUES (?, ?)");
        foreach ($default_roles as $role) {
            $stmt->execute([$role[0], $role[1]]);
        }
    }
} catch (PDOException $e) {
    // Silently continue - tables might already exist
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_admin':
            $user_id = (int)($_POST['user_id'] ?? 0);
            $role_id = (int)($_POST['role_id'] ?? 0);
            
            if (empty($user_id) || empty($role_id)) {
                $message = ['type' => 'danger', 'text' => 'Please select both a user and a role'];
                break;
            }
            
            // Check if user is already an admin
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM administrators WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $admin_exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($admin_exists > 0) {
                $message = ['type' => 'danger', 'text' => 'This user is already an administrator'];
                break;
            }
            
            // Create new administrator
            $stmt = $pdo->prepare("INSERT INTO administrators (user_id, role_id, created_at) VALUES (?, ?, NOW())");
            $result = $stmt->execute([$user_id, $role_id]);
            
            if ($result) {
                $message = ['type' => 'success', 'text' => 'Administrator created successfully'];
            } else {
                $message = ['type' => 'danger', 'text' => 'Failed to create administrator'];
            }
            break;
            
        case 'update_admin':
            $admin_id = (int)($_POST['admin_id'] ?? 0);
            $role_id = (int)($_POST['role_id'] ?? 0);
            
            if (empty($admin_id) || empty($role_id)) {
                $message = ['type' => 'danger', 'text' => 'Invalid parameters'];
                break;
            }
            
            $stmt = $pdo->prepare("UPDATE administrators SET role_id = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$role_id, $admin_id]);
            
            if ($result) {
                $message = ['type' => 'success', 'text' => 'Administrator updated successfully'];
            } else {
                $message = ['type' => 'danger', 'text' => 'Failed to update administrator'];
            }
            break;
            
        case 'delete_admin':
            $admin_id = (int)($_POST['admin_id'] ?? 0);
            
            if (empty($admin_id)) {
                $message = ['type' => 'danger', 'text' => 'Invalid administrator ID'];
                break;
            }
            
            $stmt = $pdo->prepare("DELETE FROM administrators WHERE id = ?");
            $result = $stmt->execute([$admin_id]);
            
            if ($result) {
                $message = ['type' => 'success', 'text' => 'Administrator deleted successfully'];
            } else {
                $message = ['type' => 'danger', 'text' => 'Failed to delete administrator'];
            }
            break;
    }
}

// Get analytics data
$analytics_data = [];
try {
    // Get total administrators
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM administrators");
    $total_admins = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $analytics_data[] = ['label' => 'Total Administrators', 'value' => number_format($total_admins), 'icon' => 'user-shield'];
    
    // Get total admin roles
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_roles");
    $total_roles = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $analytics_data[] = ['label' => 'Admin Roles', 'value' => number_format($total_roles), 'icon' => 'key'];
    
    // Get total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $analytics_data[] = ['label' => 'Total Users', 'value' => number_format($total_users), 'icon' => 'users'];
    
    // Get active administrators (users who are admins and are verified)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT a.id) as count FROM administrators a 
                         INNER JOIN users u ON a.user_id = u.id 
                         WHERE u.is_verified = 1");
    $active_admins = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $analytics_data[] = ['label' => 'Active Admins', 'value' => number_format($active_admins), 'icon' => 'user-check'];
} catch (PDOException $e) {
    // Silently fail - we'll show 0 values
}

// Get administrators data
$administrators = [];
try {
    $sql = "
        SELECT 
            a.id as admin_id,
            a.created_at,
            u.id as user_id,
            u.first_name,
            u.last_name,
            u.mobile_number as phone_number,
            u.email,
            u.stage_name,
            ar.id as role_id,
            ar.title as role_title
        FROM administrators a
        INNER JOIN users u ON a.user_id = u.id
        INNER JOIN admin_roles ar ON a.role_id = ar.id
        ORDER BY a.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $administrators = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching administrators: " . $e->getMessage();
}

// Get available users for creating new admins (non-admin users)
$available_users = [];
try {
    $sql = "
        SELECT u.id, u.first_name, u.last_name, u.stage_name, u.mobile_number as phone_number
        FROM users u
        WHERE u.id NOT IN (SELECT user_id FROM administrators)
        AND u.is_verified = 1
        ORDER BY u.first_name, u.last_name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $available_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silently fail
}

// Get admin roles
$admin_roles = [];
try {
    $sql = "SELECT id, title FROM admin_roles ORDER BY title";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $admin_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silently fail
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrators - Trinity Distribution</title>
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
        .admin-row.selected {
            background-color: rgba(237, 50, 55, 0.1);
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

    <!-- Create Admin Modal -->
    <div class="modal fade" id="createAdminModal" tabindex="-1" aria-labelledby="createAdminModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createAdminModalLabel">Create Administrator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createAdminForm">
                        <div class="mb-3">
                            <label for="createMember" class="form-label">Choose User</label>
                            <select class="form-select" id="createMember" name="user_id" required>
                                <option value="">Select a user</option>
                                <?php if (!empty($available_users)): ?>
                                    <?php foreach ($available_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php 
                                        $display_name = '';
                                        if (!empty($user['stage_name'])) {
                                            $display_name = $user['stage_name'];
                                        } else {
                                            $display_name = trim($user['first_name'] . ' ' . $user['last_name']);
                                        }
                                        echo htmlspecialchars($display_name) . ' (' . htmlspecialchars($user['phone_number'] ?? 'N/A') . ')';
                                        ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No available users</option>
                                <?php endif; ?>
                            </select>
                            <?php if (empty($available_users)): ?>
                            <div class="form-text text-warning">All verified users are already administrators.</div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="createRole" class="form-label">Assign Role</label>
                            <select class="form-select" id="createRole" name="role_id" required>
                                <option value="">Select a role</option>
                                <?php foreach ($admin_roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="createAdminButton" style="background-color: var(--primary-red); border-color: var(--primary-red);" <?php echo empty($available_users) ? 'disabled' : ''; ?>>Create Administrator</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAdminModalLabel">Update Administrator Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAdminForm">
                        <input type="hidden" id="editAdminId" name="admin_id">
                        <div class="mb-3">
                            <label class="form-label">Administrator</label>
                            <p class="form-control-static" id="editAdminName"></p>
                        </div>
                        <div class="mb-3">
                            <label for="editRole" class="form-label">Assign Role</label>
                            <select class="form-select" id="editRole" name="role_id" required>
                                <option value="">Select a role</option>
                                <?php foreach ($admin_roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="updateAdminButton" style="background-color: var(--primary-red); border-color: var(--primary-red);">Update Role</button>
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
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users"></i> Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="administration.php">
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
                    <h3 class="page-label-heading text-danger">Administrators</h3>
                    <p class="page-label-subheading text-danger">Manage system administrators and their roles</p> 
                </div>
                <div>
                    <button class="btn me-2" data-bs-toggle="modal" data-bs-target="#createAdminModal" style="background-color: var(--primary-red); border-color: var(--primary-red); color: white;">
                        <i class="fas fa-plus me-1"></i> Create Admin
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

            <!-- Administrators Table -->
            <div class="custom-card">
                <!-- Search and Filters -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h5 class="primary-text">Administrators List</h5>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end gap-2">
                            <div class="search-bar">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search administrators...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <div id="messageArea"></div>

                <!-- Administrators Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="adminsTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Date Added</th>
                                <th>Name</th>
                                <th>Phone Number</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="adminsTableBody">
                            <?php if (empty($administrators)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <i class="fas fa-user-shield fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No administrators found.</p>
                                    <button class="btn btn-danger mt-2" data-bs-toggle="modal" data-bs-target="#createAdminModal">
                                        <i class="fas fa-plus me-1"></i> Create First Administrator
                                    </button>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($administrators as $admin): ?>
                            <?php
                            // Format date
                            $created_date = $admin['created_at'] ? date('M d, Y', strtotime($admin['created_at'])) : 'N/A';
                            
                            // Determine display name
                            $display_name = '';
                            if (!empty($admin['stage_name'])) {
                                $display_name = $admin['stage_name'];
                            } else {
                                $display_name = trim($admin['first_name'] . ' ' . $admin['last_name']);
                            }
                            ?>
                            <tr>
                                <td><?php echo $created_date; ?></td>
                                <td><?php echo htmlspecialchars($display_name); ?></td>
                                <td><?php echo htmlspecialchars($admin['phone_number'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-primary status-badge">
                                        <?php echo htmlspecialchars($admin['role_title']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown action-dropdown">
                                        <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="editAdmin(<?php echo $admin['admin_id']; ?>, '<?php echo htmlspecialchars($display_name); ?>', <?php echo $admin['role_id']; ?>)">
                                                    <i class="fas fa-edit text-primary me-2"></i>Update Role
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" onclick="deleteAdmin(<?php echo $admin['admin_id']; ?>, '<?php echo htmlspecialchars($display_name); ?>')">
                                                    <i class="fas fa-trash me-2"></i>Remove Admin
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        let dataTable;
        
        // Initialize the page
        $(document).ready(function() {
            // Initialize DataTable if we have data
            <?php if (!empty($administrators)): ?>
            dataTable = $('#adminsTable').DataTable({
                pageLength: 20,
                lengthMenu: [[20, 40, 50, 100], [20, 40, 50, 100]],
                order: [[0, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [-1] }
                ],
                language: {
                    search: "",
                    searchPlaceholder: "Search administrators..."
                }
            });
            
            // Setup search functionality
            $('#searchInput').on('keyup', function() {
                dataTable.search(this.value).draw();
            });
            <?php endif; ?>
            
            // Create admin button handler
            document.getElementById('createAdminButton').addEventListener('click', createAdmin);
            
            // Update admin button handler
            document.getElementById('updateAdminButton').addEventListener('click', updateAdmin);
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

        // Create new administrator
        function createAdmin() {
            const memberSelect = document.getElementById('createMember');
            const roleSelect = document.getElementById('createRole');
            
            if (!memberSelect.value || !roleSelect.value) {
                showToast('Please select both a user and a role', 'warning');
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
            actionInput.value = 'create_admin';
            form.appendChild(actionInput);
            
            const memberInput = document.createElement('input');
            memberInput.type = 'hidden';
            memberInput.name = 'user_id';
            memberInput.value = memberSelect.value;
            form.appendChild(memberInput);
            
            const roleInput = document.createElement('input');
            roleInput.type = 'hidden';
            roleInput.name = 'role_id';
            roleInput.value = roleSelect.value;
            form.appendChild(roleInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        // Edit administrator - open modal with admin data
        function editAdmin(adminId, adminName, roleId) {
            // Populate the form with admin data
            document.getElementById('editAdminId').value = adminId;
            document.getElementById('editAdminName').textContent = adminName;
            document.getElementById('editRole').value = roleId;
            
            // Show the modal
            const editModal = new bootstrap.Modal(document.getElementById('editAdminModal'));
            editModal.show();
        }

        // Update administrator role
        function updateAdmin() {
            const adminId = document.getElementById('editAdminId').value;
            const roleId = document.getElementById('editRole').value;
            
            if (!adminId || !roleId) {
                showToast('Please select a role', 'warning');
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
            actionInput.value = 'update_admin';
            form.appendChild(actionInput);
            
            const adminInput = document.createElement('input');
            adminInput.type = 'hidden';
            adminInput.name = 'admin_id';
            adminInput.value = adminId;
            form.appendChild(adminInput);
            
            const roleInput = document.createElement('input');
            roleInput.type = 'hidden';
            roleInput.name = 'role_id';
            roleInput.value = roleId;
            form.appendChild(roleInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        // Delete administrator
        function deleteAdmin(adminId, adminName) {
            if (confirm(`Are you sure you want to remove ${adminName} as an administrator?`)) {
                showLoading();
                
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_admin';
                form.appendChild(actionInput);
                
                const adminInput = document.createElement('input');
                adminInput.type = 'hidden';
                adminInput.name = 'admin_id';
                adminInput.value = adminId;
                form.appendChild(adminInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>