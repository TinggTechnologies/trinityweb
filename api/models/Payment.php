<?php
/**
 * Payment Model
 * Handles payment method operations
 */

class Payment {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get payment methods for user
     */
    public function getPaymentMethods($userId) {
        $stmt = $this->db->prepare("SELECT * FROM payment_methods WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get bank payment details
     */
    public function getBankDetails($methodId) {
        $stmt = $this->db->prepare("SELECT * FROM bank_payment_details WHERE payment_method_id = ?");
        $stmt->execute([$methodId]);
        return $stmt->fetch();
    }
    
    /**
     * Get PayPal details
     */
    public function getPayPalDetails($methodId) {
        $stmt = $this->db->prepare("SELECT * FROM paypal_payment_details WHERE payment_method_id = ?");
        $stmt->execute([$methodId]);
        return $stmt->fetch();
    }
    
    /**
     * Get crypto details
     */
    public function getCryptoDetails($methodId) {
        $stmt = $this->db->prepare("SELECT * FROM crypto_payment_details WHERE payment_method_id = ?");
        $stmt->execute([$methodId]);
        return $stmt->fetch();
    }
    
    /**
     * Save payment method
     */
    public function savePaymentMethod($userId, $type) {
        // Normalize type to lowercase for database enum
        $type = strtolower($type);

        // Check if method exists
        $stmt = $this->db->prepare("SELECT id FROM payment_methods WHERE user_id = ? AND method_type = ?");
        $stmt->execute([$userId, $type]);
        $existing = $stmt->fetch();

        if ($existing) {
            return $existing['id'];
        }

        // Create new method
        $stmt = $this->db->prepare("INSERT INTO payment_methods (user_id, method_type, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$userId, $type]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Save bank details
     */
    public function saveBankDetails($methodId, $data) {
        // Delete existing
        $this->db->prepare("DELETE FROM bank_payment_details WHERE payment_method_id = ?")->execute([$methodId]);
        
        // Insert new
        $sql = "INSERT INTO bank_payment_details (
                    payment_method_id, first_name, last_name, account_number, 
                    bank_name, email, phone_number, bank_country, bank_address,
                    bank_address2, bank_city, bank_state, zip_code, swift_code, currency
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $methodId, $data['first_name'], $data['last_name'], $data['account_number'],
            $data['bank_name'], $data['email'], $data['phone_number'], $data['bank_country'],
            $data['bank_address'] ?? null, $data['bank_address2'] ?? null, $data['bank_city'] ?? null,
            $data['bank_state'] ?? null, $data['zip_code'] ?? null, $data['swift_code'] ?? null,
            $data['currency'] ?? 'USD'
        ]);
    }
    
    /**
     * Save PayPal details
     */
    public function savePayPalDetails($methodId, $email) {
        $this->db->prepare("DELETE FROM paypal_payment_details WHERE payment_method_id = ?")->execute([$methodId]);
        $stmt = $this->db->prepare("INSERT INTO paypal_payment_details (payment_method_id, paypal_email) VALUES (?, ?)");
        return $stmt->execute([$methodId, $email]);
    }
    
    /**
     * Save crypto details
     */
    public function saveCryptoDetails($methodId, $data) {
        $this->db->prepare("DELETE FROM crypto_payment_details WHERE payment_method_id = ?")->execute([$methodId]);
        $sql = "INSERT INTO crypto_payment_details (payment_method_id, crypto_name, wallet_network, wallet_address) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$methodId, $data['crypto_name'], $data['wallet_network'], $data['wallet_address']]);
    }

    /**
     * Get all payment details for a user
     */
    public function getUserPaymentDetails($userId) {
        try {
            // Get bank details
            $stmt = $this->db->prepare("
                SELECT bpd.*
                FROM bank_payment_details bpd
                JOIN payment_methods pm ON bpd.payment_method_id = pm.id
                WHERE pm.user_id = ?
            ");
            $stmt->execute([$userId]);
            $bankDetails = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get PayPal details
            $stmt = $this->db->prepare("
                SELECT ppd.*
                FROM paypal_payment_details ppd
                JOIN payment_methods pm ON ppd.payment_method_id = pm.id
                WHERE pm.user_id = ?
            ");
            $stmt->execute([$userId]);
            $paypalDetails = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get crypto details
            $stmt = $this->db->prepare("
                SELECT cpd.*
                FROM crypto_payment_details cpd
                JOIN payment_methods pm ON cpd.payment_method_id = pm.id
                WHERE pm.user_id = ?
            ");
            $stmt->execute([$userId]);
            $cryptoDetails = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'bank' => $bankDetails ?: null,
                'paypal' => $paypalDetails ?: null,
                'crypto' => $cryptoDetails ?: null
            ];
        } catch (PDOException $e) {
            error_log("Error getting payment details: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get payment settings
     */
    public function getPaymentSettings() {
        try {
            $stmt = $this->db->query("SELECT * FROM payment_settings LIMIT 1");
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting payment settings: " . $e->getMessage());
            return null;
        }
    }
}

