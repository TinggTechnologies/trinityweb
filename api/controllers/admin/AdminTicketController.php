<?php
/**
 * Admin Ticket Controller
 * Handles support ticket management for admins
 */

class AdminTicketController {
    /**
     * Get all support tickets
     * GET /api/admin/tickets
     */
    public static function getTickets() {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = ($page - 1) * $limit;

        // Get analytics
        $analytics = [];

        // Total tickets
        $stmt = $db->query("SELECT COUNT(*) FROM help_tickets");
        $analytics['total_tickets'] = (int)$stmt->fetchColumn();

        // Open tickets
        $stmt = $db->query("SELECT COUNT(*) FROM help_tickets WHERE status = 'open'");
        $analytics['open_tickets'] = (int)$stmt->fetchColumn();

        // Closed tickets
        $stmt = $db->query("SELECT COUNT(*) FROM help_tickets WHERE status = 'closed'");
        $analytics['closed_tickets'] = (int)$stmt->fetchColumn();

        // Pending response (tickets with last message from user)
        $stmt = $db->query("
            SELECT COUNT(DISTINCT ht.id)
            FROM help_tickets ht
            INNER JOIN support_messages sm ON ht.id = sm.ticket_id
            WHERE ht.status = 'open'
            AND sm.id = (
                SELECT MAX(id) FROM support_messages WHERE ticket_id = ht.id
            )
            AND sm.is_admin = 0
        ");
        $analytics['pending_response'] = (int)$stmt->fetchColumn();

        // Get tickets with user info
        $stmt = $db->prepare("
            SELECT
                ht.id,
                ht.subject,
                ht.status,
                ht.created_at,
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.email,
                u.stage_name,
                COUNT(sm.id) as message_count,
                MAX(sm.created_at) as last_message_at
            FROM help_tickets ht
            INNER JOIN users u ON ht.user_id = u.id
            LEFT JOIN support_messages sm ON ht.id = sm.ticket_id
            GROUP BY ht.id
            ORDER BY ht.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $tickets = $stmt->fetchAll();

        Response::success([
            'tickets' => $tickets,
            'analytics' => $analytics,
            'pagination' => [
                'total' => $analytics['total_tickets'],
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($analytics['total_tickets'] / $limit)
            ]
        ]);
    }
    
    /**
     * Get single ticket with messages
     * GET /api/admin/tickets/{id}
     */
    public static function getTicket($id) {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        // Get ticket
        $stmt = $db->prepare("
            SELECT
                ht.*,
                u.first_name,
                u.last_name,
                u.email,
                u.stage_name
            FROM help_tickets ht
            INNER JOIN users u ON ht.user_id = u.id
            WHERE ht.id = ?
        ");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            Response::notFound('Ticket not found');
        }

        // Get messages - include initial message from help_tickets table
        $messages = [];

        // Add initial message from ticket
        $messages[] = [
            'id' => 0,
            'ticket_id' => $ticket['id'],
            'sender_type' => 'User',
            'is_admin' => 0,
            'message' => $ticket['message'],
            'created_at' => $ticket['created_at']
        ];

        // Get follow-up messages from support_messages
        $stmt = $db->prepare("
            SELECT
                sm.id,
                sm.ticket_id,
                CASE WHEN sm.is_admin = 1 THEN 'Admin' ELSE 'User' END as sender_type,
                sm.is_admin,
                sm.message,
                sm.created_at
            FROM support_messages sm
            WHERE sm.ticket_id = ?
            ORDER BY sm.created_at ASC
        ");
        $stmt->execute([$id]);
        $followUpMessages = $stmt->fetchAll();

        $messages = array_merge($messages, $followUpMessages);

        $ticket['messages'] = $messages;

        Response::success(['ticket' => $ticket]);
    }

    /**
     * Get ticket messages
     * GET /api/admin/tickets/{id}/messages
     */
    public static function getTicketMessages($id) {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        // Get ticket
        $stmt = $db->prepare("
            SELECT
                ht.id,
                ht.subject,
                ht.message,
                ht.status,
                ht.created_at,
                u.first_name,
                u.last_name,
                u.email,
                u.stage_name
            FROM help_tickets ht
            INNER JOIN users u ON ht.user_id = u.id
            WHERE ht.id = ?
        ");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            Response::notFound('Ticket not found');
        }

        // Get messages - include initial message from help_tickets table
        $messages = [];

        // Add initial message from ticket
        $messages[] = [
            'id' => 0,
            'ticket_id' => $ticket['id'],
            'sender_type' => 'User',
            'is_admin' => 0,
            'message' => $ticket['message'],
            'created_at' => $ticket['created_at']
        ];

        // Get follow-up messages from support_messages
        $stmt = $db->prepare("
            SELECT
                sm.id,
                sm.ticket_id,
                CASE WHEN sm.is_admin = 1 THEN 'Admin' ELSE 'User' END as sender_type,
                sm.is_admin,
                sm.message,
                sm.created_at
            FROM support_messages sm
            WHERE sm.ticket_id = ?
            ORDER BY sm.created_at ASC
        ");
        $stmt->execute([$id]);
        $followUpMessages = $stmt->fetchAll();

        $messages = array_merge($messages, $followUpMessages);

        Response::success([
            'ticket' => $ticket,
            'messages' => $messages
        ]);
    }
    
    /**
     * Reply to ticket
     * POST /api/admin/tickets/{id}/reply
     */
    public static function replyToTicket($id) {
        $userId = AdminAuthMiddleware::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['message'])) {
            Response::error('Message is required', 400);
        }

        $db = Database::getInstance()->getConnection();

        // Check if ticket exists
        $stmt = $db->prepare("SELECT * FROM help_tickets WHERE id = ?");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            Response::notFound('Ticket not found');
        }

        // Add message with admin as sender
        $stmt = $db->prepare("
            INSERT INTO support_messages (ticket_id, sender_id, sender_type, message, is_admin, created_at)
            VALUES (?, ?, 'Admin', ?, 1, NOW())
        ");
        $stmt->execute([$id, $userId, $data['message']]);

        // Update ticket status to 'in_progress' if it was 'open'
        if ($ticket['status'] === 'open') {
            $stmt = $db->prepare("UPDATE help_tickets SET status = 'in_progress', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
        }

        Response::success(null, 'Reply sent successfully', 201);
    }
    
    /**
     * Update ticket status
     * PUT /api/admin/tickets/{id}/status
     */
    public static function updateTicketStatus($id) {
        AdminAuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);

        // Map old status values to new ones
        $statusMap = [
            'Open' => 'open',
            'Closed' => 'closed',
            'open' => 'open',
            'closed' => 'closed',
            'in_progress' => 'in_progress',
            'resolved' => 'resolved'
        ];

        $status = $statusMap[$data['status']] ?? null;

        if (!$status || !in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
            Response::error('Invalid status', 400);
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("UPDATE help_tickets SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        Response::success(null, 'Ticket status updated successfully');
    }
    
    /**
     * Delete ticket
     * DELETE /api/admin/tickets/{id}
     */
    public static function deleteTicket($id) {
        AdminAuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();

        // Delete messages first (foreign key constraint)
        $stmt = $db->prepare("DELETE FROM support_messages WHERE ticket_id = ?");
        $stmt->execute([$id]);

        // Delete ticket
        $stmt = $db->prepare("DELETE FROM help_tickets WHERE id = ?");
        $stmt->execute([$id]);

        Response::success(null, 'Ticket deleted successfully');
    }
}

