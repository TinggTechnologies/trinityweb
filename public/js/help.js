/**
 * Help Page JavaScript
 * Handles help ticket submission and display
 */

// Store releases data
let userReleases = [];

// Load tickets on page load
document.addEventListener('DOMContentLoaded', () => {
    loadTickets();
    loadReleases();
    setupFormHandler();
    setupReleaseDropdown();
});

/**
 * Load user's releases for the UPC dropdown
 */
async function loadReleases() {
    try {
        const response = await API.get('releases');

        if (response.success && response.data.releases) {
            userReleases = response.data.releases;
            populateReleaseDropdown(userReleases);
        }
    } catch (error) {
        console.error('Error loading releases:', error);
    }
}

/**
 * Populate release dropdown
 */
function populateReleaseDropdown(releases) {
    const select = document.getElementById('releaseSelect');
    if (!select) return;

    // Clear existing options except the first one
    select.innerHTML = '<option value="">-- Select a release --</option>';

    releases.forEach(release => {
        const option = document.createElement('option');
        option.value = release.upc || '';
        option.textContent = `${release.release_title} (UPC: ${release.upc || 'N/A'})`;
        option.dataset.releaseId = release.id;
        select.appendChild(option);
    });
}

/**
 * Setup release dropdown change handler
 */
function setupReleaseDropdown() {
    const select = document.getElementById('releaseSelect');
    const upcInput = document.getElementById('upc');

    if (select && upcInput) {
        select.addEventListener('change', function() {
            upcInput.value = this.value;
        });
    }
}

/**
 * Load user's help tickets
 */
async function loadTickets() {
    try {
        const response = await API.get('help-tickets');

        if (response.success) {
            displayTickets(response.data.tickets);
        } else {
            showError('Failed to load tickets');
        }
    } catch (error) {
        console.error('Error loading tickets:', error);
        displayTickets([]);
    }
}

/**
 * Display tickets in table
 */
function displayTickets(tickets) {
    const tbody = document.getElementById('ticketsTableBody');

    if (!tickets || tickets.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center">No help requests found. Submit your first request above!</td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = tickets.map(ticket => {
        const statusBadge = getStatusBadge(ticket.status);
        const date = formatDate(ticket.created_at);

        return `
            <tr>
                <td>#${ticket.id}</td>
                <td>${ticket.upc_code || 'N/A'}</td>
                <td>${escapeHtml(ticket.subject)}</td>
                <td>${statusBadge}</td>
                <td>${date}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="viewTicket(${ticket.id})" title="View Conversation">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTicket(${ticket.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Get status badge HTML
 */
function getStatusBadge(status) {
    const badges = {
        'open': '<span class="badge bg-warning">Open</span>',
        'in_progress': '<span class="badge bg-primary">In Progress</span>',
        'in-progress': '<span class="badge bg-primary">In Progress</span>',
        'resolved': '<span class="badge bg-success">Resolved</span>',
        'closed': '<span class="badge bg-secondary">Closed</span>',
        'pending': '<span class="badge bg-info">Pending</span>'
    };

    return badges[status] || `<span class="badge bg-secondary">${status || 'Unknown'}</span>`;
}

/**
 * Format date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('en-US', options);
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
 * Setup form submission handler
 */
function setupFormHandler() {
    const form = document.getElementById('helpForm');
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const subject = document.getElementById('subject').value.trim();
        const message = document.getElementById('message').value.trim();
        const upcCode = document.getElementById('upc').value.trim();
        
        // Validate
        if (!subject) {
            document.getElementById('subject').classList.add('is-invalid');
            return;
        } else {
            document.getElementById('subject').classList.remove('is-invalid');
        }
        
        if (!message || message.length < 10) {
            document.getElementById('message').classList.add('is-invalid');
            return;
        } else {
            document.getElementById('message').classList.remove('is-invalid');
        }
        
        // Submit ticket
        try {
            const response = await API.post('help-tickets', {
                subject: subject,
                message: message,
                upc_code: upcCode || null
            });
            
            if (response.success) {
                showSuccess(response.message || 'Your help request has been submitted successfully! Our team will get back to you soon.');
                form.reset();
                loadTickets(); // Reload tickets
                
                // Scroll to success message
                document.getElementById('successMessage').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center'
                });
            } else {
                showError(response.message || 'Failed to submit help request');
            }
        } catch (error) {
            console.error('Error submitting ticket:', error);
            showError('Failed to submit help request. Please try again.');
        }
    });
}

/**
 * Delete a ticket
 */
async function deleteTicket(ticketId) {
    if (!confirm('Are you sure you want to delete this help request?')) {
        return;
    }
    
    try {
        const response = await API.delete(`help-tickets/${ticketId}`);
        
        if (response.success) {
            showSuccess('Help request deleted successfully');
            loadTickets(); // Reload tickets
        } else {
            showError(response.message || 'Failed to delete help request');
        }
    } catch (error) {
        console.error('Error deleting ticket:', error);
        showError('Failed to delete help request. Please try again.');
    }
}

/**
 * Show success message
 */
function showSuccess(message) {
    const successDiv = document.getElementById('successMessage');
    const successText = document.getElementById('successText');
    successText.textContent = message;
    successDiv.style.display = 'block';

    setTimeout(() => {
        successDiv.style.display = 'none';
    }, 5000);
}

/**
 * Show error message
 */
function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');
    errorText.textContent = message;
    errorDiv.style.display = 'block';

    setTimeout(() => {
        errorDiv.style.display = 'none';
    }, 5000);
}

/**
 * View ticket conversation
 */
async function viewTicket(ticketId) {
    try {
        const response = await API.get(`help-tickets/${ticketId}`);

        if (response.success) {
            showConversationModal(response.data);
        } else {
            showError('Failed to load ticket details');
        }
    } catch (error) {
        console.error('Error loading ticket:', error);
        showError('Failed to load ticket details');
    }
}

/**
 * Show conversation modal
 */
function showConversationModal(ticket) {
    // Remove existing modal if any
    const existingModal = document.getElementById('conversationModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Build messages HTML
    let messagesHtml = '';

    // First show the original message from the ticket
    messagesHtml += `
        <div class="message-item user-message mb-3">
            <div class="d-flex align-items-center mb-1">
                <strong class="text-primary">You</strong>
                <small class="text-muted ms-2">${formatDate(ticket.created_at)}</small>
            </div>
            <div class="message-content bg-light p-3 rounded">
                ${escapeHtml(ticket.message)}
            </div>
        </div>
    `;

    // Then show follow-up messages
    if (ticket.messages && ticket.messages.length > 0) {
        ticket.messages.forEach(msg => {
            const isAdmin = msg.is_admin == 1 || msg.sender_type === 'Admin';
            const senderName = isAdmin ? 'Support Team' : 'You';
            const messageClass = isAdmin ? 'admin-message' : 'user-message';
            const bgClass = isAdmin ? 'bg-danger text-white' : 'bg-light';

            messagesHtml += `
                <div class="message-item ${messageClass} mb-3">
                    <div class="d-flex align-items-center mb-1">
                        <strong class="${isAdmin ? 'text-danger' : 'text-primary'}">${senderName}</strong>
                        <small class="text-muted ms-2">${formatDate(msg.created_at)}</small>
                    </div>
                    <div class="message-content ${bgClass} p-3 rounded">
                        ${escapeHtml(msg.message)}
                    </div>
                </div>
            `;
        });
    }

    if (!ticket.messages || ticket.messages.length === 0) {
        messagesHtml += `
            <div class="text-center text-muted py-3">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <p>Awaiting response from our support team...</p>
            </div>
        `;
    }

    // Check if ticket is closed
    const isClosed = ticket.status === 'closed' || ticket.status === 'resolved';

    // Reply form HTML (only show if ticket is not closed)
    const replyFormHtml = isClosed ? `
        <div class="alert alert-secondary mt-3 mb-0">
            <i class="fas fa-lock me-2"></i>
            This ticket is closed. If you need further assistance, please create a new ticket.
        </div>
    ` : `
        <div class="reply-form mt-3 pt-3 border-top">
            <h6 class="mb-3"><i class="fas fa-reply me-2"></i>Reply to Support</h6>
            <div class="form-group">
                <textarea class="form-control" id="replyMessage" rows="3" placeholder="Type your response here..."></textarea>
            </div>
            <div class="mt-2 text-end">
                <button type="button" class="btn btn-primary" id="sendReplyBtn" onclick="sendReply(${ticket.id})">
                    <i class="fas fa-paper-plane me-1"></i> Send Reply
                </button>
            </div>
        </div>
    `;

    // Create modal HTML
    const modalHtml = `
        <div class="modal fade" id="conversationModal" tabindex="-1" aria-labelledby="conversationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="conversationModalLabel">
                            <i class="fas fa-ticket-alt me-2"></i>
                            Ticket #${ticket.id}: ${escapeHtml(ticket.subject)}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="ticket-info mb-3 p-3 bg-light rounded">
                            <div class="row">
                                <div class="col-md-4">
                                    <small class="text-muted">Status:</small>
                                    <div>${getStatusBadge(ticket.status)}</div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">UPC Code:</small>
                                    <div>${ticket.upc_code || 'N/A'}</div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Created:</small>
                                    <div>${formatDate(ticket.created_at)}</div>
                                </div>
                            </div>
                        </div>
                        <h6 class="mb-3">Conversation</h6>
                        <div class="messages-container" id="messagesContainer" style="max-height: 300px; overflow-y: auto;">
                            ${messagesHtml}
                        </div>
                        ${replyFormHtml}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('conversationModal'));
    modal.show();

    // Scroll to bottom of messages
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
}

/**
 * Send reply to a ticket
 */
async function sendReply(ticketId) {
    const messageInput = document.getElementById('replyMessage');
    const sendBtn = document.getElementById('sendReplyBtn');
    const message = messageInput.value.trim();

    if (!message) {
        messageInput.classList.add('is-invalid');
        return;
    }

    messageInput.classList.remove('is-invalid');

    // Disable button while sending
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending...';

    try {
        const response = await API.post(`help-tickets/${ticketId}/reply`, {
            message: message
        });

        if (response.success) {
            // Close modal and reload ticket
            const modal = bootstrap.Modal.getInstance(document.getElementById('conversationModal'));
            modal.hide();

            // Show success message
            showSuccess('Reply sent successfully!');

            // Reload the ticket to show the new message
            setTimeout(() => viewTicket(ticketId), 500);
        } else {
            showError(response.message || 'Failed to send reply');
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send Reply';
        }
    } catch (error) {
        console.error('Error sending reply:', error);
        showError('Failed to send reply. Please try again.');
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send Reply';
    }
}

