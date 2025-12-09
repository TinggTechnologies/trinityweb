<?php
/**
 * Admin Song Controller
 * Handles release/song management for admins
 */

class AdminSongController {
    /**
     * Get all releases
     * GET /api/admin/songs
     */
    public static function getReleases() {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // Get analytics data
        $analytics = [];

        // Total releases
        $stmt = $db->query("SELECT COUNT(*) FROM releases");
        $analytics['total_releases'] = $stmt->fetchColumn();

        // Draft releases
        $stmt = $db->query("SELECT COUNT(*) FROM releases WHERE status = 'draft'");
        $analytics['draft'] = $stmt->fetchColumn();

        // Pending releases
        $stmt = $db->query("SELECT COUNT(*) FROM releases WHERE status = 'pending'");
        $analytics['pending'] = $stmt->fetchColumn();

        // Approved releases
        $stmt = $db->query("SELECT COUNT(*) FROM releases WHERE status = 'approved'");
        $analytics['approved'] = $stmt->fetchColumn();

        // Live releases
        $stmt = $db->query("SELECT COUNT(*) FROM releases WHERE status = 'live'");
        $analytics['live'] = $stmt->fetchColumn();

        // Total users with releases
        $stmt = $db->query("SELECT COUNT(DISTINCT user_id) FROM releases");
        $analytics['total_users'] = $stmt->fetchColumn();

        // Get total count of releases
        $stmt = $db->query("SELECT COUNT(*) FROM releases");
        $total = $stmt->fetchColumn();

        // Get releases with user info, artists, track count, and store count
        $stmt = $db->prepare("
            SELECT
                r.*,
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.email,
                u.stage_name as user_stage_name,
                CONCAT(u.first_name, ' ', u.last_name) as user_name,
                GROUP_CONCAT(DISTINCT ra.stage_name SEPARATOR ', ') AS artists,
                COUNT(DISTINCT t.id) AS track_count,
                COUNT(DISTINCT rs.id) AS store_count,
                MIN(t.release_date) AS track_release_date
            FROM releases r
            INNER JOIN users u ON r.user_id = u.id
            LEFT JOIN release_artists ra ON r.id = ra.release_id
            LEFT JOIN tracks t ON r.id = t.release_id
            LEFT JOIN release_stores rs ON r.id = rs.release_id
            GROUP BY r.id
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $releases = $stmt->fetchAll();

        // Use track_release_date if release_date is null
        foreach ($releases as &$release) {
            if (empty($release['release_date']) && !empty($release['track_release_date'])) {
                $release['release_date'] = $release['track_release_date'];
            }
        }

        Response::success([
            'releases' => $releases,
            'analytics' => $analytics,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ], 'Releases retrieved successfully');
    }
    
    /**
     * Get single release with full details
     * GET /api/admin/songs/{id}
     */
    public static function getRelease($id) {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        // Get release with user info and artists
        $stmt = $db->prepare("
            SELECT
                r.*,
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.email,
                u.stage_name as user_stage_name,
                GROUP_CONCAT(DISTINCT ra.stage_name SEPARATOR ', ') AS artists
            FROM releases r
            INNER JOIN users u ON r.user_id = u.id
            LEFT JOIN release_artists ra ON r.id = ra.release_id
            WHERE r.id = ?
            GROUP BY r.id
        ");
        $stmt->execute([$id]);
        $release = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$release) {
            Response::notFound('Release not found');
        }

        // Structure user data
        $release['user'] = [
            'id' => $release['user_id'],
            'first_name' => $release['first_name'],
            'last_name' => $release['last_name'],
            'email' => $release['email'],
            'stage_name' => $release['user_stage_name']
        ];

        // Get tracks
        $trackModel = new Track();
        $release['tracks'] = $trackModel->getByReleaseId($id);

        Response::success(['release' => $release]);
    }

    /**
     * Update release
     * PUT /api/admin/songs/{id}
     */
    public static function updateRelease($id) {
        AdminAuthMiddleware::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);

        $db = Database::getInstance()->getConnection();

        // Get current release data to compare values
        $stmt = $db->prepare("SELECT id, upc, isrc FROM releases WHERE id = ?");
        $stmt->execute([$id]);
        $release = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$release) {
            Response::notFound('Release not found');
            return;
        }

        // Build update query dynamically
        $updates = [];
        $params = [];

        if (isset($data['release_title'])) {
            $updates[] = "release_title = ?";
            $params[] = $data['release_title'];
        }

        if (isset($data['release_date'])) {
            $updates[] = "release_date = ?";
            $params[] = $data['release_date'];
        }

        if (isset($data['status'])) {
            $validStatuses = ['draft', 'pending', 'approved', 'rejected', 'distributed', 'live', 'taken_down'];
            if (in_array($data['status'], $validStatuses)) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }
        }

        if (isset($data['upc']) || isset($data['upc_code'])) {
            $upcValue = $data['upc'] ?? $data['upc_code'];
            $currentUpc = $release['upc'] ?? '';

            // Only update if value changed
            if ($upcValue !== $currentUpc) {
                $updates[] = "upc = ?";
                $params[] = $upcValue;
                // Mark as set by admin only if admin actually changed the value
                if (!empty($upcValue)) {
                    $updates[] = "upc_set_by_admin = 1";
                }
            }
        }

        if (isset($data['isrc']) || isset($data['isrc_code'])) {
            $isrcValue = $data['isrc'] ?? $data['isrc_code'];
            $currentIsrc = $release['isrc'] ?? '';

            // Only update if value changed
            if ($isrcValue !== $currentIsrc) {
                $updates[] = "isrc = ?";
                $params[] = $isrcValue;
                // Mark as set by admin only if admin actually changed the value
                if (!empty($isrcValue)) {
                    $updates[] = "isrc_set_by_admin = 1";
                }
            }
        }

        if (empty($updates)) {
            Response::success(null, 'No changes to save');
            return;
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $id;

        $sql = "UPDATE releases SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        Response::success(null, 'Release updated successfully');
    }
    
    /**
     * Update release status
     * PUT /api/admin/songs/{id}/status
     */
    public static function updateReleaseStatus($id) {
        AdminAuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validStatuses = ['draft', 'pending', 'approved', 'rejected', 'live', 'taken_down'];
        if (!isset($data['status']) || !in_array($data['status'], $validStatuses)) {
            Response::error('Invalid status', 400);
        }
        
        $releaseModel = new Release();
        $release = $releaseModel->findById($id);
        
        if (!$release) {
            Response::notFound('Release not found');
        }
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE releases SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$data['status'], $id]);
        
        Response::success(null, 'Release status updated successfully');
    }
    
    /**
     * Delete release
     * DELETE /api/admin/songs/{id}
     */
    public static function deleteRelease($id) {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        // Check if release exists
        $stmt = $db->prepare("SELECT id FROM releases WHERE id = ?");
        $stmt->execute([$id]);
        $release = $stmt->fetch();

        if (!$release) {
            Response::notFound('Release not found');
        }

        // Delete related data first (due to foreign key constraints)
        // Delete tracks
        $stmt = $db->prepare("DELETE FROM tracks WHERE release_id = ?");
        $stmt->execute([$id]);

        // Delete release artists
        $stmt = $db->prepare("DELETE FROM release_artists WHERE release_id = ?");
        $stmt->execute([$id]);

        // Delete release stores
        $stmt = $db->prepare("DELETE FROM release_stores WHERE release_id = ?");
        $stmt->execute([$id]);

        // Delete release
        $stmt = $db->prepare("DELETE FROM releases WHERE id = ?");
        $stmt->execute([$id]);

        Response::success(null, 'Release deleted successfully');
    }

    /**
     * Bulk delete songs
     * POST /api/admin/songs/bulk-delete
     */
    public static function bulkDelete() {
        AdminAuthMiddleware::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);
        $songIds = $data['song_ids'] ?? [];

        if (empty($songIds)) {
            Response::error('No songs selected for deletion');
        }

        $db = Database::getInstance()->getConnection();
        $placeholders = str_repeat('?,', count($songIds) - 1) . '?';
        $stmt = $db->prepare("DELETE FROM tracks WHERE id IN ($placeholders)");
        $stmt->execute($songIds);

        Response::success(null, count($songIds) . ' song(s) deleted successfully');
    }

    /**
     * Bulk update status
     * POST /api/admin/songs/bulk-update-status
     */
    public static function bulkUpdateStatus() {
        AdminAuthMiddleware::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);
        $songIds = $data['song_ids'] ?? [];
        $status = $data['status'] ?? '';

        $validStatuses = ['draft', 'pending', 'approved', 'rejected', 'live', 'taken_down'];
        if (!in_array($status, $validStatuses)) {
            Response::error('Invalid status', 400);
        }

        if (empty($songIds)) {
            Response::error('No songs selected');
        }

        $db = Database::getInstance()->getConnection();

        // Get all release IDs for these tracks
        $placeholders = str_repeat('?,', count($songIds) - 1) . '?';
        $stmt = $db->prepare("SELECT DISTINCT release_id FROM tracks WHERE id IN ($placeholders)");
        $stmt->execute($songIds);
        $releaseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($releaseIds)) {
            // Update status for all releases
            $placeholders = str_repeat('?,', count($releaseIds) - 1) . '?';
            $stmt = $db->prepare("UPDATE releases SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
            $params = array_merge([$status], $releaseIds);
            $stmt->execute($params);
        }

        Response::success(null, count($songIds) . ' song(s) updated to ' . $status);
    }

    /**
     * Get a single track
     */
    public static function getTrack($id)
    {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT t.*, tm.recording_year, tm.recording_country
            FROM tracks t
            LEFT JOIN track_metadata tm ON t.id = tm.track_id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $track = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$track) {
            Response::notFound('Track not found');
            return;
        }

        // Get track artists
        $stmt = $db->prepare("SELECT * FROM track_artists WHERE track_id = ?");
        $stmt->execute([$id]);
        $track['artists'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success(['track' => $track]);
    }

    /**
     * Update a track
     */
    public static function updateTrack($id)
    {
        try {
            AdminAuthMiddleware::authenticate();

            $db = Database::getInstance()->getConnection();

            // Ensure track_metadata table exists
            $db->exec("CREATE TABLE IF NOT EXISTS track_metadata (
                id INT AUTO_INCREMENT PRIMARY KEY,
                track_id INT NOT NULL,
                recording_year INT NULL,
                recording_country VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_track (track_id)
            )");

            // Check if track exists
            $stmt = $db->prepare("SELECT * FROM tracks WHERE id = ?");
            $stmt->execute([$id]);
            $track = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$track) {
                Response::notFound('Track not found');
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Build update query dynamically for tracks table
            $updates = [];
            $params = [];

            // Fields that exist in the tracks table (ISRC is now at release level, not track level)
            $trackFields = [
                'track_title', 'track_version', 'language',
                'explicit_content', 'release_date', 'release_time',
                'preview_start', 'track_number', 'audio_style'
            ];

            // Fields that exist in track_metadata table
            $metadataFields = ['recording_year', 'recording_country'];

            foreach ($trackFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            // Update tracks table if there are changes
            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";
                $params[] = $id;

                $sql = "UPDATE tracks SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }

            // Handle track_metadata fields separately
            $hasMetadata = false;
            foreach ($metadataFields as $field) {
                if (isset($data[$field])) {
                    $hasMetadata = true;
                    break;
                }
            }

            if ($hasMetadata) {
                // Check if metadata exists
                $stmt = $db->prepare("SELECT id FROM track_metadata WHERE track_id = ?");
                $stmt->execute([$id]);
                $existingMetadata = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existingMetadata) {
                    // Update existing metadata
                    $metaUpdates = [];
                    $metaParams = [];
                    foreach ($metadataFields as $field) {
                        if (isset($data[$field])) {
                            $metaUpdates[] = "$field = ?";
                            $metaParams[] = $data[$field];
                        }
                    }
                    if (!empty($metaUpdates)) {
                        $metaParams[] = $id;
                        $sql = "UPDATE track_metadata SET " . implode(', ', $metaUpdates) . " WHERE track_id = ?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute($metaParams);
                    }
                } else {
                    // Insert new metadata
                    $stmt = $db->prepare("INSERT INTO track_metadata (track_id, recording_year, recording_country) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $id,
                        $data['recording_year'] ?? null,
                        $data['recording_country'] ?? null
                    ]);
                }
            }

            if (empty($updates) && !$hasMetadata) {
                Response::error('No valid fields to update', 400);
                return;
            }

            // Fetch updated track with metadata
            $stmt = $db->prepare("
                SELECT t.*, tm.recording_year, tm.recording_country
                FROM tracks t
                LEFT JOIN track_metadata tm ON t.id = tm.track_id
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            $updatedTrack = $stmt->fetch(PDO::FETCH_ASSOC);

            Response::success(['track' => $updatedTrack], 'Track updated successfully');
        } catch (Exception $e) {
            Response::error('Error updating track: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a track
     */
    public static function deleteTrack($id)
    {
        try {
            AdminAuthMiddleware::authenticate();

            $db = Database::getInstance()->getConnection();

            // Check if track exists
            $stmt = $db->prepare("SELECT * FROM tracks WHERE id = ?");
            $stmt->execute([$id]);
            $track = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$track) {
                Response::notFound('Track not found');
                return;
            }

            // Delete track metadata first
            $stmt = $db->prepare("DELETE FROM track_metadata WHERE track_id = ?");
            $stmt->execute([$id]);

            // Delete track artists
            $stmt = $db->prepare("DELETE FROM track_artists WHERE track_id = ?");
            $stmt->execute([$id]);

            // Delete the track
            $stmt = $db->prepare("DELETE FROM tracks WHERE id = ?");
            $stmt->execute([$id]);

            Response::success(null, 'Track deleted successfully');
        } catch (Exception $e) {
            Response::error('Error deleting track: ' . $e->getMessage(), 500);
        }
    }
}

