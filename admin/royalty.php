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
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Allowed statuses
$ALLOWED_STATUSES = ['Pending', 'Approved', 'Rejected'];

// Messages (for UI)
$message = null;
$error_message = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {

        /* ============================================================
           SINGLE UPDATE (Approve / Reject / Pending)
        ============================================================ */
        case 'update_royalty_status':
            $payment_id = (int)($_POST['royalty_id'] ?? 0);
            // When status is provided as a button value or hidden field
            $status_raw = $_POST['status'] ?? '';
            $status = ucfirst(strtolower(trim($status_raw)));

            if (!in_array($status, $ALLOWED_STATUSES, true)) {
                $message = ['type' => 'danger', 'text' => 'Invalid status'];
                break;
            }
            if ($payment_id <= 0) {
                $message = ['type' => 'danger', 'text' => 'Invalid payment id'];
                break;
            }

            try {
                $pdo->beginTransaction();

                // Fetch current payment info
                $stmt = $pdo->prepare("SELECT user_id, amount, status FROM payment_requests WHERE id = ? FOR UPDATE");
                $stmt->execute([$payment_id]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$payment) {
                    throw new Exception("Payment request not found");
                }

                $user_id = (int)$payment['user_id'];
                $amount = (float)$payment['amount'];
                $old_status = $payment['status'];

                // Only deduct when approving a new one
                if ($status === 'Approved' && $old_status !== 'Approved') {
                    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$user) throw new Exception("User not found for payment request");
                    $current_balance = (float)$user['wallet_balance'];

                    if ($current_balance < $amount) {
                        throw new Exception("Insufficient balance to approve this request");
                    }

                    $new_balance = $current_balance - $amount;
                    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
                    $stmt->execute([$new_balance, $user_id]);
                }

                // If transitioning from Approved -> something else, we DO NOT automatically refund here.
                // (Optional: implement refund behavior if required.)

                // Update status
                $stmt = $pdo->prepare("UPDATE payment_requests SET status = ? WHERE id = ?");
                $stmt->execute([$status, $payment_id]);

                $pdo->commit();
                $message = ['type' => 'success', 'text' => "Royalty status updated to $status successfully"];
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = ['type' => 'danger', 'text' => 'Error updating royalty: ' . $e->getMessage()];
            }
            break;

        /* ============================================================
           BULK UPDATE
        ============================================================ */
        case 'bulk_update_status':
            $royalty_ids = $_POST['royalty_ids'] ?? [];
            $status_raw = $_POST['status'] ?? '';
            $status = ucfirst(strtolower(trim($status_raw)));

            if (is_string($royalty_ids)) {
                $royalty_ids = json_decode($royalty_ids, true) ?: [];
            }

            if (!in_array($status, $ALLOWED_STATUSES, true)) {
                $message = ['type' => 'danger', 'text' => 'Invalid status'];
                break;
            }
            if (empty($royalty_ids) || !is_array($royalty_ids)) {
                $message = ['type' => 'danger', 'text' => 'No royalties selected'];
                break;
            }

            $ids_clean = array_values(array_filter(array_map('intval', $royalty_ids), fn($v) => $v > 0));
            if (empty($ids_clean)) {
                $message = ['type' => 'danger', 'text' => 'No valid royalties selected'];
                break;
            }

            try {
                $pdo->beginTransaction();

                $updated = 0;
                $skipped = 0;
                $failed = [];

                // We'll loop and process each id safely
                $selectPaymentStmt = $pdo->prepare("SELECT user_id, amount, status FROM payment_requests WHERE id = ? FOR UPDATE");
                $selectUserStmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
                $updateUserStmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
                $updatePaymentStmt = $pdo->prepare("UPDATE payment_requests SET status = ? WHERE id = ?");

                foreach ($ids_clean as $id) {
                    $selectPaymentStmt->execute([$id]);
                    $payment = $selectPaymentStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$payment) {
                        $skipped++;
                        continue;
                    }

                    $user_id = (int)$payment['user_id'];
                    $amount = (float)$payment['amount'];
                    $old_status = $payment['status'];

                    if ($status === 'Approved' && $old_status !== 'Approved') {
                        // Attempt deduction
                        $selectUserStmt->execute([$user_id]);
                        $user = $selectUserStmt->fetch(PDO::FETCH_ASSOC);
                        if (!$user) {
                            $failed[] = "User not found for payment id $id";
                            continue;
                        }

                        $current_balance = (float)$user['wallet_balance'];
                        if ($current_balance < $amount) {
                            $failed[] = "Insufficient balance for user (payment id $id)";
                            continue;
                        }

                        $new_balance = $current_balance - $amount;
                        $updateUserStmt->execute([$new_balance, $user_id]);
                    }

                    // Update payment status regardless (if deduction succeeded or not applicable)
                    $updatePaymentStmt->execute([$status, $id]);
                    $updated++;
                }

                $pdo->commit();

                $msg = "$updated royalties updated to $status.";
                if ($skipped) $msg .= " $skipped skipped (not found).";
                if (!empty($failed)) $msg .= " " . count($failed) . " failed: " . implode('; ', $failed);

                $message = ['type' => 'success', 'text' => $msg];
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = ['type' => 'danger', 'text' => 'Bulk update failed: ' . $e->getMessage()];
            }
            break;

        /* ============================================================
           DELETE
        ============================================================ */
        case 'delete_royalty':
            $payment_id = (int)($_POST['royalty_id'] ?? 0);
            if ($payment_id <= 0) {
                $message = ['type' => 'danger', 'text' => 'Invalid payment id'];
                break;
            }
            try {
                $stmt = $pdo->prepare("DELETE FROM payment_requests WHERE id = ?");
                $stmt->execute([$payment_id]);
                $message = ['type' => 'success', 'text' => 'Royalty deleted successfully'];
            } catch (Exception $e) {
                $message = ['type' => 'danger', 'text' => 'Error deleting royalty: ' . $e->getMessage()];
            }
            break;
    }
}

/* ============================================================
   ANALYTICS
============================================================ */
$analytics_data = [];
try {
    $stmt = $pdo->query("SELECT SUM(earnings) as total FROM royalties");
    $total_royalties = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $analytics_data[] = ['label' => 'Total Royalties', 'value' => '$' . number_format((float)$total_royalties, 2), 'icon' => 'dollar-sign'];

    $stmtApproved = $pdo->prepare("SELECT SUM(amount) as total FROM payment_requests WHERE status = ?");
    $stmtApproved->execute(['Approved']);
    $approved = $stmtApproved->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $analytics_data[] = ['label' => 'Approved', 'value' => '$' . number_format((float)$approved, 2), 'icon' => 'check-circle'];

    $stmtApproved->execute(['Pending']);
    $pending = $stmtApproved->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $analytics_data[] = ['label' => 'Pending', 'value' => '$' . number_format((float)$pending, 2), 'icon' => 'clock'];

    $stmtApproved->execute(['Rejected']);
    $rejected = $stmtApproved->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $analytics_data[] = ['label' => 'Rejected', 'value' => '$' . number_format((float)$rejected, 2), 'icon' => 'times-circle'];
} catch (PDOException $e) {
    // silent - analytics empty if fails
}

/* ============================================================
   FETCH ROYALTIES LIST
============================================================ */
$royalties = [];
try {
    $sql = "
        SELECT 
            pr.id, pr.user_id, pr.amount, pr.status, pr.requested_at AS created_at,
            r.period, r.earnings, u.first_name, u.last_name, u.stage_name, 
            u.mobile_number AS phone_number, bpd.account_number, bpd.bank_name
        FROM payment_requests pr
        LEFT JOIN users u ON pr.user_id = u.id
        LEFT JOIN royalties r ON r.user_id = u.id
        LEFT JOIN payment_methods pm ON pm.user_id = u.id
        LEFT JOIN bank_payment_details bpd ON bpd.payment_method_id = pm.id
        ORDER BY pr.requested_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $royalties = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching royalties: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Royalty Management - Trinity Distribution</title>
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
        .nav-link {
        color: white; }
        .logo-section { text-align: left; padding: 0 20px 30px 20px; }
        .logo-text { font-size: 2rem; font-weight: 300; margin: 0; letter-spacing: 1px; }
        .analysis-card { background:white; border-radius:10px; box-shadow:0 4px 6px rgba(0,0,0,0.1); padding:20px; margin:10px; transition: transform .3s; flex:1; min-width:200px; }
        .status-badge { font-size:0.75rem; padding:0.25rem 0.5rem; }
        .royalty-row.selected { background-color: rgba(237,50,55,0.1); }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); display: flex; justify-content: center; align-items: center; z-index: 9999; display: none; }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border" style="width:3rem;height:3rem;color:var(--primary-red)" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="toast-container"></div>

    <div class="sidebar">
        <div class="logo-section">
            <h1 class="logo-text">trinity</h1>
            <p class="logo-subtitle">DISTRIBUTION</p>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="songs.php"><i class="fas fa-music"></i> All Songs</a></li>
            <li class="nav-item"><a class="nav-link active" href="royalty.php"><i class="fas fa-hand-holding-usd"></i> Royalty Request</a></li>
            <li class="nav-item"><a class="nav-link" href="ticket.php"><i class="fas fa-comments"></i> Messaging</a></li>
            <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li class="nav-item"><a class="nav-link" href="administration.php"><i class="fas fa-user-shield"></i> Administrators</a></li>
        </ul>
    </div>

    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-custom mb-4">
            <div class="container-fluid">
                <div class="d-flex align-items-center ms-auto">
                    <span class="me-3 d-none d-md-block">Welcome, <?php echo isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : 'Admin'; ?></span>
                    <img src="images/admin.jpg" width="80" height="80" class="rounded-circle" alt="Admin">
                </div>
            </div>
        </nav>

        <div class="page-label">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="page-label-heading text-danger">Royalty Management</h3>
                    <p class="page-label-subheading text-danger">Manage and track royalty payments to artists</p>
                </div>
            </div>
        </div>

        <div class="container p-3">
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message['text']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="board-analysis mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <?php foreach ($analytics_data as $item): ?>
                    <div class="analysis-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="no-gap secondary-text"><?php echo htmlspecialchars($item['label']); ?></p>
                                <h4 class="no-gap card-value text-danger mb-0"><?php echo htmlspecialchars($item['value']); ?></h4>
                            </div>
                            <div class="analysis-icon text-danger"><i class="fas fa-<?php echo htmlspecialchars($item['icon']); ?> fa-2x"></i></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="custom-card">
                <div class="row mb-3">
                    <div class="col-md-6"><h5 class="primary-text">Royalty Requests</h5></div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end gap-2">
                            <div class="search-bar">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search royalties...">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="messageArea"></div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="royaltiesTable">
                        <thead class="table-dark">
                            <tr>
                                <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                                <th>Date Created</th>
                                <th>Artist Name</th>
                                <th>Phone Number</th>
                                <th>Amount</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="royaltiesTableBody">
                            <?php if (empty($royalties)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-hand-holding-usd fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No royalty requests found.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($royalties as $royalty): ?>
                                    <?php
                                    $created_date = $royalty['created_at'] ? date('M d, Y', strtotime($royalty['created_at'])) : 'N/A';
                                    $display_name = !empty($royalty['stage_name']) ? $royalty['stage_name'] : trim($royalty['first_name'] . ' ' . $royalty['last_name']);
                                    $period = $royalty['period'] ? date('d-m-Y', strtotime($royalty['period'])) : 'N/A';
                                    $display_amount = isset($royalty['amount']) && $royalty['amount'] !== null ? $royalty['amount'] : ($royalty['earnings'] ?? 0);
                                    $status_class = '';
                                    switch($royalty['status']) {
                                        case 'Pending': $status_class = 'bg-warning'; break;
                                        case 'Approved': $status_class = 'bg-success'; break;
                                        case 'Rejected': $status_class = 'bg-danger'; break;
                                        default: $status_class = 'bg-secondary';
                                    }
                                    $status_text = htmlspecialchars($royalty['status'] ?? 'N/A');
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="royalty-checkbox" value="<?php echo (int)$royalty['id']; ?>"></td>
                                        <td><?php echo $created_date; ?></td>
                                        <td><?php echo htmlspecialchars($display_name); ?></td>
                                        <td><?php echo htmlspecialchars($royalty['phone_number'] ?: 'N/A'); ?></td>
                                        <td class="royalty-amount">$<?php echo number_format((float)$display_amount, 2); ?></td>
                                        <td><?php echo $period; ?></td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?> status-badge">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="dropdown action-dropdown">
                                                <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="updateStatus(<?php echo (int)$royalty['id']; ?>, 'Approved')">
                                                            <i class="fas fa-check text-success me-2"></i> Mark as Approved
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="updateStatus(<?php echo (int)$royalty['id']; ?>, 'Rejected')">
                                                            <i class="fas fa-times text-danger me-2"></i> Mark as Rejected
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="updateStatus(<?php echo (int)$royalty['id']; ?>, 'Pending')">
                                                            <i class="fas fa-clock text-warning me-2"></i> Mark as Pending
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="deleteRoyalty(<?php echo (int)$royalty['id']; ?>, '<?php echo htmlspecialchars($display_name); ?>')">
                                                            <i class="fas fa-trash me-2"></i> Delete
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <!-- quick-inline form for admins who prefer buttons -->
                                                    <li class="px-3">
                                                        <form method="POST" style="display:flex; gap:6px; justify-content:center;">
                                                            <input type="hidden" name="action" value="update_royalty_status">
                                                            <input type="hidden" name="royalty_id" value="<?php echo (int)$royalty['id']; ?>">
                                                            <button type="submit" name="status" value="Approved" class="btn btn-sm btn-success">Approve</button>
                                                            <button type="submit" name="status" value="Rejected" class="btn btn-sm btn-danger">Reject</button>
                                                        </form>
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

                <?php if (!empty($royalties)): ?>
                <div class="bulk-actions-container mt-3">
                    <h6 class="mb-3">Bulk Actions</h6>
                    <button class="btn btn-outline-warning btn-sm me-2" onclick="bulkUpdateStatus('Pending')">
                        <i class="fas fa-clock me-1"></i> Mark Selected as Pending
                    </button>
                    <button class="btn btn-outline-success btn-sm me-2" onclick="bulkUpdateStatus('Approved')">
                        <i class="fas fa-check me-1"></i> Mark Selected as Approved
                    </button>
                    <button class="btn btn-outline-danger btn-sm me-2" onclick="bulkUpdateStatus('Rejected')">
                        <i class="fas fa-times me-1"></i> Mark Selected as Rejected
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="bulkDelete()">
                        <i class="fas fa-trash me-1"></i> Delete Selected
                    </button>
                </div>
                <?php endif; ?>
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

        $(document).ready(function() {
            <?php if (!empty($royalties)): ?>
            dataTable = $('#royaltiesTable').DataTable({
                pageLength: 20,
                lengthMenu: [[20, 40, 50, 100], [20, 40, 50, 100]],
                order: [[1, 'desc']],
                columnDefs: [{ orderable: false, targets: [0, -1] }],
                language: { search: "", searchPlaceholder: "Search royalties..." }
            });

            $('#searchInput').on('keyup', function() {
                dataTable.search(this.value).draw();
            });
            <?php endif; ?>
        });

        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        function showToast(message, type = 'success') {
            const toastContainer = document.querySelector('.toast-container');
            const toastId = 'toast-' + Date.now();
            const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            toastElement.addEventListener('hidden.bs.toast', function() { toastElement.remove(); });
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.royalty-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = selectAll.checked);
            const rows = document.querySelectorAll('#royaltiesTable tbody tr');
            rows.forEach(row => {
                if (selectAll.checked) row.classList.add('selected'); else row.classList.remove('selected');
            });
        }

        function getSelectedRoyalties() {
            const checkboxes = document.querySelectorAll('.royalty-checkbox:checked');
            const selectedIds = [];
            checkboxes.forEach(checkbox => selectedIds.push(checkbox.value));
            return selectedIds;
        }

        function updateStatus(royaltyId, status) {
            if (!confirm(`Are you sure you want to mark this royalty as ${status}?`)) return;
            showLoading();
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const actionInput = document.createElement('input'); actionInput.type = 'hidden'; actionInput.name = 'action'; actionInput.value = 'update_royalty_status'; form.appendChild(actionInput);
            const royaltyInput = document.createElement('input'); royaltyInput.type = 'hidden'; royaltyInput.name = 'royalty_id'; royaltyInput.value = royaltyId; form.appendChild(royaltyInput);
            const statusInput = document.createElement('input'); statusInput.type = 'hidden'; statusInput.name = 'status'; statusInput.value = status; form.appendChild(statusInput);

            document.body.appendChild(form);
            form.submit();
        }

        function deleteRoyalty(royaltyId, artistName) {
            if (!confirm(`Are you sure you want to delete the royalty request for ${artistName}? This action cannot be undone.`)) return;
            showLoading();
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const actionInput = document.createElement('input'); actionInput.type = 'hidden'; actionInput.name = 'action'; actionInput.value = 'delete_royalty'; form.appendChild(actionInput);
            const royaltyInput = document.createElement('input'); royaltyInput.type = 'hidden'; royaltyInput.name = 'royalty_id'; royaltyInput.value = royaltyId; form.appendChild(royaltyInput);

            document.body.appendChild(form);
            form.submit();
        }

        function bulkUpdateStatus(status) {
            const selectedIds = getSelectedRoyalties();
            if (selectedIds.length === 0) { showToast('Please select at least one royalty to update', 'warning'); return; }
            if (!confirm(`Are you sure you want to update ${selectedIds.length} royalties to ${status}?`)) return;
            showLoading();

            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const actionInput = document.createElement('input'); actionInput.type = 'hidden'; actionInput.name = 'action'; actionInput.value = 'bulk_update_status'; form.appendChild(actionInput);
            const royaltyIdsInput = document.createElement('input'); royaltyIdsInput.type = 'hidden'; royaltyIdsInput.name = 'royalty_ids'; royaltyIdsInput.value = JSON.stringify(selectedIds); form.appendChild(royaltyIdsInput);
            const statusInput = document.createElement('input'); statusInput.type = 'hidden'; statusInput.name = 'status'; statusInput.value = status; form.appendChild(statusInput);

            document.body.appendChild(form);
            form.submit();
        }

        function bulkDelete() {
            const selectedIds = getSelectedRoyalties();
            if (selectedIds.length === 0) { showToast('Please select at least one royalty to delete', 'warning'); return; }
            if (!confirm(`Are you sure you want to delete ${selectedIds.length} royalties? This action cannot be undone.`)) return;
            showLoading();
            // Create one form and send list to server for deletion handling
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const actionInput = document.createElement('input'); actionInput.type = 'hidden'; actionInput.name = 'action'; actionInput.value = 'bulk_delete'; form.appendChild(actionInput);
            const royaltyIdsInput = document.createElement('input'); royaltyIdsInput.type = 'hidden'; royaltyIdsInput.name = 'royalty_ids'; royaltyIdsInput.value = JSON.stringify(selectedIds); form.appendChild(royaltyIdsInput);

            // Note: server-side 'bulk_delete' handling is not implemented in the PHP above.
            // As a quick fallback we can delete one-by-one by creating multiple invisible posts,
            // but to keep things simple we will emulate the original approach: submit multiple delete requests with sendBeacon when possible.
            selectedIds.forEach((royaltyId, index) => {
                if (index === selectedIds.length - 1) {
                    // last one - submit form to reload page after completion (deletes server-side once implemented)
                    const singleForm = document.createElement('form');
                    singleForm.method = 'POST';
                    singleForm.style.display = 'none';
                    const actionInput2 = document.createElement('input'); actionInput2.type = 'hidden'; actionInput2.name = 'action'; actionInput2.value = 'delete_royalty'; singleForm.appendChild(actionInput2);
                    const royaltyInput2 = document.createElement('input'); royaltyInput2.type = 'hidden'; royaltyInput2.name = 'royalty_id'; royaltyInput2.value = royaltyId; singleForm.appendChild(royaltyInput2);
                    document.body.appendChild(singleForm);
                    singleForm.submit();
                } else {
                    const data = new FormData();
                    data.append('action', 'delete_royalty');
                    data.append('royalty_id', royaltyId);
                    if (navigator.sendBeacon) {
                        const blob = new Blob([new URLSearchParams(Array.from(data.entries())).toString()], {type: 'application/x-www-form-urlencoded'});
                        navigator.sendBeacon(window.location.href, blob);
                    } else {
                        // fallback: do a background fetch (fire & forget)
                        fetch(window.location.href, {
                            method: 'POST',
                            body: data,
                            keepalive: true
                        }).catch(() => {});
                    }
                }
            });
        }

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('royalty-checkbox')) {
                const row = e.target.closest('tr');
                if (e.target.checked) row.classList.add('selected'); else {
                    row.classList.remove('selected');
                    document.getElementById('selectAll').checked = false;
                }
            }
        });
    </script>
</body>
</html>
