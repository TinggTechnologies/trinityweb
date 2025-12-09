<?php
/**
 * Admin Model
 * Handles admin-related database operations
 */

class Admin {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Check if a user is an admin
     */
    public function isAdmin($userId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM administrators WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get admin details by user ID
     */
    public function getByUserId($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                a.id as admin_id,
                a.user_id,
                a.role_id,
                a.created_at,
                a.updated_at,
                ar.title as role_title,
                ar.privileges,
                u.first_name,
                u.last_name,
                u.email,
                u.stage_name,
                u.mobile_number
            FROM administrators a
            INNER JOIN admin_roles ar ON a.role_id = ar.id
            INNER JOIN users u ON a.user_id = u.id
            WHERE a.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Get all administrators
     */
    public function getAll() {
        $stmt = $this->db->prepare("
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
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get all admin roles
     */
    public function getRoles() {
        $stmt = $this->db->prepare("SELECT * FROM admin_roles ORDER BY title ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Create a new administrator
     */
    public function create($userId, $roleId) {
        // Check if user is already an admin
        if ($this->isAdmin($userId)) {
            throw new Exception('User is already an administrator');
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO administrators (user_id, role_id, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
        ");
        $stmt->execute([$userId, $roleId]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Update administrator role
     */
    public function updateRole($adminId, $roleId) {
        $stmt = $this->db->prepare("
            UPDATE administrators 
            SET role_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$roleId, $adminId]);
    }
    
    /**
     * Delete administrator
     */
    public function delete($adminId) {
        $stmt = $this->db->prepare("DELETE FROM administrators WHERE id = ?");
        return $stmt->execute([$adminId]);
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        $stats = [];
        
        // Total users
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        $stats['total_users'] = $stmt->fetchColumn();
        
        // Total releases
        $stmt = $this->db->query("SELECT COUNT(*) FROM releases");
        $stats['total_releases'] = $stmt->fetchColumn();
        
        // Total tracks
        $stmt = $this->db->query("SELECT COUNT(*) FROM tracks");
        $stats['total_tracks'] = $stmt->fetchColumn();
        
        // Pending releases
        $stmt = $this->db->query("SELECT COUNT(*) FROM releases WHERE status = 'pending'");
        $stats['pending_releases'] = $stmt->fetchColumn();
        
        // Open tickets
        $stmt = $this->db->query("SELECT COUNT(*) FROM help_tickets WHERE status = 'open'");
        $stats['open_tickets'] = $stmt->fetchColumn();

        // Total artists
        $stmt = $this->db->query("SELECT COUNT(*) FROM artists");
        $stats['total_artists'] = $stmt->fetchColumn();

        // Total royalties
        $stmt = $this->db->query("SELECT SUM(closing_balance) FROM royalties");
        $stats['total_royalties'] = $stmt->fetchColumn() ?? 0;

        return $stats;
    }
}

