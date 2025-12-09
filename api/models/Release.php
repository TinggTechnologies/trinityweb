<?php
/**
 * Release Model
 * Handles all release-related database operations
 */

class Release {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create new release
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO releases (
                        user_id, release_title, release_version, catalog_number,
                        upc, genre, subgenre, label_name, c_line_year, c_line_text,
                        p_line_year, p_line_text, num_tracks, pricing_tier, isrc,
                        upc_set_by_admin, isrc_set_by_admin,
                        artwork_path, release_time, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['user_id'],
                $data['release_title'],
                $data['release_version'] ?? null,
                $data['catalog_number'],
                $data['upc'] ?? null,
                $data['genre'],
                $data['subgenre'] ?? null,
                $data['label_name'],
                $data['c_line_year'] ?? null,
                $data['c_line_text'] ?? null,
                $data['p_line_year'] ?? null,
                $data['p_line_text'] ?? null,
                $data['num_tracks'],
                $data['pricing_tier'] ?? null,
                $data['isrc'] ?? null,
                $data['upc_set_by_admin'] ?? 0,
                $data['isrc_set_by_admin'] ?? 0,
                $data['artwork_path'] ?? null,
                $data['release_time'] ?? ''
            ]);

            $releaseId = $this->db->lastInsertId();

            // Insert artists if provided
            if (!empty($data['stage_names']) && is_array($data['stage_names'])) {
                $artistStmt = $this->db->prepare("
                    INSERT INTO release_artists (release_id, stage_name, role, created_at)
                    VALUES (?, ?, 'Primary Artist', NOW())
                ");

                foreach ($data['stage_names'] as $stageName) {
                    if (!empty(trim($stageName))) {
                        $artistStmt->execute([$releaseId, trim($stageName)]);
                    }
                }
            }

            $this->db->commit();
            return $releaseId;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get release by ID
     */
    public function findById($id) {
        $sql = "SELECT r.*,
                    GROUP_CONCAT(DISTINCT ra.stage_name SEPARATOR ', ') AS artists,
                    COUNT(DISTINCT t.id) AS track_count,
                    COUNT(DISTINCT rs.id) AS store_count
                FROM releases r
                LEFT JOIN release_artists ra ON r.id = ra.release_id
                LEFT JOIN tracks t ON r.id = t.release_id
                LEFT JOIN release_stores rs ON r.id = rs.release_id
                WHERE r.id = ?
                GROUP BY r.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $release = $stmt->fetch();

        // Get individual artists, tracks, and stores
        if ($release) {
            $release['artist_list'] = $this->getReleaseArtists($id);
            $release['tracks'] = $this->getReleaseTracks($id);
            $release['stores'] = $this->getReleaseStores($id);
        }

        return $release;
    }

    /**
     * Get stores for a release
     */
    public function getReleaseStores($releaseId) {
        $stmt = $this->db->prepare("SELECT * FROM release_stores WHERE release_id = ? ORDER BY store_name ASC");
        $stmt->execute([$releaseId]);
        return $stmt->fetchAll();
    }

    /**
     * Add a store to a release
     */
    public function addStore($releaseId, $storeName) {
        $stmt = $this->db->prepare("
            INSERT INTO release_stores (release_id, store_name, is_selected, status, created_at)
            VALUES (?, ?, 1, 'pending', NOW())
        ");
        return $stmt->execute([$releaseId, $storeName]);
    }

    /**
     * Delete all stores for a release
     */
    public function deleteReleaseStores($releaseId) {
        $stmt = $this->db->prepare("DELETE FROM release_stores WHERE release_id = ?");
        return $stmt->execute([$releaseId]);
    }

    /**
     * Get tracks for a release with their artists
     */
    public function getReleaseTracks($releaseId) {
        $stmt = $this->db->prepare("
            SELECT
                t.id,
                t.release_id,
                t.track_number,
                t.track_title,
                t.track_version,
                t.isrc,
                t.featured_artists,
                t.composers,
                t.lyricists,
                t.producers,
                t.explicit_content,
                t.language,
                t.audio_file_path,
                t.duration,
                t.preview_start,
                t.release_date,
                t.created_at,
                t.updated_at,
                tm.recording_year,
                tm.recording_country
            FROM tracks t
            LEFT JOIN track_metadata tm ON t.id = tm.track_id
            WHERE t.release_id = ?
            ORDER BY t.track_number ASC
        ");
        $stmt->execute([$releaseId]);
        $tracks = $stmt->fetchAll();

        error_log("Tracks fetched for release $releaseId: " . print_r($tracks, true));

        // Get artists for each track
        foreach ($tracks as &$track) {
            $artistStmt = $this->db->prepare("SELECT * FROM track_artists WHERE track_id = ? ORDER BY id ASC");
            $artistStmt->execute([$track['id']]);
            $track['artists'] = $artistStmt->fetchAll();

            // Ensure recording_year and recording_country are present (even if null)
            if (!isset($track['recording_year'])) {
                $track['recording_year'] = null;
            }
            if (!isset($track['recording_country'])) {
                $track['recording_country'] = null;
            }
        }

        error_log("Tracks after processing: " . print_r($tracks, true));

        return $tracks;
    }

    /**
     * Get artists for a release
     */
    public function getReleaseArtists($releaseId) {
        $stmt = $this->db->prepare("SELECT * FROM release_artists WHERE release_id = ? ORDER BY id ASC");
        $stmt->execute([$releaseId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all releases for a user (including releases where user is a collaborator via split shares)
     */
    public function getByUserId($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT r.*,
                    GROUP_CONCAT(DISTINCT ra.stage_name SEPARATOR ', ') AS artists,
                    COUNT(DISTINCT t.id) AS track_count,
                    COUNT(DISTINCT rs.id) AS store_count,
                    MIN(t.release_date) AS track_release_date,
                    CASE WHEN r.user_id = ? THEN 1 ELSE 0 END AS is_owner,
                    ss.percentage AS split_percentage
                FROM releases r
                LEFT JOIN release_artists ra ON r.id = ra.release_id
                LEFT JOIN tracks t ON r.id = t.release_id
                LEFT JOIN release_stores rs ON r.id = rs.release_id
                LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
                WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')
                GROUP BY r.id
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->bindValue(3, $userId, PDO::PARAM_INT);
        $stmt->bindValue(4, $userId, PDO::PARAM_INT);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->bindValue(6, $offset, PDO::PARAM_INT);
        $stmt->execute();

        $releases = $stmt->fetchAll();

        // Use track_release_date if release_date is null
        foreach ($releases as &$release) {
            if (empty($release['release_date']) && !empty($release['track_release_date'])) {
                $release['release_date'] = $release['track_release_date'];
            }
        }

        return $releases;
    }
    
    /**
     * Update release
     */
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        
        $sql = "UPDATE releases SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Delete release
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM releases WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get next catalog number (globally unique across all users)
     */
    public function getNextCatalogNumber($userId) {
        // Get max catalog number across ALL releases (not just this user)
        $stmt = $this->db->prepare("SELECT MAX(CAST(catalog_number AS UNSIGNED)) as max_catalog FROM releases");
        $stmt->execute();
        $result = $stmt->fetch();

        $nextNumber = ($result['max_catalog'] ?? 0) + 1;
        return str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Add artist to release
     */
    public function addArtist($releaseId, $stageName) {
        $stmt = $this->db->prepare("INSERT INTO release_artists (release_id, stage_name, created_at) VALUES (?, ?, NOW())");
        return $stmt->execute([$releaseId, $stageName]);
    }

    /**
     * Delete all artists for a release
     */
    public function deleteReleaseArtists($releaseId) {
        $stmt = $this->db->prepare("DELETE FROM release_artists WHERE release_id = ?");
        return $stmt->execute([$releaseId]);
    }
    
    /**
     * Count releases by user (including releases where user is a collaborator)
     */
    public function countByUserId($userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT r.id)
            FROM releases r
            LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
            WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchColumn();
    }

    /**
     * Upload artwork file
     */
    public function uploadArtwork($file) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 10 * 1024 * 1024; // 10MB

        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only JPEG and PNG are allowed.');
        }

        if ($file['size'] > $max_size) {
            throw new Exception('File size exceeds 10MB limit.');
        }

        // Create upload directory if it doesn't exist
        $upload_dir = __DIR__ . '/../../uploads/artworks/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'artwork_' . uniqid() . '.' . $extension;
        $filepath = $upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to upload artwork.');
        }

        return 'uploads/artworks/' . $filename;
    }
}

