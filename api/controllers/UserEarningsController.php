<?php
/**
 * User Earnings Controller
 * Handles user-specific stream earnings data
 * Includes earnings from both owned releases and collaborator releases (via split shares)
 */

class UserEarningsController {
    /**
     * Get earnings for the authenticated user
     * Matches stream_earnings.catalogue with user's releases.catalog_number
     * Also includes earnings from collaborator releases (with split percentage applied)
     */
    public static function getUserEarnings() {
        $userId = AuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        // Get earnings data for this user's releases (owned + collaborator)
        // For owned releases: 100% of earnings (minus any split shares given out)
        // For collaborator releases: only the split percentage they were given
        $stmt = $db->prepare("
            SELECT
                se.id,
                se.track_title,
                se.release_name,
                se.digital_service_provider as dsp,
                se.activity_period,
                se.reporting_period,
                se.territory,
                se.count as streams,
                CASE
                    WHEN r.user_id = ? THEN se.royalty
                    ELSE se.royalty * (ss.percentage / 100)
                END as royalty,
                se.sale_or_void,
                se.catalogue,
                se.isrc_code,
                se.upc_code,
                r.release_title as user_release_title,
                r.catalog_number,
                CASE WHEN r.user_id = ? THEN 1 ELSE 0 END as is_owner,
                ss.percentage as split_percentage
            FROM stream_earnings se
            INNER JOIN releases r ON se.catalogue COLLATE utf8mb4_unicode_ci = r.catalog_number COLLATE utf8mb4_unicode_ci
            LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
            WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')
            ORDER BY se.created_at DESC, se.id DESC
        ");

        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
        $earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get summary statistics (with split percentage applied for collaborator releases)
        $stmt = $db->prepare("
            SELECT
                COUNT(DISTINCT se.id) as total_records,
                SUM(CASE WHEN LOWER(se.sale_or_void) NOT IN ('void', 'voided') THEN se.count ELSE 0 END) as total_streams,
                SUM(CASE
                    WHEN LOWER(se.sale_or_void) NOT IN ('void', 'voided') THEN
                        CASE WHEN r.user_id = ? THEN se.royalty ELSE se.royalty * (ss.percentage / 100) END
                    ELSE 0
                END) as total_earnings,
                COUNT(DISTINCT se.digital_service_provider) as total_platforms,
                COUNT(DISTINCT se.territory) as total_territories
            FROM stream_earnings se
            INNER JOIN releases r ON se.catalogue COLLATE utf8mb4_unicode_ci = r.catalog_number COLLATE utf8mb4_unicode_ci
            LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
            WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')
        ");

        $stmt->execute([$userId, $userId, $userId, $userId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get earnings by platform (with split percentage applied)
        $stmt = $db->prepare("
            SELECT
                se.digital_service_provider as dsp,
                SUM(CASE WHEN LOWER(se.sale_or_void) NOT IN ('void', 'voided') THEN se.count ELSE 0 END) as streams,
                SUM(CASE
                    WHEN LOWER(se.sale_or_void) NOT IN ('void', 'voided') THEN
                        CASE WHEN r.user_id = ? THEN se.royalty ELSE se.royalty * (ss.percentage / 100) END
                    ELSE 0
                END) as earnings
            FROM stream_earnings se
            INNER JOIN releases r ON se.catalogue COLLATE utf8mb4_unicode_ci = r.catalog_number COLLATE utf8mb4_unicode_ci
            LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
            WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')
            GROUP BY se.digital_service_provider
            ORDER BY earnings DESC
        ");

        $stmt->execute([$userId, $userId, $userId, $userId]);
        $byPlatform = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get earnings by territory (with split percentage applied)
        $stmt = $db->prepare("
            SELECT
                se.territory,
                SUM(CASE WHEN LOWER(se.sale_or_void) NOT IN ('void', 'voided') THEN se.count ELSE 0 END) as streams,
                SUM(CASE
                    WHEN LOWER(se.sale_or_void) NOT IN ('void', 'voided') THEN
                        CASE WHEN r.user_id = ? THEN se.royalty ELSE se.royalty * (ss.percentage / 100) END
                    ELSE 0
                END) as earnings
            FROM stream_earnings se
            INNER JOIN releases r ON se.catalogue COLLATE utf8mb4_unicode_ci = r.catalog_number COLLATE utf8mb4_unicode_ci
            LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
            WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')
            GROUP BY se.territory
            ORDER BY earnings DESC
            LIMIT 10
        ");

        $stmt->execute([$userId, $userId, $userId, $userId]);
        $byTerritory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get earnings by period (with split percentage applied)
        $stmt = $db->prepare("
            SELECT
                se.reporting_period,
                se.activity_period,
                SUM(CASE WHEN LOWER(se.sale_or_void) NOT IN ('void', 'voided') THEN se.count ELSE 0 END) as streams,
                SUM(CASE
                    WHEN LOWER(se.sale_or_void) NOT IN ('void', 'voided') THEN
                        CASE WHEN r.user_id = ? THEN se.royalty ELSE se.royalty * (ss.percentage / 100) END
                    ELSE 0
                END) as earnings
            FROM stream_earnings se
            INNER JOIN releases r ON se.catalogue COLLATE utf8mb4_unicode_ci = r.catalog_number COLLATE utf8mb4_unicode_ci
            LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
            WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')
            GROUP BY se.reporting_period, se.activity_period
            ORDER BY se.reporting_period DESC
        ");

        $stmt->execute([$userId, $userId, $userId, $userId]);
        $byPeriod = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'earnings' => $earnings,
            'summary' => $summary,
            'by_platform' => $byPlatform,
            'by_territory' => $byTerritory,
            'by_period' => $byPeriod
        ], 'User earnings retrieved successfully');
    }

    /**
     * Get earnings summary for dashboard (with split percentage applied for collaborator releases)
     */
    public static function getEarningsSummary() {
        $userId = AuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        // Get total earnings for this user (owned + collaborator with split percentage)
        $stmt = $db->prepare("
            SELECT
                SUM(CASE WHEN LOWER(se.sale_or_void) NOT IN ('void', 'voided') THEN se.count ELSE 0 END) as total_streams,
                SUM(CASE
                    WHEN LOWER(se.sale_or_void) NOT IN ('void', 'voided') THEN
                        CASE WHEN r.user_id = ? THEN se.royalty ELSE se.royalty * (ss.percentage / 100) END
                    ELSE 0
                END) as total_earnings
            FROM stream_earnings se
            INNER JOIN releases r ON se.catalogue COLLATE utf8mb4_unicode_ci = r.catalog_number COLLATE utf8mb4_unicode_ci
            LEFT JOIN split_shares ss ON r.id = ss.release_id AND ss.user_id = ? AND ss.status = 'accepted'
            WHERE r.user_id = ? OR (ss.user_id = ? AND ss.status = 'accepted')
        ");

        $stmt->execute([$userId, $userId, $userId, $userId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success($summary, 'Earnings summary retrieved successfully');
    }
}

