<?php
/**
 * Admin Earnings Controller
 * Handles CSV upload and earnings analytics
 */

class AdminEarningsController {
    /**
     * Upload CSV file with earnings data
     * POST /api/admin/earnings/upload
     */
    public static function uploadCSV() {
        AdminAuthMiddleware::authenticate();

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('No file uploaded or upload error occurred', 400);
        }

        $file = $_FILES['csv_file'];
        
        // Validate file type
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileExt !== 'csv') {
            Response::error('Only CSV files are allowed', 400);
        }

        // Validate file size (max 50MB)
        if ($file['size'] > 50 * 1024 * 1024) {
            Response::error('File size exceeds 50MB limit', 400);
        }

        try {
            // Parse CSV
            $csvData = [];
            $handle = fopen($file['tmp_name'], 'r');
            
            if ($handle === false) {
                Response::error('Failed to open CSV file', 500);
            }

            // Read header row
            $headers = fgetcsv($handle);
            if ($headers === false) {
                fclose($handle);
                error_log('CSV Upload Error: Empty CSV file');
                Response::error('CSV file is empty', 400);
            }

            error_log('CSV Upload: Original headers: ' . print_r($headers, true));

            // Normalize headers - remove quotes, convert to lowercase, replace spaces/special chars
            $headers = array_map(function($header) {
                // Remove BOM if present (UTF-8 BOM is EF BB BF)
                $header = str_replace("\xEF\xBB\xBF", '', $header);
                // Remove quotes and trim
                $header = trim($header, '"');
                // Convert to lowercase
                $header = strtolower($header);
                // Remove parentheses and their content
                $header = preg_replace('/\([^)]*\)/', '', $header);
                // Replace spaces and hyphens with underscores
                $header = str_replace([' ', '-'], '_', $header);
                // Remove any remaining special characters
                $header = preg_replace('/[^a-z0-9_]/', '', $header);
                // Remove trailing underscores
                $header = trim($header, '_');
                return $header;
            }, $headers);

            error_log('CSV Upload: Normalized headers: ' . print_r($headers, true));

            $rowCount = 0;
            $maxRows = 100000; // Limit to prevent memory issues
            $skippedRows = 0;

            // Read data rows
            while (($row = fgetcsv($handle)) !== false && $rowCount < $maxRows) {
                if (count($row) !== count($headers)) {
                    $skippedRows++;
                    error_log("CSV Upload: Skipping row - column count mismatch. Expected: " . count($headers) . ", Got: " . count($row));
                    continue; // Skip malformed rows
                }

                $rowData = array_combine($headers, $row);

                // Map CSV columns to database columns
                // Headers after normalization:
                // "Reporting Period" -> reporting_period
                // "UPC Code" -> upc_code
                // "ISRC Code" -> isrc_code
                // "Digital Service Provider" -> digital_service_provider
                // "Royalty ($US)" -> royalty (parentheses removed)
                $csvData[] = [
                    'reporting_period' => $rowData['reporting_period'] ?? '',
                    'label' => $rowData['label'] ?? '',
                    'release_name' => $rowData['release_name'] ?? '',
                    'release_version' => $rowData['release_version'] ?? '',
                    'release_artists' => $rowData['release_artists'] ?? '',
                    'upc' => $rowData['upc_code'] ?? '',
                    'catalogue' => $rowData['catalogue'] ?? '',
                    'track_title' => $rowData['track_title'] ?? '',
                    'mix_version' => $rowData['mix_version'] ?? '',
                    'isrc' => $rowData['isrc_code'] ?? '',
                    'track_artists' => $rowData['track_artists'] ?? '',
                    'dsp' => $rowData['digital_service_provider'] ?? '',
                    'activity_period' => $rowData['activity_period'] ?? '',
                    'territory' => $rowData['territory'] ?? '',
                    'delivery' => $rowData['delivery'] ?? '',
                    'content_type' => $rowData['content_type'] ?? '',
                    'sale_or_void' => $rowData['sale_or_void'] ?? '',
                    'count' => (int)($rowData['count'] ?? 0),
                    'royalty' => (float)($rowData['royalty'] ?? 0) // "Royalty ($US)" becomes "royalty"
                ];

                $rowCount++;
            }

            fclose($handle);

            error_log("CSV Upload: Total rows parsed: $rowCount, Skipped rows: $skippedRows");
            error_log('CSV Upload: First 2 data rows: ' . print_r(array_slice($csvData, 0, 2), true));

            if (empty($csvData)) {
                error_log('CSV Upload Error: No valid data found after parsing');
                Response::error('No valid data found in CSV file', 400);
            }

            // Generate batch ID
            $batchId = uniqid('batch_', true);

            // Insert data into database
            error_log("CSV Upload: Calling insertBatch with " . count($csvData) . " rows, batchId: $batchId");
            $streamEarning = new StreamEarning();
            $result = $streamEarning->insertBatch($csvData, $batchId);
            error_log("CSV Upload: insertBatch result: " . ($result ? 'true' : 'false'));

            // Log upload
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO csv_uploads (batch_id, filename, rows_imported, uploaded_by, uploaded_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            // Get admin ID
            $adminId = $_SESSION['admin_id'] ?? null;
            $stmt->execute([$batchId, $file['name'], $rowCount, $adminId]);

            // Process royalties from CSV data
            error_log("CSV Upload: Processing royalties from CSV data");
            $royaltiesProcessed = self::processRoyaltiesFromCSV($csvData);
            error_log("CSV Upload: Processed royalties for " . count($royaltiesProcessed) . " users");

            Response::success([
                'batch_id' => $batchId,
                'rows_imported' => $rowCount,
                'filename' => $file['name'],
                'royalties_processed' => count($royaltiesProcessed)
            ], 'CSV file uploaded and processed successfully');

        } catch (Exception $e) {
            error_log('CSV Upload Error: ' . $e->getMessage());
            Response::error('Failed to process CSV file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get upload history
     * GET /api/admin/earnings/uploads
     */
    public static function getUploads() {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        // Create uploads table if not exists
        $db->exec("CREATE TABLE IF NOT EXISTS csv_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batch_id VARCHAR(100) UNIQUE,
            filename VARCHAR(255),
            rows_imported INT,
            uploaded_by INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_batch_id (batch_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $db->query("
            SELECT u.*, a.first_name, a.last_name
            FROM csv_uploads u
            LEFT JOIN administrators ad ON u.uploaded_by = ad.id
            LEFT JOIN users a ON ad.user_id = a.id
            ORDER BY u.uploaded_at DESC
            LIMIT 50
        ");

        $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success(['uploads' => $uploads], 'Upload history retrieved successfully');
    }

    /**
     * Get earnings data
     * GET /api/admin/earnings/data
     */
    public static function getEarnings() {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        // Get pagination parameters
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = ($page - 1) * $limit;

        // Get total count
        $stmt = $db->query("SELECT COUNT(*) FROM stream_earnings");
        $totalRecords = $stmt->fetchColumn();

        // Get earnings data
        $stmt = $db->prepare("
            SELECT
                id,
                track_title,
                release_name,
                digital_service_provider as dsp,
                activity_period,
                territory,
                count as streams,
                royalty,
                sale_or_void,
                reporting_period,
                isrc_code as isrc,
                upc_code as upc,
                catalogue,
                created_at as uploaded_at
            FROM stream_earnings
            ORDER BY created_at DESC, id DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$limit, $offset]);
        $earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'earnings' => $earnings,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalRecords,
                'pages' => ceil($totalRecords / $limit)
            ]
        ], 'Earnings data retrieved successfully');
    }

    /**
     * Get earnings analytics aggregated by user
     * GET /api/admin/earnings/analytics
     */
    public static function getAnalytics() {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        // Get total streams and earnings by UPC (release level) and ISRC (track level)
        // Match with user releases and tracks
        $stmt = $db->query("
            SELECT
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.stage_name,
                u.email,
                COUNT(DISTINCT se.id) as total_records,
                SUM(CASE WHEN se.sale_or_void = 'Sale' THEN se.count ELSE 0 END) as total_streams,
                SUM(CASE WHEN se.sale_or_void = 'Sale' THEN se.royalty ELSE 0 END) as total_earnings
            FROM users u
            INNER JOIN releases r ON u.id = r.user_id
            LEFT JOIN tracks t ON r.id = t.release_id
            INNER JOIN stream_earnings se ON (
                (se.upc_code = r.upc AND r.upc IS NOT NULL AND r.upc != '')
                OR (se.isrc_code = t.isrc AND t.isrc IS NOT NULL AND t.isrc != '')
            )
            WHERE se.sale_or_void = 'Sale'
            GROUP BY u.id
            ORDER BY total_earnings DESC
        ");

        $userEarnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get overall statistics
        $stmt = $db->query("
            SELECT
                COUNT(*) as total_records,
                SUM(CASE WHEN sale_or_void = 'Sale' THEN count ELSE 0 END) as total_streams,
                SUM(CASE WHEN sale_or_void = 'Sale' THEN royalty ELSE 0 END) as total_earnings,
                COUNT(DISTINCT digital_service_provider) as total_dsps,
                COUNT(DISTINCT territory) as total_territories,
                COUNT(DISTINCT reporting_period) as total_periods
            FROM stream_earnings
        ");

        $overall = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get top DSPs
        $stmt = $db->query("
            SELECT
                digital_service_provider as dsp,
                SUM(CASE WHEN sale_or_void = 'Sale' THEN count ELSE 0 END) as streams,
                SUM(CASE WHEN sale_or_void = 'Sale' THEN royalty ELSE 0 END) as earnings
            FROM stream_earnings
            WHERE sale_or_void = 'Sale'
            GROUP BY digital_service_provider
            ORDER BY earnings DESC
            LIMIT 10
        ");

        $topDsps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get top territories
        $stmt = $db->query("
            SELECT
                territory,
                SUM(CASE WHEN sale_or_void = 'Sale' THEN count ELSE 0 END) as streams,
                SUM(CASE WHEN sale_or_void = 'Sale' THEN royalty ELSE 0 END) as earnings
            FROM stream_earnings
            WHERE sale_or_void = 'Sale'
            GROUP BY territory
            ORDER BY streams DESC
            LIMIT 10
        ");

        $topTerritories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'user_earnings' => $userEarnings,
            'overall' => $overall,
            'top_dsps' => $topDsps,
            'top_territories' => $topTerritories
        ], 'Analytics retrieved successfully');
    }

    /**
     * Process royalties from CSV data
     * Groups earnings by user and period, calculates split shares, and creates/updates royalty records
     */
    private static function processRoyaltiesFromCSV($csvData) {
        $db = Database::getInstance()->getConnection();
        $processedUsers = [];

        try {
            // Group earnings by catalogue number and reporting period
            $earningsByPeriod = [];

            foreach ($csvData as $row) {
                $catalogue = $row['catalogue'] ?? null;
                $period = $row['reporting_period'] ?? null;
                $royalty = floatval($row['royalty'] ?? 0);
                $saleOrVoid = strtolower($row['sale_or_void'] ?? '');

                // Skip void transactions
                if (in_array($saleOrVoid, ['void', 'voided'])) {
                    continue;
                }

                if (!$catalogue || !$period) {
                    continue;
                }

                $key = $catalogue . '_' . $period;

                if (!isset($earningsByPeriod[$key])) {
                    $earningsByPeriod[$key] = [
                        'catalogue' => $catalogue,
                        'period' => $period,
                        'total_royalty' => 0
                    ];
                }

                $earningsByPeriod[$key]['total_royalty'] += $royalty;
            }

            error_log("Royalties Processing: Grouped into " . count($earningsByPeriod) . " period entries");

            // Process each period's earnings
            foreach ($earningsByPeriod as $periodData) {
                $catalogue = $periodData['catalogue'];
                $period = $periodData['period'];
                $earnings = $periodData['total_royalty'];

                // Find user by catalogue number
                $stmt = $db->prepare("
                    SELECT user_id FROM releases
                    WHERE catalog_number = ?
                    LIMIT 1
                ");
                $stmt->execute([$catalogue]);
                $release = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$release) {
                    error_log("Royalties Processing: No user found for catalogue $catalogue");
                    continue;
                }

                $userId = $release['user_id'];
                error_log("Royalties Processing: Processing period $period for user $userId (catalogue $catalogue), earnings: $earnings");

                // Get previous period's closing balance for opening balance
                $stmt = $db->prepare("
                    SELECT closing_balance
                    FROM royalties
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $previousRoyalty = $stmt->fetch(PDO::FETCH_ASSOC);
                $openingBalance = $previousRoyalty ? floatval($previousRoyalty['closing_balance']) : 0.00;

                // Calculate split share deductions
                $splitShareDeductions = self::calculateSplitShareDeductions($userId, $catalogue, $earnings, $db);

                // Adjustments is always 0
                $adjustments = 0.00;

                // Withdrawal defaults to 0 (user can update this later)
                $withdrawal = 0.00;

                // Calculate closing balance
                $closingBalance = $openingBalance + $earnings + $adjustments - $splitShareDeductions - $withdrawal;

                // Check if royalty record already exists for this user and period
                $stmt = $db->prepare("
                    SELECT id FROM royalties
                    WHERE user_id = ? AND period = ?
                ");
                $stmt->execute([$userId, $period]);
                $existingRoyalty = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existingRoyalty) {
                    // Update existing record
                    $stmt = $db->prepare("
                        UPDATE royalties
                        SET opening_balance = ?,
                            earnings = earnings + ?,
                            adjustments = ?,
                            split_share_deductions = ?,
                            withdrawals = ?,
                            closing_balance = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $openingBalance,
                        $earnings,
                        $adjustments,
                        $splitShareDeductions,
                        $withdrawal,
                        $closingBalance,
                        $existingRoyalty['id']
                    ]);
                    error_log("Royalties Processing: Updated existing royalty record for user $userId, period $period");
                } else {
                    // Insert new record
                    $stmt = $db->prepare("
                        INSERT INTO royalties (
                            user_id, period, opening_balance, earnings, adjustments,
                            split_share_deductions, withdrawals, closing_balance, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $userId,
                        $period,
                        $openingBalance,
                        $earnings,
                        $adjustments,
                        $splitShareDeductions,
                        $withdrawal,
                        $closingBalance
                    ]);
                    error_log("Royalties Processing: Created new royalty record for user $userId, period $period");
                }

                $processedUsers[$userId] = true;
            }

            return array_keys($processedUsers);

        } catch (Exception $e) {
            error_log("Royalties Processing Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate split share deductions for a user's release
     */
    private static function calculateSplitShareDeductions($userId, $catalogue, $earnings, $db) {
        try {
            // Get release ID from catalogue number
            $stmt = $db->prepare("
                SELECT id FROM releases
                WHERE catalog_number = ? AND user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$catalogue, $userId]);
            $release = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$release) {
                return 0.00;
            }

            $releaseId = $release['id'];

            // Get all approved split shares for this release
            $stmt = $db->prepare("
                SELECT SUM(split_percentage) as total_percentage
                FROM split_shares
                WHERE release_id = ? AND status = 'approved'
            ");
            $stmt->execute([$releaseId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $totalPercentage = floatval($result['total_percentage'] ?? 0);

            if ($totalPercentage <= 0) {
                return 0.00;
            }

            // Calculate deduction amount
            $deduction = ($earnings * $totalPercentage) / 100;

            error_log("Split Share Calculation: Release $releaseId, Total %: $totalPercentage, Earnings: $earnings, Deduction: $deduction");

            return $deduction;

        } catch (Exception $e) {
            error_log("Split Share Calculation Error: " . $e->getMessage());
            return 0.00;
        }
    }
}

