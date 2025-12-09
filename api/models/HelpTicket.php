<?php
/**
 * HelpTicket Model
 * Handles help ticket database operations
 */

class HelpTicket {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create a new help ticket
     */
    public function create($userId, $upcCode, $subject, $message) {
        $stmt = $this->db->prepare("
            INSERT INTO help_tickets (user_id, subject, upc_code, message, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'open', NOW(), NOW())
        ");

        $stmt->execute([$userId, $subject, $upcCode, $message]);

        return [
            'id' => $this->db->lastInsertId(),
            'user_id' => $userId,
            'upc_code' => $upcCode,
            'subject' => $subject,
            'message' => $message,
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get all tickets for a user
     */
    public function getUserTickets($userId, $limit = 50, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT
                id,
                subject,
                upc_code,
                message,
                status,
                priority,
                created_at,
                updated_at
            FROM help_tickets
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get a single ticket by ID
     */
    public function getTicket($ticketId, $userId) {
        $stmt = $this->db->prepare("
            SELECT
                id,
                user_id,
                subject,
                upc_code,
                message,
                status,
                priority,
                created_at,
                updated_at
            FROM help_tickets
            WHERE id = ? AND user_id = ?
        ");

        $stmt->execute([$ticketId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get messages for a ticket
     */
    public function getMessages($ticketId) {
        $stmt = $this->db->prepare("
            SELECT
                sm.id,
                sm.sender_id,
                sm.sender_type,
                sm.message,
                sm.is_admin,
                sm.created_at,
                CASE
                    WHEN sm.is_admin = 1 THEN 'Admin'
                    ELSE CONCAT(u.first_name, ' ', u.last_name)
                END as sender_name
            FROM support_messages sm
            LEFT JOIN users u ON sm.sender_id = u.id
            WHERE sm.ticket_id = ?
            ORDER BY sm.created_at ASC
        ");

        $stmt->execute([$ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a message to a ticket
     */
    public function addMessage($ticketId, $senderId, $message, $isAdmin = false) {
        $senderType = $isAdmin ? 'Admin' : 'User';

        $stmt = $this->db->prepare("
            INSERT INTO support_messages (ticket_id, sender_id, sender_type, message, is_admin, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $result = $stmt->execute([$ticketId, $senderId, $senderType, $message, $isAdmin ? 1 : 0]);

        // Update ticket updated_at timestamp
        if ($result) {
            $updateStmt = $this->db->prepare("
                UPDATE help_tickets SET updated_at = NOW() WHERE id = ?
            ");
            $updateStmt->execute([$ticketId]);
        }

        return $result;
    }

    /**
     * Update ticket status
     */
    public function updateStatus($ticketId, $userId, $status) {
        $validStatuses = ['open', 'in_progress', 'resolved'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            UPDATE help_tickets
            SET status = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$status, $ticketId, $userId]);
    }
    
    /**
     * Delete a ticket
     */
    public function delete($ticketId, $userId) {
        $stmt = $this->db->prepare("
            DELETE FROM help_tickets
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$ticketId, $userId]);
    }
    
    /**
     * Get ticket count for a user
     */
    public function getUserTicketCount($userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM help_tickets
            WHERE user_id = ?
        ");
        
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    /**
     * Get ticket statistics for a user
     */
    public function getUserTicketStats($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
            FROM help_tickets
            WHERE user_id = ?
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

