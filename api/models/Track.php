<?php
/**
 * Track Model
 * Handles all track-related database operations
 */

class Track {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create new track
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO tracks (
                        release_id, track_number, track_title, track_version, isrc,
                        featured_artists, composers, lyricists, producers,
                        explicit_content, language, audio_file_path, duration,
                        preview_start, release_date, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['release_id'],
                $data['track_number'] ?? 1,
                $data['track_title'],
                $data['track_version'] ?? null,
                $data['isrc'] ?? null,
                $data['featured_artists'] ?? null,
                $data['composers'] ?? null,
                $data['lyricists'] ?? null,
                $data['producers'] ?? null,
                $data['explicit_content'] ?? 'no',
                $data['language'] ?? null,
                $data['audio_file_path'] ?? null,
                $data['duration'] ?? null,
                $data['preview_start'] ?? 0,
                $data['release_date'] ?? null
            ]);

            $trackId = $this->db->lastInsertId();

            // Insert track artists if provided
            if (!empty($data['artists']) && is_array($data['artists'])) {
                foreach ($data['artists'] as $artist) {
                    // Get or create artist
                    $artistId = null;

                    // If artist_id is provided, use it
                    if (!empty($artist['artist_id']) || !empty($artist['id'])) {
                        $artistId = $artist['artist_id'] ?? $artist['id'];
                    }
                    // Otherwise, create or find artist by name
                    else if (!empty($artist['name'])) {
                        // Check if artist exists
                        $checkStmt = $this->db->prepare("SELECT id FROM artists WHERE name = ? LIMIT 1");
                        $checkStmt->execute([$artist['name']]);
                        $existingArtist = $checkStmt->fetch();

                        if ($existingArtist) {
                            $artistId = $existingArtist['id'];
                        } else {
                            // Create new artist
                            $createStmt = $this->db->prepare("INSERT INTO artists (name) VALUES (?)");
                            $createStmt->execute([$artist['name']]);
                            $artistId = $this->db->lastInsertId();
                        }
                    }

                    // Insert track-artist relationship with name and type
                    $artistStmt = $this->db->prepare("
                        INSERT INTO track_artists (track_id, artist_id, artist_name, role, type, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $artistStmt->execute([
                        $trackId,
                        $artistId,
                        $artist['name'] ?? '',
                        $artist['role'] ?? 'Primary Artist',
                        $artist['type'] ?? 'display'
                    ]);
                }
            }

            // Insert track metadata if provided (use isset to allow empty values)
            if (isset($data['recording_year']) || isset($data['recording_country'])) {
                $metadataStmt = $this->db->prepare("
                    INSERT INTO track_metadata (track_id, recording_year, recording_country, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $metadataStmt->execute([
                    $trackId,
                    !empty($data['recording_year']) ? $data['recording_year'] : null,
                    !empty($data['recording_country']) ? $data['recording_country'] : null
                ]);
            }

            $this->db->commit();
            return $trackId;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get track by ID
     */
    public function findById($id) {
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
                t.audio_style,
                t.audio_file_path,
                t.duration,
                t.preview_start,
                t.release_date,
                t.release_time,
                t.worldwide_release,
                t.created_at,
                t.updated_at,
                tm.recording_year,
                tm.recording_country
            FROM tracks t
            LEFT JOIN track_metadata tm ON t.id = tm.track_id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $track = $stmt->fetch();

        // Ensure recording_year and recording_country are present (even if null)
        if ($track) {
            if (!isset($track['recording_year'])) {
                $track['recording_year'] = null;
            }
            if (!isset($track['recording_country'])) {
                $track['recording_country'] = null;
            }
        }

        return $track;
    }
    
    /**
     * Get tracks by release ID
     */
    public function getByReleaseId($releaseId) {
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                tm.recording_year,
                tm.recording_country
            FROM tracks t
            LEFT JOIN track_metadata tm ON t.id = tm.track_id
            WHERE t.release_id = ?
            ORDER BY t.track_number ASC, t.created_at ASC
        ");
        $stmt->execute([$releaseId]);
        $tracks = $stmt->fetchAll();

        // Get artists for each track
        foreach ($tracks as &$track) {
            $track['artists'] = $this->getTrackArtists($track['id']);

            // Ensure recording_year and recording_country are present (even if null)
            if (!isset($track['recording_year'])) {
                $track['recording_year'] = null;
            }
            if (!isset($track['recording_country'])) {
                $track['recording_country'] = null;
            }
        }

        return $tracks;
    }

    /**
     * Get artists for a track
     */
    public function getTrackArtists($trackId) {
        $stmt = $this->db->prepare("SELECT * FROM track_artists WHERE track_id = ? ORDER BY id ASC");
        $stmt->execute([$trackId]);
        return $stmt->fetchAll();
    }

    /**
     * Add artist to track
     */
    public function addArtist($trackId, $artistName, $role = 'Primary Artist', $type = 'display') {
        $stmt = $this->db->prepare("
            INSERT INTO track_artists (track_id, artist_name, role, type, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$trackId, $artistName, $role, $type]);
    }

    /**
     * Delete track artists
     */
    public function deleteTrackArtists($trackId) {
        $stmt = $this->db->prepare("DELETE FROM track_artists WHERE track_id = ?");
        return $stmt->execute([$trackId]);
    }
    
    /**
     * Update track
     */
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        
        $sql = "UPDATE tracks SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Delete track
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM tracks WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get metadata for a track
     */
    public function getMetadata($trackId) {
        $stmt = $this->db->prepare("SELECT * FROM track_metadata WHERE track_id = ?");
        $stmt->execute([$trackId]);
        return $stmt->fetch();
    }

    /**
     * Create metadata for a track
     */
    public function createMetadata($trackId, $data) {
        $sql = "INSERT INTO track_metadata (
                    track_id, recording_year, recording_country, created_at
                ) VALUES (?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $trackId,
            $data['recording_year'] ?? null,
            $data['recording_country'] ?? null
        ]);
    }

    /**
     * Update metadata for a track
     */
    public function updateMetadata($trackId, $data) {
        $sql = "UPDATE track_metadata SET
                    recording_year = ?,
                    recording_country = ?,
                    updated_at = NOW()
                WHERE track_id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['recording_year'] ?? null,
            $data['recording_country'] ?? null,
            $trackId
        ]);
    }
}

