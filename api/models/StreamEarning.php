<?php
/**
 * StreamEarning Model
 * Handles stream earnings data from CSV uploads
 */

class StreamEarning {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->initializeTable();
    }

    /**
     * Initialize stream_earnings table
     */
    private function initializeTable() {
        $sql = "CREATE TABLE IF NOT EXISTS stream_earnings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reporting_period VARCHAR(50),
            label VARCHAR(255),
            release_name VARCHAR(255),
            release_version VARCHAR(255),
            release_artists TEXT,
            upc VARCHAR(50),
            catalogue VARCHAR(100),
            track_title VARCHAR(255),
            mix_version VARCHAR(255),
            isrc VARCHAR(50),
            track_artists TEXT,
            dsp VARCHAR(100),
            activity_period VARCHAR(50),
            territory VARCHAR(10),
            delivery VARCHAR(50),
            content_type VARCHAR(100),
            sale_or_void VARCHAR(20),
            count INT DEFAULT 0,
            royalty DECIMAL(15, 12) DEFAULT 0.000000000000,
            upload_batch_id VARCHAR(100),
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_isrc (isrc),
            INDEX idx_upc (upc),
            INDEX idx_catalogue (catalogue),
            INDEX idx_dsp (dsp),
            INDEX idx_reporting_period (reporting_period),
            INDEX idx_upload_batch (upload_batch_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $this->db->exec($sql);
    }

    /**
     * Insert earnings data from CSV
     */
    public function insertBatch($data, $batchId) {
        $sql = "INSERT INTO stream_earnings (
            reporting_period, label, release_name, upc_code, catalogue,
            track_title, isrc_code, digital_service_provider,
            activity_period, territory, sale_or_void, count, royalty
        ) VALUES (
            :reporting_period, :label, :release_name, :upc_code, :catalogue,
            :track_title, :isrc_code, :digital_service_provider,
            :activity_period, :territory, :sale_or_void, :count, :royalty
        )";

        $stmt = $this->db->prepare($sql);

        error_log("StreamEarning::insertBatch - Starting transaction for " . count($data) . " rows");
        $this->db->beginTransaction();
        try {
            $insertCount = 0;
            foreach ($data as $row) {
                $stmt->execute([
                    ':reporting_period' => $row['reporting_period'] ?? '',
                    ':label' => $row['label'] ?? '',
                    ':release_name' => $row['release_name'] ?? '',
                    ':upc_code' => $row['upc'] ?? $row['upc_code'] ?? '',
                    ':catalogue' => $row['catalogue'] ?? '',
                    ':track_title' => $row['track_title'] ?? '',
                    ':isrc_code' => $row['isrc'] ?? $row['isrc_code'] ?? '',
                    ':digital_service_provider' => $row['dsp'] ?? $row['digital_service_provider'] ?? '',
                    ':activity_period' => $row['activity_period'] ?? '',
                    ':territory' => $row['territory'] ?? '',
                    ':sale_or_void' => $row['sale_or_void'] ?? '',
                    ':count' => $row['count'] ?? 0,
                    ':royalty' => $row['royalty'] ?? 0
                ]);
                $insertCount++;
            }
            error_log("StreamEarning::insertBatch - Inserted $insertCount rows, committing transaction");
            $this->db->commit();
            error_log("StreamEarning::insertBatch - Transaction committed successfully");
            return true;
        } catch (Exception $e) {
            error_log("StreamEarning::insertBatch - Error: " . $e->getMessage());
            error_log("StreamEarning::insertBatch - Stack trace: " . $e->getTraceAsString());
            $this->db->rollBack();
            error_log("StreamEarning::insertBatch - Transaction rolled back");
            throw $e;
        }
    }

    /**
     * Get earnings analytics for a specific user by ISRC and UPC
     */
    public function getAnalyticsByUser($userId) {
        // Get user's ISRCs from their tracks and UPCs from releases
        $sql = "SELECT DISTINCT t.isrc
                FROM tracks t
                INNER JOIN releases r ON t.release_id = r.id
                WHERE r.user_id = ? AND t.isrc IS NOT NULL AND t.isrc != ''";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $isrcs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get user's UPCs
        $sql = "SELECT DISTINCT upc
                FROM releases
                WHERE user_id = ? AND upc IS NOT NULL AND upc != ''";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $upcs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($isrcs) && empty($upcs)) {
            return [
                'total_streams' => 0,
                'total_earnings' => 0,
                'by_dsp' => [],
                'by_territory' => [],
                'by_track' => []
            ];
        }

        // Build WHERE clause for ISRC or UPC matching
        $conditions = [];
        $params = [];

        if (!empty($isrcs)) {
            $isrcPlaceholders = str_repeat('?,', count($isrcs) - 1) . '?';
            $conditions[] = "isrc_code IN ($isrcPlaceholders)";
            $params = array_merge($params, $isrcs);
        }

        if (!empty($upcs)) {
            $upcPlaceholders = str_repeat('?,', count($upcs) - 1) . '?';
            $conditions[] = "upc_code IN ($upcPlaceholders)";
            $params = array_merge($params, $upcs);
        }

        $whereClause = '(' . implode(' OR ', $conditions) . ') AND sale_or_void = ?';
        $params[] = 'Sale';

        // Total streams and earnings
        $sql = "SELECT
                    SUM(count) as total_streams,
                    SUM(royalty) as total_earnings
                FROM stream_earnings
                WHERE $whereClause";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        // By DSP
        $sql = "SELECT digital_service_provider as dsp, SUM(count) as streams, SUM(royalty) as earnings
                FROM stream_earnings
                WHERE $whereClause
                GROUP BY digital_service_provider
                ORDER BY streams DESC
                LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $byDsp = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // By Territory
        $sql = "SELECT territory, SUM(count) as streams, SUM(royalty) as earnings
                FROM stream_earnings
                WHERE $whereClause
                GROUP BY territory
                ORDER BY streams DESC
                LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $byTerritory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // By Track
        $sql = "SELECT track_title, SUM(count) as streams, SUM(royalty) as earnings
                FROM stream_earnings
                WHERE $whereClause
                GROUP BY track_title
                ORDER BY streams DESC
                LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $byTrack = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_streams' => (int)($totals['total_streams'] ?? 0),
            'total_earnings' => (float)($totals['total_earnings'] ?? 0),
            'by_dsp' => $byDsp,
            'by_territory' => $byTerritory,
            'by_track' => $byTrack
        ];
    }
}

