<?php

class PaymentMethod {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
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
    
    /**
     * Save or update bank payment details
     */
    public function saveBankDetails($userId, $data) {
        try {
            $this->db->beginTransaction();
            
            // Check if user already has a bank payment method
            $stmt = $this->db->prepare("
                SELECT pm.id 
                FROM payment_methods pm
                JOIN bank_payment_details bpd ON bpd.payment_method_id = pm.id
                WHERE pm.user_id = ?
            ");
            $stmt->execute([$userId]);
            $existingMethod = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingMethod) {
                // Update existing bank details
                $stmt = $this->db->prepare("
                    UPDATE bank_payment_details SET
                        first_name = ?, last_name = ?, account_number = ?,
                        bank_name = ?, email = ?, phone_number = ?,
                        bank_country = ?, bank_address = ?, bank_address2 = ?,
                        bank_city = ?, bank_state = ?, zip_code = ?,
                        swift_code = ?, currency = ?
                    WHERE payment_method_id = ?
                ");
                
                $stmt->execute([
                    $data['first_name'], $data['last_name'], $data['account_number'],
                    $data['bank_name'], $data['email'], $data['phone_number'],
                    $data['bank_country'], $data['bank_address'] ?? null,
                    $data['bank_address2'] ?? null, $data['bank_city'] ?? null,
                    $data['bank_state'] ?? null, $data['zip_code'] ?? null,
                    $data['swift_code'] ?? null, $data['currency'] ?? 'USD',
                    $existingMethod['id']
                ]);
            } else {
                // Create new payment method
                $stmt = $this->db->prepare("
                    INSERT INTO payment_methods (user_id, method_type, is_primary) 
                    VALUES (?, 'bank', TRUE)
                ");
                $stmt->execute([$userId]);
                $methodId = $this->db->lastInsertId();
                
                // Insert bank details
                $stmt = $this->db->prepare("
                    INSERT INTO bank_payment_details 
                    (payment_method_id, first_name, last_name, account_number,
                    bank_name, email, phone_number, bank_country, bank_address,
                    bank_address2, bank_city, bank_state, zip_code, swift_code, currency)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $methodId, $data['first_name'], $data['last_name'], 
                    $data['account_number'], $data['bank_name'], $data['email'],
                    $data['phone_number'], $data['bank_country'], 
                    $data['bank_address'] ?? null, $data['bank_address2'] ?? null,
                    $data['bank_city'] ?? null, $data['bank_state'] ?? null,
                    $data['zip_code'] ?? null, $data['swift_code'] ?? null,
                    $data['currency'] ?? 'USD'
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error saving bank details: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save or update PayPal payment details
     */
    public function savePayPalDetails($userId, $email) {
        try {
            $this->db->beginTransaction();

            // Check if user already has a PayPal payment method
            $stmt = $this->db->prepare("
                SELECT pm.id
                FROM payment_methods pm
                JOIN paypal_payment_details ppd ON ppd.payment_method_id = pm.id
                WHERE pm.user_id = ?
            ");
            $stmt->execute([$userId]);
            $existingMethod = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingMethod) {
                // Update existing PayPal details
                $stmt = $this->db->prepare("
                    UPDATE paypal_payment_details SET email = ?
                    WHERE payment_method_id = ?
                ");
                $stmt->execute([$email, $existingMethod['id']]);
            } else {
                // Create new payment method
                $stmt = $this->db->prepare("
                    INSERT INTO payment_methods (user_id, method_type)
                    VALUES (?, 'paypal')
                ");
                $stmt->execute([$userId]);
                $methodId = $this->db->lastInsertId();

                // Insert PayPal details
                $stmt = $this->db->prepare("
                    INSERT INTO paypal_payment_details (payment_method_id, email)
                    VALUES (?, ?)
                ");
                $stmt->execute([$methodId, $email]);
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error saving PayPal details: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save or update crypto payment details
     */
    public function saveCryptoDetails($userId, $data) {
        try {
            $this->db->beginTransaction();

            // Check if user already has a crypto payment method
            $stmt = $this->db->prepare("
                SELECT pm.id
                FROM payment_methods pm
                JOIN crypto_payment_details cpd ON cpd.payment_method_id = pm.id
                WHERE pm.user_id = ?
            ");
            $stmt->execute([$userId]);
            $existingMethod = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingMethod) {
                // Update existing crypto details
                $stmt = $this->db->prepare("
                    UPDATE crypto_payment_details SET
                        crypto_name = ?, wallet_network = ?, wallet_address = ?
                    WHERE payment_method_id = ?
                ");
                $stmt->execute([
                    $data['crypto_name'], $data['wallet_network'],
                    $data['wallet_address'], $existingMethod['id']
                ]);
            } else {
                // Create new payment method
                $stmt = $this->db->prepare("
                    INSERT INTO payment_methods (user_id, method_type)
                    VALUES (?, 'crypto')
                ");
                $stmt->execute([$userId]);
                $methodId = $this->db->lastInsertId();

                // Insert crypto details
                $stmt = $this->db->prepare("
                    INSERT INTO crypto_payment_details
                    (payment_method_id, crypto_name, wallet_network, wallet_address)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $methodId, $data['crypto_name'],
                    $data['wallet_network'], $data['wallet_address']
                ]);
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error saving crypto details: " . $e->getMessage());
            return false;
        }
    }
}
