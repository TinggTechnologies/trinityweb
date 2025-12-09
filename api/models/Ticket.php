<?php
/**
 * Ticket Model
 * Handles support ticket operations
 */

class Ticket {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create new ticket
     */
    public function create($userId, $subject, $message) {
        $this->db->beginTransaction();
        
        try {
            // Create ticket
            $stmt = $this->db->prepare("INSERT INTO support_tickets (user_id, subject, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$userId, $subject]);
            $ticketId = $this->db->lastInsertId();
            
            // Add first message
            $stmt = $this->db->prepare("INSERT INTO support_messages (ticket_id, sender_type, message, created_at) VALUES (?, 'User', ?, NOW())");
            $stmt->execute([$ticketId, $message]);
            
            $this->db->commit();
            return $ticketId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get ticket by ID
     */
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM support_tickets WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get all tickets for user
     */
    public function getByUserId($userId) {
        $stmt = $this->db->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get ticket messages
     */
    public function getMessages($ticketId) {
        $stmt = $this->db->prepare("SELECT * FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Add message to ticket
     */
    public function addMessage($ticketId, $message, $senderType = 'User') {
        $stmt = $this->db->prepare("INSERT INTO support_messages (ticket_id, sender_type, message, created_at) VALUES (?, ?, ?, NOW())");
        return $stmt->execute([$ticketId, $senderType, $message]);
    }
    
    /**
     * Update ticket status
     */
    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
}

