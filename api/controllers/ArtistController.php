<?php
require_once __DIR__ . '/../models/Artist.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ArtistController {
    
    /**
     * Get all artists for the authenticated user
     * GET /api/artists
     */
    public static function getAll() {
        $userId = AuthMiddleware::authenticate();
        
        $artistModel = new Artist();
        $artists = $artistModel->getByUserId($userId);
        
        Response::success(['artists' => $artists]);
    }
    
    /**
     * Search artists by name
     * GET /api/artists/search?q=query
     */
    public static function search() {
        $userId = AuthMiddleware::authenticate();
        
        $query = $_GET['q'] ?? '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        
        $artistModel = new Artist();
        $artists = $artistModel->search($userId, $query, $limit);
        
        Response::success(['artists' => $artists]);
    }
    
    /**
     * Create a new artist
     * POST /api/artists
     */
    public static function create() {
        $userId = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name'])) {
            Response::error('Artist name is required', 400);
        }
        
        $name = trim($data['name']);
        
        if (strlen($name) < 1) {
            Response::error('Artist name must be at least 1 character', 400);
        }
        
        $artistModel = new Artist();
        
        // Check if artist already exists
        $existing = $artistModel->findByName($userId, $name);
        if ($existing) {
            Response::success(['artist' => $existing], 'Artist already exists');
        }
        
        $isPrimary = isset($data['is_primary']) ? (bool)$data['is_primary'] : false;
        $artist = $artistModel->create($userId, $name, $isPrimary);
        
        Response::success(['artist' => $artist], 'Artist created successfully', 201);
    }
    
    /**
     * Update an artist
     * PUT /api/artists/{id}
     */
    public static function update($id) {
        $userId = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name'])) {
            Response::error('Artist name is required', 400);
        }
        
        $artistModel = new Artist();
        
        // Verify artist belongs to user
        $artist = $artistModel->getById($id);
        if (!$artist || $artist['user_id'] != $userId) {
            Response::error('Artist not found', 404);
        }
        
        $success = $artistModel->update($id, $userId, $data['name']);
        
        if ($success) {
            Response::success(['id' => $id, 'name' => $data['name']], 'Artist updated successfully');
        } else {
            Response::error('Failed to update artist', 500);
        }
    }
    
    /**
     * Delete an artist
     * DELETE /api/artists/{id}
     */
    public static function delete($id) {
        $userId = AuthMiddleware::authenticate();
        
        $artistModel = new Artist();
        
        // Verify artist belongs to user
        $artist = $artistModel->getById($id);
        if (!$artist || $artist['user_id'] != $userId) {
            Response::error('Artist not found', 404);
        }
        
        $success = $artistModel->delete($id, $userId);
        
        if ($success) {
            Response::success(null, 'Artist deleted successfully');
        } else {
            Response::error('Failed to delete artist', 500);
        }
    }
}

