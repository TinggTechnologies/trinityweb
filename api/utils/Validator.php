<?php
/**
 * Validation Utility Class
 */

class Validator {
    private $errors = [];
    
    /**
     * Validate required field
     */
    public function required($field, $value, $message = null) {
        if (empty($value) && $value !== '0') {
            $this->errors[$field] = $message ?? ucfirst($field) . ' is required';
        }
        return $this;
    }
    
    /**
     * Validate email
     */
    public function email($field, $value, $message = null) {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? 'Invalid email format';
        }
        return $this;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($field, $value, $min, $message = null) {
        if (!empty($value) && strlen($value) < $min) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must be at least {$min} characters";
        }
        return $this;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength($field, $value, $max, $message = null) {
        if (!empty($value) && strlen($value) > $max) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must not exceed {$max} characters";
        }
        return $this;
    }
    
    /**
     * Validate numeric value
     */
    public function numeric($field, $value, $message = null) {
        if (!empty($value) && !is_numeric($value)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' must be a number';
        }
        return $this;
    }
    
    /**
     * Validate minimum value
     */
    public function min($field, $value, $min, $message = null) {
        if (!empty($value) && $value < $min) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must be at least {$min}";
        }
        return $this;
    }
    
    /**
     * Validate match (e.g., password confirmation)
     */
    public function match($field, $value, $matchValue, $message = null) {
        if ($value !== $matchValue) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' does not match';
        }
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function passes() {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails() {
        return !$this->passes();
    }
    
    /**
     * Get validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Add custom error
     */
    public function addError($field, $message) {
        $this->errors[$field] = $message;
        return $this;
    }
}

