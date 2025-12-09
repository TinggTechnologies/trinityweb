/**
 * Royalty Page JavaScript
 * Handles royalty data display and payment requests
 */

let currentBalance = 0;

// Load royalty data on page load
document.addEventListener('DOMContentLoaded', async () => {
    await loadRoyaltyData();
});

/**
 * Load royalty data from API
 */
async function loadRoyaltyData() {
    try {
        const response = await API.get('royalties');
        
        if (response.success) {
            currentBalance = parseFloat(response.data.balance) || 0;
            
            // Update balance display
            document.getElementById('currentBalance').textContent = `$${formatNumber(currentBalance)}`;
            
            // Display royalty history
            displayRoyaltyHistory(response.data.history);
            
            // Display latest payment request if exists
            displayLatestPayment(response.data.payment_requests);
        } else {
            showError('Failed to load royalty data');
        }
    } catch (error) {
        console.error('Error loading royalty data:', error);
        showError('Failed to load royalty data. Please try again.');
    }
}

/**
 * Display royalty history cards
 */
function displayRoyaltyHistory(royalties) {
    const container = document.getElementById('royaltyCardsContainer');

    if (!royalties || royalties.length === 0) {
        container.innerHTML = '<p class="text-center text-muted">No royalty records found yet.</p>';
        return;
    }

    container.innerHTML = royalties.map(r => {
        // Use stored split_share_deductions value
        const splitShareDeductions = parseFloat(r.split_share_deductions || 0);
        let splitShareHTML = '';

        // Show breakdown if available
        if (r.split_share_deductions_breakdown && r.split_share_deductions_breakdown.length > 0) {
            r.split_share_deductions_breakdown.forEach(split => {
                const deductionAmount = parseFloat(split.amount || 0);

                splitShareHTML += `
                    <div class="no-space" style="color: #dc3545;">
                        <p style="font-size: 0.85rem;">
                            <i class="bi bi-share"></i> Split: ${escapeHtml(split.invitee_name)} (${split.split_percentage}%)
                        </p>
                        <p>-$${formatNumber(deductionAmount)}</p>
                    </div>
                `;
            });
        } else if (splitShareDeductions > 0) {
            // Show total if no breakdown available
            splitShareHTML = `
                <div class="no-space" style="color: #dc3545;">
                    <p style="font-size: 0.85rem;">
                        <i class="bi bi-share"></i> Split Share Deductions
                    </p>
                    <p>-$${formatNumber(splitShareDeductions)}</p>
                </div>
            `;
        }

        return `
            <div class="col-12 col-sm-6 col-lg-3 mb-4">
                <div class="royalty-card" style="width: 100%;">
                    <div class="royalty-card-header" style="display: flex; justify-content: space-between; width: 100%;">
                        <span><i class="bi bi-calendar-date"></i> ${escapeHtml(r.period || 'N/A')}</span>
                        <span><i class="bi bi-coin"></i> Earning</span>
                    </div>
                    <div class="royalty-card-body">
                        <div class="no-space"><p>Opening Balance</p><p>$${formatNumber(r.opening_balance)}</p></div>
                        <div class="no-space"><p>Earnings</p><p>$${formatNumber(r.earnings)}</p></div>
                        ${splitShareHTML}
                        <div class="no-space"><p>Adjustments</p><p>$${formatNumber(r.adjustments)}</p></div>
                        <div class="no-space"><p>Withdrawals</p><p>$${formatNumber(r.withdrawals)}</p></div>
                        <div class="underline"></div>
                        <div class="no-space">
                            <p><strong>Closing Balance</strong></p>
                            <p><strong>$${formatNumber(r.closing_balance)}</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Display latest payment request
 */
function displayLatestPayment(paymentRequests) {
    const container = document.getElementById('latestPaymentContainer');
    
    if (!paymentRequests || paymentRequests.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    const latest = paymentRequests[0];
    const statusClass = latest.status === 'Pending' ? 'bg-warning text-dark' :
                       (latest.status === 'Approved' ? 'bg-success' : 'bg-danger');
    
    container.innerHTML = `
        <p class="mt-2">
            Last request:
            <strong>$${formatNumber(latest.amount)}</strong> — 
            <span class="badge ${statusClass}">
                ${escapeHtml(latest.status)}
            </span>
        </p>
    `;
}

/**
 * Handle payment request
 */
document.getElementById('requestPaymentBtn').addEventListener('click', async () => {
    const messageContainer = document.getElementById('messageContainer');
    const btn = document.getElementById('requestPaymentBtn');
    
    // Clear previous messages
    messageContainer.innerHTML = '';
    
    // Validate balance
    if (currentBalance <= 0) {
        showError('No available balance to request payment.');
        return;
    }
    
    // Confirm with user
    if (!confirm(`Request payment of $${formatNumber(currentBalance)}?`)) {
        return;
    }
    
    // Disable button
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
    
    try {
        const response = await API.post('royalties/request-payment', {
            amount: currentBalance
        });
        
        if (response.success) {
            showSuccess(`Payment request of $${formatNumber(currentBalance)} submitted successfully. Status: Pending.`);
            
            // Reload data after 2 seconds
            setTimeout(() => {
                loadRoyaltyData();
            }, 2000);
        } else {
            showError(response.message || 'Failed to submit payment request');
        }
    } catch (error) {
        console.error('Error requesting payment:', error);
        showError(error.message || 'Failed to submit payment request. Please try again.');
    } finally {
        // Re-enable button
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-coin"></i> Request Payment';
    }
});

/**
 * Show success message
 */
function showSuccess(message) {
    const container = document.getElementById('messageContainer');
    container.innerHTML = `<p class="text-success">${escapeHtml(message)}</p>`;
}

/**
 * Show error message
 */
function showError(message) {
    const container = document.getElementById('messageContainer');
    container.innerHTML = `<p class="text-danger">${escapeHtml(message)}</p>`;
}

