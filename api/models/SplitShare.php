<?php
require_once __DIR__ . '/../config/Database.php';

class SplitShare {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create a new split share invitation
     */
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO split_shares (
                release_id, collaborator_email, collaborator_name, percentage, token, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $token = bin2hex(random_bytes(32));

        $stmt->execute([
            $data['release_id'],
            $data['invitee_email'],
            $data['collaborator_name'] ?? $data['invitee_email'],
            $data['split_percentage'],
            $token
        ]);

        return [
            'id' => $this->db->lastInsertId(),
            'token' => $token
        ];
    }

    /**
     * Get all split shares for a release
     */
    public function getByReleaseId($releaseId) {
        $stmt = $this->db->prepare("
            SELECT
                id,
                release_id,
                collaborator_email as invitee_email,
                user_id,
                collaborator_name,
                percentage as split_percentage,
                status,
                token,
                created_at,
                updated_at
            FROM split_shares
            WHERE release_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$releaseId]);
        return $stmt->fetchAll();
    }

    /**
     * Get split share by ID
     */
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM split_shares WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Get split share by token
     */
    public function findByToken($token) {
        $stmt = $this->db->prepare("SELECT * FROM split_shares WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    /**
     * Update split share status
     */
    public function updateStatus($id, $status, $userId = null) {
        if ($userId) {
            $stmt = $this->db->prepare("
                UPDATE split_shares
                SET status = ?, user_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $userId, $id]);
        } else {
            $stmt = $this->db->prepare("
                UPDATE split_shares
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $id]);
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if email already has a pending invitation for this release
     */
    public function hasPendingInvitation($releaseId, $email) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM split_shares
            WHERE release_id = ? AND collaborator_email = ? AND status = 'pending'
        ");
        $stmt->execute([$releaseId, $email]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    /**
     * Get total split percentage for a release (includes pending and accepted)
     */
    public function getTotalSplitPercentage($releaseId) {
        $stmt = $this->db->prepare("
            SELECT SUM(percentage) as total
            FROM split_shares
            WHERE release_id = ? AND status IN ('pending', 'accepted', 'approved')
        ");
        $stmt->execute([$releaseId]);
        $result = $stmt->fetch();
        return floatval($result['total'] ?? 0);
    }

    /**
     * Delete split share
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM split_shares WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}

