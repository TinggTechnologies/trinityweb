<?php
/**
 * Payment Controller
 * Handles payment method operations
 */

class PaymentController {
    /**
     * Get payment methods
     */
    public static function getPaymentMethods() {
        $userId = AuthMiddleware::authenticate();
        
        $paymentModel = new Payment();
        $methods = $paymentModel->getPaymentMethods($userId);
        
        // Get details for each method
        foreach ($methods as &$method) {
            // Normalize method_type to match case statements
            $methodType = ucfirst(strtolower($method['method_type']));

            switch ($methodType) {
                case 'Bank':
                    $method['details'] = $paymentModel->getBankDetails($method['id']);
                    break;
                case 'Paypal':
                    $method['details'] = $paymentModel->getPayPalDetails($method['id']);
                    break;
                case 'Crypto':
                    $method['details'] = $paymentModel->getCryptoDetails($method['id']);
                    break;
            }
        }
        
        Response::success($methods);
    }
    
    /**
     * Save payment method
     */
    public static function savePaymentMethod() {
        $userId = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate payment type
        $validator = new Validator();
        $validator->required('payment_type', $data['payment_type'] ?? '');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $paymentType = $data['payment_type'];
        
        if (!in_array($paymentType, ['Bank', 'PayPal', 'Crypto'])) {
            Response::error('Invalid payment type');
        }
        
        $paymentModel = new Payment();
        
        // Save payment method
        $methodId = $paymentModel->savePaymentMethod($userId, $paymentType);
        
        // Save details based on type
        try {
            switch ($paymentType) {
                case 'Bank':
                    $validator->required('account_number', $data['account_number'] ?? '')
                             ->required('bank_name', $data['bank_name'] ?? '')
                             ->required('first_name', $data['first_name'] ?? '')
                             ->required('last_name', $data['last_name'] ?? '')
                             ->email('email', $data['email'] ?? '');
                    
                    if ($validator->fails()) {
                        Response::validationError($validator->getErrors());
                    }
                    
                    $paymentModel->saveBankDetails($methodId, $data);
                    break;
                    
                case 'PayPal':
                    $validator->required('paypal_email', $data['paypal_email'] ?? '')
                             ->email('paypal_email', $data['paypal_email'] ?? '');
                    
                    if ($validator->fails()) {
                        Response::validationError($validator->getErrors());
                    }
                    
                    $paymentModel->savePayPalDetails($methodId, $data['paypal_email']);
                    break;
                    
                case 'Crypto':
                    $validator->required('crypto_name', $data['crypto_name'] ?? '')
                             ->required('wallet_network', $data['wallet_network'] ?? '')
                             ->required('wallet_address', $data['wallet_address'] ?? '');
                    
                    if ($validator->fails()) {
                        Response::validationError($validator->getErrors());
                    }
                    
                    $paymentModel->saveCryptoDetails($methodId, $data);
                    break;
            }
            
            Response::success(null, 'Payment method saved successfully');
        } catch (Exception $e) {
            Response::serverError('Failed to save payment details');
        }
    }

    /**
     * Get payment details and settings
     * GET /api/payment-details
     */
    public static function getPaymentDetails() {
        $userId = AuthMiddleware::authenticate();

        $paymentModel = new Payment();
        $paymentDetails = $paymentModel->getUserPaymentDetails($userId);
        $paymentSettings = $paymentModel->getPaymentSettings();

        if ($paymentDetails === null) {
            Response::error('Failed to load payment details', 500);
        }

        Response::success([
            'payment_details' => $paymentDetails,
            'payment_settings' => $paymentSettings
        ]);
    }

    /**
     * Update all payment details at once
     * PUT /api/payment-details
     */
    public static function updateAllPaymentDetails() {
        $userId = AuthMiddleware::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);

        // Check if at least one payment method is being submitted
        $hasBankDetails = !empty($data['first_name']) || !empty($data['last_name']) ||
                         !empty($data['account_number']) || !empty($data['bank_name']) ||
                         !empty($data['email']) || !empty($data['phone_number']) ||
                         !empty($data['bank_country']);
        $hasPayPalDetails = !empty($data['paypal_email']);
        $hasCryptoDetails = !empty($data['crypto_name']) || !empty($data['wallet_network']) ||
                           !empty($data['wallet_address']);

        if (!$hasBankDetails && !$hasPayPalDetails && !$hasCryptoDetails) {
            Response::error('Please provide at least one payment method (Bank, PayPal, or Crypto)', 400);
        }

        $paymentModel = new Payment();
        $errors = [];
        $success = [];

        // Update bank details if any bank field is provided
        if ($hasBankDetails) {
            // Validate required bank fields
            $requiredFields = [
                'first_name' => 'First name',
                'last_name' => 'Last name',
                'account_number' => 'Account number',
                'bank_name' => 'Bank name',
                'email' => 'Email',
                'phone_number' => 'Phone number',
                'bank_country' => 'Bank country'
            ];

            $missingFields = [];
            foreach ($requiredFields as $field => $label) {
                if (empty($data[$field])) {
                    $missingFields[] = $label;
                }
            }

            if (!empty($missingFields)) {
                Response::error('Please fill in all required bank fields: ' . implode(', ', $missingFields), 400);
            }

            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                Response::error('Invalid email address', 400);
            }

            // Validate SWIFT code if provided
            if (!empty($data['swift_code']) && !preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $data['swift_code'])) {
                Response::error('Invalid SWIFT code format. Must be 8 or 11 characters (e.g., ABNGNGLA)', 400);
            }

            $methodId = $paymentModel->savePaymentMethod($userId, 'Bank');
            $result = $paymentModel->saveBankDetails($methodId, $data);
            if ($result) {
                $success[] = 'Bank details saved';
            } else {
                $errors[] = 'Failed to save bank details';
            }
        }

        // Update PayPal details if provided
        if ($hasPayPalDetails) {
            if (!filter_var($data['paypal_email'], FILTER_VALIDATE_EMAIL)) {
                Response::error('Invalid PayPal email address', 400);
            }

            $methodId = $paymentModel->savePaymentMethod($userId, 'PayPal');
            $result = $paymentModel->savePayPalDetails($methodId, $data['paypal_email']);
            if ($result) {
                $success[] = 'PayPal details saved';
            } else {
                $errors[] = 'Failed to save PayPal details';
            }
        }

        // Update crypto details if any crypto field is provided
        if ($hasCryptoDetails) {
            $requiredCryptoFields = [
                'crypto_name' => 'Crypto name',
                'wallet_network' => 'Wallet network',
                'wallet_address' => 'Wallet address'
            ];

            $missingFields = [];
            foreach ($requiredCryptoFields as $field => $label) {
                if (empty($data[$field])) {
                    $missingFields[] = $label;
                }
            }

            if (!empty($missingFields)) {
                Response::error('Please fill in all required crypto fields: ' . implode(', ', $missingFields), 400);
            }

            $methodId = $paymentModel->savePaymentMethod($userId, 'Crypto');
            $result = $paymentModel->saveCryptoDetails($methodId, $data);
            if ($result) {
                $success[] = 'Crypto details saved';
            } else {
                $errors[] = 'Failed to save crypto details';
            }
        }

        if (!empty($errors)) {
            Response::error(implode(', ', $errors), 500);
        } else {
            Response::success(
                null,
                'Payment details saved successfully!'
            );
        }
    }
}

