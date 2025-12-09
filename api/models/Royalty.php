<?php
/**
 * Royalty Model
 * Handles royalty and payment request operations
 */

class Royalty {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get user's current balance
     */
    public function getCurrentBalance($userId) {
        $stmt = $this->db->prepare("SELECT SUM(closing_balance) AS total_balance FROM royalties WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?? 0.00;
    }
    
    /**
     * Get royalty history with split share deductions breakdown
     */
    public function getHistory($userId) {
        $stmt = $this->db->prepare("SELECT * FROM royalties WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $royalties = $stmt->fetchAll();

        // Add split share deductions breakdown for each period
        foreach ($royalties as &$royalty) {
            $royalty['split_share_deductions_breakdown'] = $this->getSplitShareDeductionsBreakdown($userId, $royalty['earnings']);
        }

        return $royalties;
    }

    /**
     * Get split share deductions breakdown for display
     * Returns array of split share details with calculated amounts
     */
    public function getSplitShareDeductionsBreakdown($userId, $earnings) {
        // Get all accepted split shares for releases owned by this user
        $stmt = $this->db->prepare("
            SELECT
                ss.id,
                ss.release_id,
                ss.collaborator_email,
                ss.collaborator_name,
                ss.percentage,
                r.release_title
            FROM split_shares ss
            INNER JOIN releases r ON ss.release_id = r.id
            WHERE r.user_id = ?
            AND ss.status = 'accepted'
            ORDER BY ss.created_at DESC
        ");
        $stmt->execute([$userId]);
        $splitShares = $stmt->fetchAll();

        // Calculate deductions based on earnings
        $deductions = [];
        foreach ($splitShares as $split) {
            $amount = ($earnings * floatval($split['percentage'])) / 100;
            $deductions[] = [
                'release_title' => $split['release_title'],
                'invitee_name' => $split['collaborator_name'],
                'invitee_email' => $split['collaborator_email'],
                'split_percentage' => $split['percentage'],
                'amount' => $amount
            ];
        }

        return $deductions;
    }
    
    /**
     * Create payment request
     */
    public function createPaymentRequest($userId, $amount) {
        $stmt = $this->db->prepare("INSERT INTO payment_requests (user_id, amount, status) VALUES (?, ?, 'Pending')");
        return $stmt->execute([$userId, $amount]);
    }

    /**
     * Get payment requests
     */
    public function getPaymentRequests($userId) {
        $stmt = $this->db->prepare("SELECT * FROM payment_requests WHERE user_id = ? ORDER BY requested_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get latest payment request
     */
    public function getLatestPaymentRequest($userId) {
        $stmt = $this->db->prepare("SELECT * FROM payment_requests WHERE user_id = ? ORDER BY requested_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
}

