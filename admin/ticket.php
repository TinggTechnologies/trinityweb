<?php
// support.php - User Support Tickets (UI matches admin template)
// Start session and bootstrap
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Authentication
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB config
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'trinity');

$adminEmail = 'admin@yourdomain.com'; // change to actual admin email

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

$user_id = (int)$_SESSION['user_id'];

// ---------------------------
// AJAX / POST endpoints
// ---------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create ticket
    if ($action === 'create_ticket') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$subject || !$message) {
            echo json_encode(['success' => false, 'message' => 'Subject and message are required.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, status) VALUES (?, ?, 'Open')");
            $stmt->execute([$user_id, $subject]);
            $ticket_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO support_messages (ticket_id, sender_type, message) VALUES (?, 'admin', ?)");
            $stmt->execute([$ticket_id, $message]);

            $pdo->commit();

            // notify admin by email
            $userEmailStmt = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
            $userEmailStmt->execute([$user_id]);
            $userInfo = $userEmailStmt->fetch(PDO::FETCH_ASSOC);
            $userName = ($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? '');

            $to = $adminEmail;
            $subjectEmail = "New Support Ticket #{$ticket_id}: {$subject}";
            $body = "New support ticket created by {$userName} ({$userInfo['email']}).\n\nSubject: {$subject}\nMessage:\n{$message}\n\nPlease log in to the admin panel to reply.";
            @mail($to, $subjectEmail, $body);

            echo json_encode(['success' => true, 'message' => 'Ticket created successfully.']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error creating ticket: ' . $e->getMessage()]);
            exit;
        }
    }

    // Send message (reply) as user
    if ($action === 'send_message') {
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if (!$ticket_id || !$message) {
            echo json_encode(['success' => false, 'message' => 'Invalid ticket or message.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO support_messages (ticket_id, sender_type, message) VALUES (?, 'admin', ?)");
            $stmt->execute([$ticket_id, $message]);

            // Email admin
            // fetch ticket & user info
            $tstmt = $pdo->prepare("SELECT subject, user_id FROM support_tickets WHERE id = ?");
            $tstmt->execute([$ticket_id]);
            $ticket = $tstmt->fetch(PDO::FETCH_ASSOC);

            $ustmt = $pdo->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
            $ustmt->execute([$user_id]);
            $u = $ustmt->fetch(PDO::FETCH_ASSOC);
            $userName = ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '');

            $to = $adminEmail;
            $subjectEmail = "Reply on Ticket #{$ticket_id}: {$ticket['subject']}";
            $body = "User {$userName} ({$u['email']}) replied to ticket #{$ticket_id}.\n\nMessage:\n{$message}\n\nLogin to admin panel to respond.";
            @mail($to, $subjectEmail, $body);

            echo json_encode(['success' => true, 'message' => 'Message sent.']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error sending message: ' . $e->getMessage()]);
            exit;
        }
    }
}

// ---------------------------
// GET endpoints for AJAX
// ---------------------------
if (isset($_GET['fetch_tickets']) && $_GET['fetch_tickets'] == '1') {
    // return user's tickets
    $stmt = $pdo->prepare("SELECT id, subject, status, created_at FROM support_tickets WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'tickets' => $tickets]);
    exit;
}

if (isset($_GET['fetch_messages']) && is_numeric($_GET['ticket_id'])) {
    $ticket_id = (int)$_GET['ticket_id'];

    // Verify ticket belongs to user
    $vstmt = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
    $vstmt->execute([$ticket_id, $user_id]);
    if (!$vstmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found or access denied.']);
        exit;
    }

    $mstmt = $pdo->prepare("SELECT id, sender_type, message, created_at FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC");
    $mstmt->execute([$ticket_id]);
    $messages = $mstmt->fetchAll(PDO::FETCH_ASSOC);

    // Also return ticket info
    $tstmt = $pdo->prepare("SELECT id, subject, status, created_at FROM support_tickets WHERE id = ?");
    $tstmt->execute([$ticket_id]);
    $ticket = $tstmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'ticket' => $ticket, 'messages' => $messages]);
    exit;
}

// ---------------------------
// Render initial HTML page
// ---------------------------

// fetch some analytics (reuse small snippets from admin template if desired)
$analytics_data = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM support_tickets WHERE user_id = {$user_id}");
    $openTickets = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $analytics_data[] = ['label' => 'Your Tickets', 'value' => number_format($openTickets), 'icon' => 'comments'];
} catch (PDOException $e) {
    // ignore
}

// fetch user info for display
$uStmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$uStmt->execute([$user_id]);
$userInfo = $uStmt->fetch(PDO::FETCH_ASSOC);
$displayName = trim(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? ''));
$userEmail = $userInfo['email'] ?? '';

// fetch tickets for the initial page (server-side fallback)
$ticketsStmt = $pdo->prepare("SELECT id, subject, status, created_at FROM support_tickets WHERE user_id = ? ORDER BY id DESC");
$ticketsStmt->execute([$user_id]);
$initialTickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Support Tickets | Trinity Distribution</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Bootstrap / icons / datatables CSS (matching admin template) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Use same style as admin template -->
    <style>
        :root {
            --primary-red: #ED3237;
            --secondary: #6c757d;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; }
        .sidebar { background: var(--primary-red); color: white; height: 100vh; position: fixed; width: 250px; padding-top: 20px; z-index: 1000; }
        .main-content { margin-left: 250px; padding: 20px; }
        .logo-section { text-align: left; padding: 0 20px 30px 20px; }
        .logo-text { font-size: 2rem; font-weight: 300; margin: 0; letter-spacing: 1px; }
        .logo-subtitle { font-size: 0.9rem; opacity: 0.8; margin: 0; font-weight: 300; }
        .nav-link { color: rgba(255,255,255,0.9); padding: 12px 20px; margin: 5px 0; border-radius: 5px; display:flex; align-items:center; }
        .nav-link:hover { background-color: rgba(255,255,255,0.1); color: white; }
        .nav-link.active { background-color: white; color: var(--primary-red); }
        .page-label { background-color: white; border-bottom: 1px solid #dee2e6; margin: -20px -20px 20px -20px; padding: 15px 20px; }
        .page-label-heading { font-size: 1.8rem; font-weight: 600; margin-bottom: 0.25rem; color: #333; }
        .page-label-subheading { color: #6c757d; margin-bottom: 0; }
        .custom-card { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        .royalty-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; width: 100%; }
        .analysis-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.08); margin: 10px; flex:1; min-width:200px; }
        .analysis-icon { font-size: 2rem; opacity: 0.7; color: var(--primary-red); }
        .no-gap { margin: 0; }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .loading-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(255,255,255,0.8); display:flex; justify-content:center; align-items:center; z-index:9999; display:none; }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border" style="width:3rem; height:3rem; color:var(--primary-red);" role="status"><span class="visually-hidden">Loading...</span></div>
    </div>

    <!-- Toast container -->
    <div class="toast-container"></div>

    <!-- Sidebar (same as admin template) -->
    <div class="sidebar">
        <div class="logo-section">
            <h1 class="logo-text">trinity</h1>
            <p class="logo-subtitle">DISTRIBUTION</p>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-th-large me-2"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="songs.php"><i class="fas fa-music me-2"></i> All Songs</a></li>
            <li class="nav-item"><a class="nav-link" href="royalty.php"><i class="fas fa-hand-holding-usd me-2"></i> Royalty Request</a></li>
            <li class="nav-item"><a class="nav-link active" href="ticket.php"><i class="fas fa-comments me-2"></i> Messaging</a></li>
            <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users me-2"></i> Users</a></li>
            <li class="nav-item"><a class="nav-link" href="administration.php"><i class="fas fa-users me-2"></i> Administration</a></li>
        </ul>
    </div>

    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-custom mb-4">
            <div class="container-fluid">
                <div class="d-flex align-items-center ms-auto">
                    <span class="me-3 d-none d-md-block">Welcome, <?php echo htmlspecialchars($displayName ?: 'User'); ?></span>
                    <img src="images/admin.jpg" width="80" height="80" class="rounded-circle" alt="avatar">
                </div>
            </div>
        </nav>

        <!-- Page label -->
        <div class="page-label">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="page-label-heading text-danger">Support Tickets</h3>
                    <p class="page-label-subheading text-danger">Create and view your support tickets. Admin will reply via the dashboard.</p>
                </div>
                <div>
                    <button class="btn btn-danger" id="newTicketBtn"><i class="fas fa-plus me-1"></i> New Ticket</button>
                </div>
            </div>
        </div>

        <div class="container p-3">
            <div class="board-analysis mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <?php foreach ($analytics_data as $item): ?>
                    <div class="analysis-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="no-gap secondary-text"><?php echo $item['label']; ?></p>
                                <h4 class="no-gap card-value text-danger mb-0"><?php echo $item['value']; ?></h4>
                            </div>
                            <div class="analysis-icon">
                                <i class="fas fa-<?php echo $item['icon']; ?>"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tickets table -->
            <div class="custom-card">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h5>Your Tickets</h5>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end gap-2">
                            <div class="search-bar" style="max-width:320px;">
                                <i class="fas fa-search search-icon" style="position:absolute; left:12px; top:12px; color:#6c757d;"></i>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search tickets...">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="messageArea"></div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="ticketsTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Ticket #</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="ticketsTableBody">
                            <?php if (empty($initialTickets)): ?>
                                <tr><td colspan="5" class="text-center py-4"><i class="fas fa-comments fa-3x text-muted mb-3"></i><p class="text-muted">No tickets found.</p></td></tr>
                            <?php else: ?>
                                <?php foreach ($initialTickets as $t): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($t['id']); ?></td>
                                        <td><?php echo htmlspecialchars($t['subject']); ?></td>
                                        <td>
                                            <?php if ($t['status'] === 'Open'): ?>
                                                <span class="badge bg-success">Open</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Closed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-dark viewTicketBtn" data-id="<?php echo $t['id']; ?>"><i class="fas fa-eye me-1"></i> View</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <footer class="bg-dark text-white text-center py-3 mt-4">© <?= date('Y') ?>. Developed and maintained by Trinity Distribution</footer>
    </div>

    <!-- New Ticket Modal -->
    <div class="modal fade" id="newTicketModal" tabindex="-1" aria-labelledby="newTicketModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="newTicketForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="newTicketModalLabel">Create New Support Ticket</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="newTicketAlert"></div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" id="ticketSubject" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" id="ticketMessage" class="form-control" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Submit Ticket</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Conversation Modal (thread) -->
    <div class="modal fade" id="viewConversationModal" tabindex="-1" aria-labelledby="viewConversationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conversation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="conversationAlert"></div>
                    <div class="mb-3">
                        <h5 id="convSubject"></h5>
                        <small id="convMeta" class="text-muted"></small>
                    </div>
                    <div id="conversationMessages" style="max-height:500px; overflow-y:auto; background:#fff; padding:10px; border-radius:6px; border:1px solid #eee;">
                        <!-- Messages will be appended here -->
                    </div>

                    <div class="mt-3">
                        <form id="replyForm">
                            <input type="hidden" id="replyTicketId" name="ticket_id" value="">
                            <div class="mb-3">
                                <label class="form-label">Your Reply</label>
                                <textarea id="replyMessage" name="message" class="form-control" rows="4" required></textarea>
                            </div>
                            <div id="replyAlert"></div>
                            <button type="submit" class="btn btn-danger">Send Reply</button>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <small class="text-muted me-auto">Replies are emailed to admin and stored here.</small>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JS: jQuery, Bootstrap, DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
    $(function() {
        const adminEmail = "<?php echo addslashes($adminEmail); ?>";

        // DataTable
        const ticketsTable = $('#ticketsTable').DataTable({
            pageLength: 10,
            order: [[3, 'desc']],
            columnDefs: [{ orderable: false, targets: 4 }]
        });

        // Show loading
        function showLoading() { $('#loadingOverlay').show(); }
        function hideLoading() { $('#loadingOverlay').hide(); }

        // Toast
        function showToast(message, type = 'success') {
            const toastId = 'toast' + Date.now();
            const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>`;
            $('.toast-container').append(toastHTML);
            const el = document.getElementById(toastId);
            const bsToast = new bootstrap.Toast(el);
            bsToast.show();
            el.addEventListener('hidden.bs.toast', function(){ el.remove(); });
        }

        // Open new ticket modal
        $('#newTicketBtn').on('click', function() {
            $('#ticketSubject').val('');
            $('#ticketMessage').val('');
            $('#newTicketAlert').html('');
            const modal = new bootstrap.Modal(document.getElementById('newTicketModal'));
            modal.show();
        });

        // Submit new ticket (AJAX)
        $('#newTicketForm').on('submit', function(e) {
            e.preventDefault();
            const subject = $('#ticketSubject').val().trim();
            const message = $('#ticketMessage').val().trim();
            if (!subject || !message) {
                $('#newTicketAlert').html('<div class="alert alert-warning">Subject and message required.</div>');
                return;
            }
            showLoading();
            $.post('ticket.php', { action: 'create_ticket', subject, message }, function(resp) {
                hideLoading();
                try {
                    const data = (typeof resp === 'string') ? JSON.parse(resp) : resp;
                    if (data.success) {
                        $('#newTicketModal').modal('hide');
                        showToast(data.message || 'Ticket created', 'success');
                        reloadTickets();
                    } else {
                        $('#newTicketAlert').html('<div class="alert alert-danger">' + (data.message || 'Error') + '</div>');
                    }
                } catch (err) {
                    $('#newTicketAlert').html('<div class="alert alert-danger">Unexpected response.</div>');
                }
            });
        });

        // Reload tickets list (AJAX)
        function reloadTickets() {
            showLoading();
            $.get('ticket.php?fetch_tickets=1', function(resp) {
                hideLoading();
                const data = (typeof resp === 'string') ? JSON.parse(resp) : resp;
                if (!data.success) return;
                ticketsTable.clear();
                data.tickets.forEach(t => {
                    const statusBadge = t.status === 'Open' ? '<span class="badge bg-success">Open</span>' : '<span class="badge bg-secondary">Closed</span>';
                    const rowNode = ticketsTable.row.add([
                        '#' + t.id,
                        $('<div>').text(t.subject).html(),
                        statusBadge,
                        (new Date(t.created_at)).toLocaleDateString(),
                        `<button class="btn btn-sm btn-dark viewTicketBtn" data-id="${t.id}"><i class="fas fa-eye me-1"></i> View</button>`
                    ]).draw(false).node();
                });
            });
        }

        // Click view ticket (delegated)
        $('#ticketsTable tbody').on('click', 'button.viewTicketBtn', function() {
            const ticketId = $(this).data('id');
            openConversation(ticketId);
        });

        // Function open conversation modal and load messages
        function openConversation(ticketId) {
            $('#conversationAlert').html('');
            $('#conversationMessages').html('');
            $('#replyMessage').val('');
            $('#replyTicketId').val(ticketId);
            showLoading();

            $.get('ticket.php', { fetch_messages: 1, ticket_id: ticketId }, function(resp) {
                hideLoading();
                const data = (typeof resp === 'string') ? JSON.parse(resp) : resp;
                if (!data.success) {
                    $('#conversationAlert').html('<div class="alert alert-danger">' + (data.message || 'Error loading conversation') + '</div>');
                    return;
                }
                $('#convSubject').text(data.ticket.subject);
                $('#convMeta').text('Created: ' + (new Date(data.ticket.created_at)).toLocaleString());

                // Append messages
                const messages = data.messages || [];
                const container = $('#conversationMessages');
                container.empty();
                messages.forEach(m => {
                    const isUser = m.sender_type === 'User';
                    const wrapper = $('<div>').addClass('mb-2 p-2 rounded');
                    if (isUser) {
                        wrapper.css({'background':'#f1f1f1','text-align':'left'});
                    } else {
                        wrapper.css({'background':'#dc3545','color':'#fff','text-align':'left'});
                    }
                    const meta = $('<div>').html('<small class="text-muted">' + m.sender_type + ' · ' + (new Date(m.created_at)).toLocaleString() + '</small>');
                    const body = $('<div>').css({'margin-top':'6px'}).text(m.message);
                    wrapper.append(meta).append(body);
                    container.append(wrapper);
                });

                // scroll to bottom
                container.scrollTop(container[0].scrollHeight);

                const modal = new bootstrap.Modal(document.getElementById('viewConversationModal'));
                modal.show();
            });
        }

        // Submit reply (AJAX)
        $('#replyForm').on('submit', function(e) {
            e.preventDefault();
            const ticketId = $('#replyTicketId').val();
            const message = $('#replyMessage').val().trim();
            if (!message) {
                $('#replyAlert').html('<div class="alert alert-warning">Please enter a message.</div>');
                return;
            }
            $('#replyAlert').html('');
            showLoading();
            $.post('ticket.php', { action: 'send_message', ticket_id: ticketId, message: message }, function(resp) {
                hideLoading();
                const data = (typeof resp === 'string') ? JSON.parse(resp) : resp;
                if (data.success) {
                    $('#replyMessage').val('');
                    showToast(data.message || 'Reply sent', 'success');
                    // reload conversation messages
                    setTimeout(function(){ openConversation(ticketId); }, 500);
                    // Also reload tickets list to ensure updated timestamps / states
                    reloadTickets();
                } else {
                    $('#replyAlert').html('<div class="alert alert-danger">' + (data.message || 'Error sending reply') + '</div>');
                }
            });
        });

        // Search input
        $('#searchInput').on('keyup', function() {
            ticketsTable.search(this.value).draw();
        });
    });
    </script>
</body>
</html>
