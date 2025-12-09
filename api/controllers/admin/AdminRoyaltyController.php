<?php
/**
 * Admin Royalty Controller
 * Handles royalty and payment request management for admins
 */

class AdminRoyaltyController {
    /**
     * Get all payment requests
     * GET /api/admin/royalties/payment-requests
     */
    public static function getPaymentRequests() {
        AdminAuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                pr.id, pr.user_id, pr.amount, pr.status, pr.requested_at AS created_at,
                r.period, r.earnings, u.first_name, u.last_name, u.stage_name, 
                u.mobile_number AS phone_number, bpd.account_number, bpd.bank_name
            FROM payment_requests pr
            LEFT JOIN users u ON pr.user_id = u.id
            LEFT JOIN royalties r ON r.user_id = u.id
            LEFT JOIN payment_methods pm ON pm.user_id = u.id
            LEFT JOIN bank_payment_details bpd ON bpd.payment_method_id = pm.id
            ORDER BY pr.requested_at DESC
        ");
        $stmt->execute();
        $payment_requests = $stmt->fetchAll();
        
        Response::success(['payment_requests' => $payment_requests]);
    }
    
    /**
     * Update payment request status
     * PUT /api/admin/royalties/payment-requests/{id}
     */
    public static function updatePaymentRequest($id) {
        AdminAuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['status']) || !in_array($data['status'], ['Pending', 'Approved', 'Rejected'])) {
            Response::error('Invalid status', 400);
        }
        
        $db = Database::getInstance()->getConnection();
        
        // Check if payment request exists
        $stmt = $db->prepare("SELECT * FROM payment_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            Response::notFound('Payment request not found');
        }
        
        // Update status
        $stmt = $db->prepare("UPDATE payment_requests SET status = ? WHERE id = ?");
        $stmt->execute([$data['status'], $id]);
        
        Response::success(null, 'Payment request updated successfully');
    }
    
    /**
     * Get all royalties
     * GET /api/admin/royalties
     */
    public static function getRoyalties() {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        // Get analytics data
        $analytics = [];

        // Total royalties
        $stmt = $db->query("SELECT SUM(earnings) as total FROM royalties");
        $total_royalties = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        $analytics['total_royalties'] = number_format((float)$total_royalties, 2);

        // Approved
        $stmt = $db->prepare("SELECT SUM(amount) as total FROM payment_requests WHERE status = ?");
        $stmt->execute(['Approved']);
        $approved = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        $analytics['approved'] = number_format((float)$approved, 2);

        // Pending
        $stmt->execute(['Pending']);
        $pending = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        $analytics['pending'] = number_format((float)$pending, 2);

        // Rejected
        $stmt->execute(['Rejected']);
        $rejected = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        $analytics['rejected'] = number_format((float)$rejected, 2);

        // Get royalties with user info
        // We'll show royalties table data, not payment_requests
        $stmt = $db->prepare("
            SELECT
                r.id, r.user_id, r.earnings AS amount,
                COALESCE(pr.status, 'Pending') AS status,
                r.created_at,
                r.period, r.earnings, u.first_name, u.last_name, u.stage_name,
                u.mobile_number AS phone_number, bpd.account_number, bpd.bank_name,
                pr.id AS payment_request_id
            FROM royalties r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN payment_methods pm ON pm.user_id = u.id
            LEFT JOIN bank_payment_details bpd ON bpd.payment_method_id = pm.id
            LEFT JOIN payment_requests pr ON pr.user_id = r.user_id AND pr.amount = r.earnings
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        $royalties = $stmt->fetchAll();

        Response::success([
            'royalties' => $royalties,
            'analytics' => $analytics
        ]);
    }
    
    /**
     * Create royalty record
     * POST /api/admin/royalties
     */
    public static function createRoyalty() {
        AdminAuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator();
        $validator->required('user_id', $data['user_id'] ?? '')
                  ->required('period', $data['period'] ?? '')
                  ->required('earnings', $data['earnings'] ?? '');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $db = Database::getInstance()->getConnection();
        
        $opening_balance = $data['opening_balance'] ?? 0;
        $earnings = $data['earnings'] ?? 0;
        $adjustments = $data['adjustments'] ?? 0;
        $withdrawals = $data['withdrawals'] ?? 0;
        $fees = $data['fees'] ?? 0;
        $closing_balance = $opening_balance + $earnings + $adjustments - $withdrawals - $fees;
        
        $stmt = $db->prepare("
            INSERT INTO royalties (user_id, period, opening_balance, earnings, adjustments, withdrawals, fees, closing_balance, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $data['user_id'],
            $data['period'],
            $opening_balance,
            $earnings,
            $adjustments,
            $withdrawals,
            $fees,
            $closing_balance
        ]);
        
        Response::success(['id' => $db->lastInsertId()], 'Royalty record created successfully', 201);
    }
    
    /**
     * Update royalty status
     * PUT /api/admin/royalties/{id}/status
     */
    public static function updateRoyaltyStatus($id) {
        AdminAuthMiddleware::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['status']) || !in_array($data['status'], ['Pending', 'Approved', 'Rejected'])) {
            Response::error('Invalid status', 400);
        }

        $db = Database::getInstance()->getConnection();

        // Get the royalty record
        $stmt = $db->prepare("SELECT * FROM royalties WHERE id = ?");
        $stmt->execute([$id]);
        $royalty = $stmt->fetch();

        if (!$royalty) {
            Response::notFound('Royalty not found');
        }

        // Check if a payment request already exists for this specific royalty
        $stmt = $db->prepare("
            SELECT id FROM payment_requests
            WHERE user_id = ? AND amount = ? AND requested_at >= ?
            LIMIT 1
        ");
        $stmt->execute([
            $royalty['user_id'],
            $royalty['earnings'],
            date('Y-m-d', strtotime($royalty['created_at']))
        ]);
        $existingRequest = $stmt->fetch();

        if ($existingRequest) {
            // Update existing payment request
            $stmt = $db->prepare("UPDATE payment_requests SET status = ? WHERE id = ?");
            $stmt->execute([$data['status'], $existingRequest['id']]);
        } else {
            // Create new payment request for this specific royalty
            $stmt = $db->prepare("
                INSERT INTO payment_requests (user_id, amount, status, requested_at)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $royalty['user_id'],
                $royalty['earnings'],
                $data['status'],
                $royalty['created_at']
            ]);
        }

        Response::success(null, 'Royalty status updated successfully');
    }

    /**
     * Delete royalty record
     * DELETE /api/admin/royalties/{id}
     */
    public static function deleteRoyalty($id) {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        // Delete from payment_requests table
        $stmt = $db->prepare("DELETE FROM payment_requests WHERE id = ?");
        $stmt->execute([$id]);

        Response::success(null, 'Royalty request deleted successfully');
    }
}

