<?php
/**
 * Admin Payment Controller
 * Handles payment request management for admin
 */

class AdminPaymentController {
    /**
     * Get all payment requests with user details and bank information
     * GET /api/admin/payments
     */
    public static function getAllPaymentRequests() {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        try {
            // Get all payment requests with user information
            $stmt = $db->prepare("
                SELECT
                    pr.id,
                    pr.user_id,
                    pr.amount,
                    pr.status,
                    pr.requested_at,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.stage_name,
                    u.mobile_number,
                    CONCAT(u.first_name, ' ', u.last_name) as full_name
                FROM payment_requests pr
                INNER JOIN users u ON pr.user_id = u.id
                ORDER BY
                    CASE pr.status
                        WHEN 'Pending' THEN 1
                        WHEN 'Approved' THEN 2
                        WHEN 'Rejected' THEN 3
                    END,
                    pr.requested_at DESC
            ");
            $stmt->execute();
            $paymentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // For each payment request, get the user's payment method details
            foreach ($paymentRequests as &$request) {
                $userEmail = $request['email'];

                // Try to get bank payment details using email
                $stmt = $db->prepare("
                    SELECT * FROM bank_payment_details
                    WHERE email = ?
                    LIMIT 1
                ");
                $stmt->execute([$userEmail]);
                $bankDetails = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($bankDetails) {
                    $request['payment_method_type'] = 'bank';
                    $request['payment_details'] = $bankDetails;
                } else {
                    // Try to get PayPal details using email
                    $stmt = $db->prepare("
                        SELECT * FROM paypal_payment_details
                        WHERE email = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$userEmail]);
                    $paypalDetails = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($paypalDetails) {
                        $request['payment_method_type'] = 'paypal';
                        $request['payment_details'] = $paypalDetails;
                    } else {
                        // Try to get crypto details (crypto doesn't have email, so check by payment_method_id)
                        $userId = $request['user_id'];
                        $stmt = $db->prepare("
                            SELECT id, method_type
                            FROM payment_methods
                            WHERE user_id = ? AND is_primary = 1 AND method_type = 'crypto'
                            LIMIT 1
                        ");
                        $stmt->execute([$userId]);
                        $paymentMethod = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($paymentMethod) {
                            $stmt = $db->prepare("
                                SELECT * FROM crypto_payment_details
                                WHERE payment_method_id = ?
                            ");
                            $stmt->execute([$paymentMethod['id']]);
                            $cryptoDetails = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($cryptoDetails) {
                                $request['payment_method_type'] = 'crypto';
                                $request['payment_details'] = $cryptoDetails;
                            } else {
                                $request['payment_method_type'] = null;
                                $request['payment_details'] = null;
                            }
                        } else {
                            $request['payment_method_type'] = null;
                            $request['payment_details'] = null;
                        }
                    }
                }
            }

            Response::success($paymentRequests, 'Payment requests retrieved successfully');

        } catch (Exception $e) {
            error_log('Admin Payment Error: ' . $e->getMessage());
            Response::error('Failed to retrieve payment requests: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update payment request status
     * PUT /api/admin/payments/{id}
     */
    public static function updatePaymentStatus($paymentId) {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();

        try {
            // Get request body
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['status'])) {
                Response::error('Status is required', 400);
            }

            $status = $input['status'];

            // Validate status
            if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
                Response::error('Invalid status. Must be Pending, Approved, or Rejected', 400);
            }

            // Update payment request status
            $stmt = $db->prepare("
                UPDATE payment_requests
                SET status = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $paymentId]);

            if ($stmt->rowCount() === 0) {
                Response::error('Payment request not found', 404);
            }

            Response::success([
                'id' => $paymentId,
                'status' => $status
            ], 'Payment request status updated successfully');

        } catch (Exception $e) {
            error_log('Admin Payment Update Error: ' . $e->getMessage());
            Response::error('Failed to update payment request: ' . $e->getMessage(), 500);
        }
    }
}

