<?php
/**
 * Analytics Model
 * Handles analytics and streaming data operations
 * Includes data for both owned releases and collaborator releases (via split shares)
 */

class Analytics {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get stream analytics for user (including collaborator releases)
     */
    public function getStreamAnalytics($userId) {
        $sql = "SELECT
                    COALESCE(NULLIF(ra.store_name, ''), rs.store_name, 'Unknown') AS platform,
                    ra.date,
                    SUM(ra.streams) AS total_streams
                FROM release_analytics ra
                INNER JOIN releases r ON ra.release_id = r.id
                LEFT JOIN release_stores rs ON rs.release_id = r.id
                LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
                WHERE (r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted'))
                    AND ra.date <> '0000-00-00'
                GROUP BY platform, ra.date
                ORDER BY ra.date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get top artists by streams (including collaborator releases)
     */
    public function getTopArtists($userId, $limit = 10, $offset = 0) {
        $sql = "SELECT
                    ra.stage_name,
                    COALESCE(SUM(rela.streams), 0) as total_streams,
                    MIN(r.artwork_path) as artwork_path
                FROM release_artists ra
                INNER JOIN releases r ON ra.release_id = r.id
                LEFT JOIN release_analytics rela ON r.id = rela.release_id
                LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
                WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')
                GROUP BY ra.stage_name
                ORDER BY total_streams DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->bindValue(3, $userId, PDO::PARAM_INT);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get total count of artists for user (including collaborator releases)
     */
    public function getTotalArtistsCount($userId) {
        $sql = "SELECT COUNT(DISTINCT ra.stage_name) as total
                FROM release_artists ra
                INNER JOIN releases r ON ra.release_id = r.id
                LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
                WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $userId, $userId]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }

    /**
     * Get top tracks by streams (including collaborator releases)
     */
    public function getTopTracks($userId, $limit = 10, $offset = 0) {
        $sql = "SELECT
                    t.id,
                    t.track_title,
                    t.track_version,
                    COALESCE(SUM(rela.streams), 0) as total_streams,
                    r.artwork_path
                FROM tracks t
                INNER JOIN releases r ON t.release_id = r.id
                LEFT JOIN release_analytics rela ON t.id = rela.track_id
                LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
                WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')
                GROUP BY t.id, t.track_title, t.track_version, r.artwork_path
                ORDER BY total_streams DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->bindValue(3, $userId, PDO::PARAM_INT);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get total count of tracks for user (including collaborator releases)
     */
    public function getTotalTracksCount($userId) {
        $sql = "SELECT COUNT(DISTINCT t.id) as total
                FROM tracks t
                INNER JOIN releases r ON t.release_id = r.id
                LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
                WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $userId, $userId]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
}

