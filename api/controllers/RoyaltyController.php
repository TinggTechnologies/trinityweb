<?php
/**
 * Royalty Controller
 * Handles royalty and payment request operations
 */

class RoyaltyController {
    /**
     * Get royalties
     */
    public static function getRoyalties() {
        $userId = AuthMiddleware::authenticate();
        
        $royaltyModel = new Royalty();
        
        // Get current balance
        $balance = $royaltyModel->getCurrentBalance($userId);
        
        // Get royalty history
        $history = $royaltyModel->getHistory($userId);
        
        // Get payment requests
        $paymentRequests = $royaltyModel->getPaymentRequests($userId);
        
        Response::success([
            'balance' => $balance,
            'history' => $history,
            'payment_requests' => $paymentRequests
        ]);
    }
    
    /**
     * Request payment
     */
    public static function requestPayment() {
        $userId = AuthMiddleware::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);

        // Validate amount
        if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
            Response::error('Invalid amount', 400);
        }

        $royaltyModel = new Royalty();

        // Check if user has sufficient balance
        $balance = $royaltyModel->getCurrentBalance($userId);
        if ($balance < $data['amount']) {
            Response::error('Insufficient balance', 400);
        }

        // Check if there's a pending request
        $latestRequest = $royaltyModel->getLatestPaymentRequest($userId);
        if ($latestRequest && $latestRequest['status'] === 'Pending') {
            Response::error('You already have a pending payment request', 400);
        }

        // Create payment request
        try {
            $royaltyModel->createPaymentRequest($userId, $data['amount']);
            Response::success(null, 'Payment request submitted successfully', 201);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}

