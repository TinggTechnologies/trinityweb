/**
 * Admin Payments JavaScript
 * Handles payment request management
 */

// Use AppConfig if available, otherwise fallback
const API_BASE_URL = (typeof AppConfig !== 'undefined')
    ? AppConfig.apiUrl
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? 'http://localhost/trinity/api'
        : 'https://trinity.futurewebhost.com.ng/api';

let paymentsTable;
let currentPaymentId = null;
let currentPaymentStatus = null;

// Initialize on page load
$(document).ready(function() {
    // Check authentication
    checkAdminAuth();
    
    // Load admin name
    loadAdminName();
    
    // Initialize DataTable
    initializeDataTable();
    
    // Load payment requests
    loadPaymentRequests();
    
    // Event listeners
    $('#logoutBtn').on('click', logout);
    $('#mobileMenuToggle').on('click', toggleSidebar);
    $('#sidebarOverlay').on('click', toggleSidebar);
    $('#approveBtn').on('click', () => updatePaymentStatus('Approved'));
    $('#rejectBtn').on('click', () => updatePaymentStatus('Rejected'));
    $('#pendingBtn').on('click', () => updatePaymentStatus('Pending'));
});

/**
 * Check admin authentication
 */
function checkAdminAuth() {
    fetch(`${API_BASE_URL}/admin/check-auth`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            window.location.href = './login';
        }
    })
    .catch(error => {
        console.error('Auth check failed:', error);
        window.location.href = './login';
    });
}

/**
 * Load admin name
 */
function loadAdminName() {
    const adminName = sessionStorage.getItem('admin_name') || 'Admin';
    $('#adminName').text(adminName);
}

/**
 * Logout
 */
function logout() {
    fetch(`${API_BASE_URL}/admin/logout`, {
        method: 'POST',
        credentials: 'include'
    })
    .then(() => {
        sessionStorage.clear();
        window.location.href = './login';
    })
    .catch(error => {
        console.error('Logout failed:', error);
        window.location.href = window.location.origin + '/public/admin/login';
    });
}

/**
 * Toggle sidebar (mobile)
 */
function toggleSidebar() {
    $('#sidebar').toggleClass('show');
    $('#sidebarOverlay').toggleClass('show');
}

/**
 * Initialize DataTable
 */
function initializeDataTable() {
    paymentsTable = $('#paymentsTable').DataTable({
        order: [[4, 'desc']], // Sort by requested date descending
        pageLength: 25,
        language: {
            emptyTable: "No payment requests found"
        },
        columnDefs: [
            { orderable: false, targets: 5 } // Actions column not sortable
        ]
    });
}

/**
 * Load payment requests
 */
async function loadPaymentRequests() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/payments`, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            displayPaymentRequests(data.data);
        } else {
            console.error('Failed to load payment requests:', data.message);
            showError('Failed to load payment requests');
        }
    } catch (error) {
        console.error('Error loading payment requests:', error);
        showError('Failed to load payment requests');
    }
}

/**
 * Display payment requests in table
 */
function displayPaymentRequests(payments) {
    paymentsTable.clear();

    payments.forEach(payment => {
        const statusBadge = getStatusBadge(payment.status);
        const requestedDate = new Date(payment.requested_at).toLocaleString();
        
        const row = [
            payment.id,
            `<div><strong>${escapeHtml(payment.full_name)}</strong><br><small class="text-muted">${escapeHtml(payment.email)}</small></div>`,
            `$${parseFloat(payment.amount).toFixed(2)}`,
            statusBadge,
            requestedDate,
            `<button class="btn btn-sm btn-primary" onclick="viewPaymentDetails(${payment.id})">
                <i class="fas fa-eye"></i> View Details
            </button>`
        ];

        paymentsTable.row.add(row);
    });

    paymentsTable.draw();
}

/**
 * Get status badge HTML
 */
function getStatusBadge(status) {
    const statusClasses = {
        'Pending': 'status-pending',
        'Approved': 'status-approved',
        'Rejected': 'status-rejected'
    };

    const className = statusClasses[status] || 'status-pending';
    return `<span class="status-badge ${className}">${status}</span>`;
}

/**
 * View payment details
 */
async function viewPaymentDetails(paymentId) {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/payments`, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            const payment = data.data.find(p => p.id === paymentId);
            if (payment) {
                displayPaymentDetailsModal(payment);
            }
        }
    } catch (error) {
        console.error('Error loading payment details:', error);
        showError('Failed to load payment details');
    }
}

/**
 * Display payment details in modal
 */
function displayPaymentDetailsModal(payment) {
    currentPaymentId = payment.id;
    currentPaymentStatus = payment.status;

    let html = `
        <div class="payment-details-card">
            <h6 class="mb-3"><i class="fas fa-user"></i> User Information</h6>
            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value">${escapeHtml(payment.full_name)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value">${escapeHtml(payment.email)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone:</span>
                <span class="detail-value">${escapeHtml(payment.mobile_number || 'N/A')}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Stage Name:</span>
                <span class="detail-value">${escapeHtml(payment.stage_name || 'N/A')}</span>
            </div>
        </div>

        <div class="payment-details-card">
            <h6 class="mb-3"><i class="fas fa-money-check-alt"></i> Payment Information</h6>
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span class="detail-value"><strong>$${parseFloat(payment.amount).toFixed(2)}</strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">${getStatusBadge(payment.status)}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Requested Date:</span>
                <span class="detail-value">${new Date(payment.requested_at).toLocaleString()}</span>
            </div>
        </div>
    `;

    // Add payment method details
    if (payment.payment_method_type && payment.payment_details) {
        html += `<div class="payment-details-card">`;

        if (payment.payment_method_type === 'bank') {
            html += `
                <h6 class="mb-3"><i class="fas fa-university"></i> Bank Account Details</h6>
                <div class="detail-row">
                    <span class="detail-label">Account Holder:</span>
                    <span class="detail-value">${escapeHtml(payment.payment_details.first_name)} ${escapeHtml(payment.payment_details.last_name)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Bank Name:</span>
                    <span class="detail-value">${escapeHtml(payment.payment_details.bank_name)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Account Number:</span>
                    <span class="detail-value">${escapeHtml(payment.payment_details.account_number)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">SWIFT Code:</span>
                    <span class="detail-value">${escapeHtml(payment.payment_details.swift_code || 'N/A')}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Bank Country:</span>
                    <span class="detail-value">${escapeHtml(payment.payment_details.bank_country)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Currency:</span>
                    <span class="detail-value">${escapeHtml(payment.payment_details.currency)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">${escapeHtml(payment.payment_details.email)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">${escapeHtml(payment.payment_details.phone_number)}</span>
                </div>
            `;
        } else if (payment.payment_method_type === 'paypal') {
            html += `
                <h6 class="mb-3"><i class="fab fa-paypal"></i> PayPal Details</h6>
                <div class="detail-row">
                    <span class="detail-label">PayPal Email:</span>
                    <span class="detail-value">${escapeHtml(payment.payment_details.email)}</span>
                </div>
            `;
        } else if (payment.payment_method_type === 'crypto') {
            html += `
                <h6 class="mb-3"><i class="fab fa-bitcoin"></i> Crypto Wallet Details</h6>
                <div class="detail-row">
                    <span class="detail-label">Crypto:</span>
                    <span class="detail-value">${escapeHtml(payment.payment_details.crypto_name)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Network:</span>
                    <span class="detail-value">${escapeHtml(payment.payment_details.wallet_network)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Wallet Address:</span>
                    <span class="detail-value" style="word-break: break-all;">${escapeHtml(payment.payment_details.wallet_address)}</span>
                </div>
            `;
        }

        html += `</div>`;
    } else {
        html += `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No payment method configured for this user
            </div>
        `;
    }

    $('#paymentDetailsContent').html(html);

    // Show/hide action buttons based on current status
    if (payment.status === 'Pending') {
        $('#approveBtn').show();
        $('#rejectBtn').show();
        $('#pendingBtn').hide();
    } else if (payment.status === 'Approved') {
        $('#approveBtn').hide();
        $('#rejectBtn').show();
        $('#pendingBtn').show();
    } else if (payment.status === 'Rejected') {
        $('#approveBtn').show();
        $('#rejectBtn').hide();
        $('#pendingBtn').show();
    }

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
    modal.show();
}

/**
 * Update payment status
 */
async function updatePaymentStatus(newStatus) {
    if (!currentPaymentId) {
        return;
    }

    // Confirm action
    const confirmMessage = `Are you sure you want to ${newStatus.toLowerCase()} this payment request?`;
    if (!confirm(confirmMessage)) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/admin/payments/${currentPaymentId}`, {
            method: 'PUT',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ status: newStatus })
        });

        const data = await response.json();

        if (data.success) {
            showSuccess(`Payment request ${newStatus.toLowerCase()} successfully`);

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('paymentDetailsModal'));
            modal.hide();

            // Reload payment requests
            await loadPaymentRequests();
        } else {
            showError(data.message || 'Failed to update payment status');
        }
    } catch (error) {
        console.error('Error updating payment status:', error);
        showError('Failed to update payment status');
    }
}

/**
 * Show success message
 */
function showSuccess(message) {
    alert(message); // You can replace this with a better notification system
}

/**
 * Show error message
 */
function showError(message) {
    alert('Error: ' + message); // You can replace this with a better notification system
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}


