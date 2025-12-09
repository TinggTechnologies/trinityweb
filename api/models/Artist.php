<?php
require_once __DIR__ . '/../config/Database.php';

class Artist {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Search artists by name for a specific user
     */
    public function search($userId, $query = '', $limit = 20) {
        $sql = "SELECT id, name FROM artists WHERE user_id = ?";
        $params = [$userId];
        
        if (!empty($query)) {
            $sql .= " AND name LIKE ?";
            $params[] = '%' . $query . '%';
        }
        
        $sql .= " ORDER BY name ASC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all artists for a user
     */
    public function getByUserId($userId) {
        $stmt = $this->db->prepare("
            SELECT id, name, is_primary 
            FROM artists 
            WHERE user_id = ? 
            ORDER BY name ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get artist by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM artists WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find artist by name for a user
     */
    public function findByName($userId, $name) {
        $stmt = $this->db->prepare("
            SELECT * FROM artists 
            WHERE user_id = ? AND LOWER(name) = LOWER(?)
        ");
        $stmt->execute([$userId, $name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new artist
     */
    public function create($userId, $name, $isPrimary = false) {
        // Check if artist already exists for this user
        $existing = $this->findByName($userId, $name);
        if ($existing) {
            return $existing;
        }

        $stmt = $this->db->prepare("
            INSERT INTO artists (user_id, name, is_primary) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, trim($name), $isPrimary ? 1 : 0]);
        
        return [
            'id' => $this->db->lastInsertId(),
            'name' => trim($name),
            'user_id' => $userId,
            'is_primary' => $isPrimary
        ];
    }

    /**
     * Update artist name
     */
    public function update($id, $userId, $name) {
        $stmt = $this->db->prepare("
            UPDATE artists SET name = ? 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([trim($name), $id, $userId]);
    }

    /**
     * Delete an artist
     */
    public function delete($id, $userId) {
        $stmt = $this->db->prepare("DELETE FROM artists WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }
}

