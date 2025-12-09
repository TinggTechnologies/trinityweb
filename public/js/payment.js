/**
 * Payment Details Page JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Set current year in footer
    document.getElementById('currentYear').textContent = new Date().getFullYear();
    
    // Load payment details and settings
    loadPaymentDetails();
    
    // Populate country dropdown
    populateCountryDropdown();
    
    // Setup form handler
    setupFormHandler();
});

/**
 * Load payment details and settings from API
 */
async function loadPaymentDetails() {
    try {
        const response = await API.get('payment-details');
        
        if (response.success) {
            const { payment_details, payment_settings } = response.data;
            
            // Populate bank details
            if (payment_details.bank) {
                populateBankDetails(payment_details.bank);
            }
            
            // Populate PayPal details
            if (payment_details.paypal) {
                document.getElementById('paypal-email').value = payment_details.paypal.email || '';
            }
            
            // Populate crypto details
            if (payment_details.crypto) {
                populateCryptoDetails(payment_details.crypto);
            }
            
            // Update payment settings note
            if (payment_settings) {
                updatePaymentSettingsNote(payment_settings);
            }
        } else {
            showError('Failed to load payment details');
        }
    } catch (error) {
        console.error('Error loading payment details:', error);
        showError('Failed to load payment details');
    }
}

/**
 * Populate bank details form fields
 */
function populateBankDetails(bankDetails) {
    document.getElementById('first-name').value = bankDetails.first_name || '';
    document.getElementById('last-name').value = bankDetails.last_name || '';
    document.getElementById('account-number').value = bankDetails.account_number || '';
    document.getElementById('bank-name').value = bankDetails.bank_name || '';
    document.getElementById('email').value = bankDetails.email || '';
    document.getElementById('phone-number').value = bankDetails.phone_number || '';
    document.getElementById('bank-country').value = bankDetails.bank_country || '';
    document.getElementById('bank-address').value = bankDetails.bank_address || '';
    document.getElementById('bank-address2').value = bankDetails.bank_address2 || '';
    document.getElementById('bank-city').value = bankDetails.bank_city || '';
    document.getElementById('bank-state').value = bankDetails.bank_state || '';
    document.getElementById('zip-code').value = bankDetails.zip_code || '';
    document.getElementById('swift-code').value = bankDetails.swift_code || '';
    document.getElementById('currency').value = bankDetails.currency || 'USD';
}

/**
 * Populate crypto details form fields
 */
function populateCryptoDetails(cryptoDetails) {
    document.getElementById('crypto-name').value = cryptoDetails.crypto_name || '';
    document.getElementById('wallet-network').value = cryptoDetails.wallet_network || '';
    document.getElementById('wallet-address').value = cryptoDetails.wallet_address || '';
}

/**
 * Update payment settings note
 */
function updatePaymentSettingsNote(settings) {
    if (settings.processing_days_min && settings.processing_days_max) {
        document.getElementById('processingDays').textContent = 
            `${settings.processing_days_min}-${settings.processing_days_max}`;
    }
    
    if (settings.min_withdrawal) {
        document.getElementById('minWithdrawal').textContent = settings.min_withdrawal;
    }
    
    if (settings.vat_percentage) {
        document.getElementById('vatPercentage').textContent = settings.vat_percentage;
    }
}

/**
 * Populate country dropdown
 */
function populateCountryDropdown() {
    const countrySelect = document.getElementById('bank-country');

    if (typeof populateCountries !== 'undefined') {
        // Use the populateCountries function from countries.js
        populateCountries(countrySelect);
    } else if (typeof COUNTRIES !== 'undefined') {
        // Fallback: use COUNTRIES array directly
        COUNTRIES.forEach(country => {
            const option = document.createElement('option');
            option.value = country;
            option.textContent = country;
            countrySelect.appendChild(option);
        });
    }
}

/**
 * Setup form submission handler
 */
function setupFormHandler() {
    const form = document.getElementById('paymentForm');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Clear previous validation errors
        clearValidationErrors();

        // Get form data
        const formData = new FormData(form);
        const data = {};

        formData.forEach((value, key) => {
            if (value.trim() !== '') {
                data[key] = value.trim();
            }
        });

        // Validate that at least one payment method is being submitted
        const hasBankDetails = data.first_name || data.last_name || data.account_number ||
                              data.bank_name || data.email || data.phone_number || data.bank_country;
        const hasPayPalDetails = data.paypal_email;
        const hasCryptoDetails = data.crypto_name || data.wallet_network || data.wallet_address;

        if (!hasBankDetails && !hasPayPalDetails && !hasCryptoDetails) {
            showError('Please provide at least one payment method (Bank, PayPal, or Crypto)');
            return;
        }

        // Validate bank details if any bank field is filled
        if (hasBankDetails) {
            const requiredBankFields = [
                { id: 'first-name', name: 'first_name', label: 'First name' },
                { id: 'last-name', name: 'last_name', label: 'Last name' },
                { id: 'account-number', name: 'account_number', label: 'Account number' },
                { id: 'bank-name', name: 'bank_name', label: 'Bank name' },
                { id: 'email', name: 'email', label: 'Email' },
                { id: 'phone-number', name: 'phone_number', label: 'Phone number' },
                { id: 'bank-country', name: 'bank_country', label: 'Bank country' }
            ];

            let hasErrors = false;
            requiredBankFields.forEach(field => {
                if (!data[field.name]) {
                    const input = document.getElementById(field.id);
                    const feedback = input.nextElementSibling;
                    input.classList.add('is-invalid');
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.textContent = `${field.label} is required`;
                    }
                    hasErrors = true;
                }
            });

            if (hasErrors) {
                showError('Please fill in all required bank fields');
                return;
            }

            // Validate email format
            if (data.email && !isValidEmail(data.email)) {
                const emailInput = document.getElementById('email');
                const feedback = emailInput.nextElementSibling;
                emailInput.classList.add('is-invalid');
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.textContent = 'Please enter a valid email address';
                }
                showError('Invalid email address');
                return;
            }

            // Validate SWIFT code if provided
            if (data.swift_code && !isValidSWIFTCode(data.swift_code)) {
                const swiftInput = document.getElementById('swift-code');
                const feedback = swiftInput.nextElementSibling;
                swiftInput.classList.add('is-invalid');
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.textContent = 'Invalid SWIFT code format (8 or 11 characters, e.g., ABNGNGLA)';
                }
                showError('Invalid SWIFT code format');
                return;
            }
        }

        // Validate PayPal email if provided
        if (hasPayPalDetails && !isValidEmail(data.paypal_email)) {
            const paypalInput = document.getElementById('paypal-email');
            const feedback = paypalInput.nextElementSibling;
            paypalInput.classList.add('is-invalid');
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = 'Please enter a valid PayPal email address';
            }
            showError('Invalid PayPal email address');
            return;
        }

        // Validate crypto details if any crypto field is filled
        if (hasCryptoDetails) {
            const requiredCryptoFields = [
                { id: 'crypto-name', name: 'crypto_name', label: 'Crypto name' },
                { id: 'wallet-network', name: 'wallet_network', label: 'Wallet network' },
                { id: 'wallet-address', name: 'wallet_address', label: 'Wallet address' }
            ];

            let hasErrors = false;
            requiredCryptoFields.forEach(field => {
                if (!data[field.name]) {
                    const input = document.getElementById(field.id);
                    const feedback = input.nextElementSibling;
                    input.classList.add('is-invalid');
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.textContent = `${field.label} is required`;
                    }
                    hasErrors = true;
                }
            });

            if (hasErrors) {
                showError('Please fill in all required crypto fields');
                return;
            }
        }

        try {
            const response = await API.put('payment-details', data);

            if (response.success) {
                showSuccess(response.message || 'Payment details saved successfully!');
                // Reload payment details
                setTimeout(() => loadPaymentDetails(), 1000);
            } else {
                showError(response.message || 'Failed to save payment details');
            }
        } catch (error) {
            console.error('Error saving payment details:', error);
            showError('Failed to save payment details');
        }
    });
}

/**
 * Show success message
 */
function showSuccess(message) {
    const alertContainer = document.getElementById('alertContainer');
    alertContainer.innerHTML = `
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    // Scroll to top to show message
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = alertContainer.querySelector('.alert');
        if (alert) {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 150);
        }
    }, 5000);
}

/**
 * Show error message
 */
function showError(message) {
    const alertContainer = document.getElementById('alertContainer');
    alertContainer.innerHTML = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    // Scroll to top to show message
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Clear validation errors
 */
function clearValidationErrors() {
    const invalidInputs = document.querySelectorAll('.is-invalid');
    invalidInputs.forEach(input => {
        input.classList.remove('is-invalid');
    });

    const feedbacks = document.querySelectorAll('.invalid-feedback');
    feedbacks.forEach(feedback => {
        feedback.textContent = '';
    });
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Validate email format
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Validate SWIFT code format
 */
function isValidSWIFTCode(code) {
    const swiftRegex = /^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/;
    return swiftRegex.test(code.toUpperCase());
}

