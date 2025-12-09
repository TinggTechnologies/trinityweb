/**
 * Admin Messaging/Support Tickets Page
 */

// Use AppConfig if available, otherwise fallback
const API_BASE_URL = (typeof AppConfig !== 'undefined')
    ? AppConfig.apiUrl
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? 'http://localhost/trinity/api'
        : 'https://trinity.futurewebhost.com.ng/api';

let ticketsTable;

// Load tickets on page load
document.addEventListener('DOMContentLoaded', function() {
    loadTickets();

    // Refresh button
    document.getElementById('refreshBtn').addEventListener('click', function() {
        loadTickets();
    });

    // Reply form submission
    document.getElementById('replyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        sendReply();
    });

    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });
    }
});

/**
 * Load all tickets
 */
function loadTickets() {
    fetch(`${API_BASE_URL}/admin/tickets`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateAnalytics(data.data.analytics);
                populateTicketsTable(data.data.tickets);
            } else {
                showMessage('Error loading tickets: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading tickets:', error);
            showMessage('Failed to load tickets. Please try again.', 'danger');
        });
}

/**
 * Populate analytics cards
 */
function populateAnalytics(analytics) {
    const cards = [
        { label: 'Total Tickets', value: analytics.total_tickets || 0, icon: 'comments' },
        { label: 'Open Tickets', value: analytics.open_tickets || 0, icon: 'folder-open' },
        { label: 'Closed Tickets', value: analytics.closed_tickets || 0, icon: 'check-circle' },
        { label: 'Pending Response', value: analytics.pending_response || 0, icon: 'clock' }
    ];

    document.getElementById('analyticsCards').innerHTML = cards.map(card => `
        <div class="analysis-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="no-gap secondary-text">${card.label}</p>
                    <h4 class="no-gap card-value text-danger mb-0">${card.value.toLocaleString()}</h4>
                </div>
                <div class="analysis-icon text-danger">
                    <i class="fas fa-${card.icon}"></i>
                </div>
            </div>
        </div>
    `).join('');
}

/**
 * Populate tickets table
 */
function populateTicketsTable(tickets) {
    // Destroy existing DataTable first
    if ($.fn.DataTable.isDataTable('#ticketsTable')) {
        $('#ticketsTable').DataTable().destroy();
    }
    
    const tbody = document.getElementById('ticketsTableBody');
    
    if (tickets.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No support tickets found.</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = tickets.map(ticket => {
        const createdDate = ticket.created_at ? new Date(ticket.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
        const lastMessageDate = ticket.last_message_at ? new Date(ticket.last_message_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : createdDate;
        const userName = ticket.user_name || ticket.stage_name || `${ticket.first_name || ''} ${ticket.last_name || ''}`.trim() || 'Unknown User';
        const statusClass = getStatusBadgeClass(ticket.status);
        
        return `
            <tr>
                <td>#${ticket.id}</td>
                <td>${escapeHtml(userName)}</td>
                <td>${escapeHtml(ticket.subject || 'No Subject')}</td>
                <td><span class="badge ${statusClass} status-badge">${escapeHtml(ticket.status || 'Open')}</span></td>
                <td>${createdDate}</td>
                <td>${lastMessageDate}</td>
                <td>
                    <div class="dropdown action-dropdown">
                        <button class="btn btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="viewConversation(${ticket.id}); return false;">
                                <i class="fas fa-eye text-primary me-2"></i> View Conversation
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateTicketStatus(${ticket.id}, 'Open'); return false;">
                                <i class="fas fa-folder-open text-success me-2"></i> Mark as Open
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateTicketStatus(${ticket.id}, 'Closed'); return false;">
                                <i class="fas fa-check-circle text-secondary me-2"></i> Mark as Closed
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteTicket(${ticket.id}, '${escapeHtml(ticket.subject)}'); return false;">
                                <i class="fas fa-trash me-2"></i> Delete
                            </a></li>
                        </ul>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    // Initialize DataTables after populating
    setTimeout(() => {
        ticketsTable = $('#ticketsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            language: {
                search: "",
                searchPlaceholder: "Search tickets..."
            }
        });
    }, 100);
}

/**
 * View conversation
 */
function viewConversation(ticketId) {
    fetch(`${API_BASE_URL}/admin/tickets/${ticketId}/messages`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ticket = data.data.ticket;
                const messages = data.data.messages;
                
                document.getElementById('convSubject').textContent = ticket.subject;
                document.getElementById('convMeta').textContent = `Ticket #${ticket.id} - Created: ${new Date(ticket.created_at).toLocaleString()}`;
                document.getElementById('replyTicketId').value = ticketId;

                // Display messages
                const messagesContainer = document.getElementById('conversationMessages');
                messagesContainer.innerHTML = messages.map(msg => {
                    const isAdmin = msg.sender_type === 'admin';
                    const bubbleClass = isAdmin ? 'message-admin' : 'message-user';
                    const senderName = isAdmin ? 'Admin' : 'User';
                    const messageTime = new Date(msg.created_at).toLocaleString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    return `
                        <div class="message-bubble ${bubbleClass}">
                            <div class="message-sender">${senderName}</div>
                            <div>${escapeHtml(msg.message)}</div>
                            <div class="message-time">${messageTime}</div>
                        </div>
                    `;
                }).join('');

                // Scroll to bottom
                messagesContainer.scrollTop = messagesContainer.scrollHeight;

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('viewConversationModal'));
                modal.show();
            } else {
                showMessage('Error loading conversation: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading conversation:', error);
            showMessage('Failed to load conversation. Please try again.', 'danger');
        });
}

/**
 * Send reply
 */
function sendReply() {
    const ticketId = document.getElementById('replyTicketId').value;
    const message = document.getElementById('replyMessage').value.trim();

    if (!message) {
        showMessage('Please enter a message', 'warning');
        return;
    }

    fetch(`${API_BASE_URL}/admin/tickets/${ticketId}/reply`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ message: message })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Reply sent successfully', 'success');
            document.getElementById('replyMessage').value = '';
            // Reload conversation
            viewConversation(ticketId);
        } else {
            showMessage('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error sending reply:', error);
        showMessage('Failed to send reply. Please try again.', 'danger');
    });
}

/**
 * Update ticket status
 */
function updateTicketStatus(ticketId, status) {
    if (!confirm(`Are you sure you want to mark this ticket as ${status}?`)) {
        return;
    }

    fetch(`${API_BASE_URL}/admin/tickets/${ticketId}/status`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ status: status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(`Ticket marked as ${status}`, 'success');
            loadTickets();
        } else {
            showMessage('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        showMessage('Failed to update status. Please try again.', 'danger');
    });
}

/**
 * Delete ticket
 */
function deleteTicket(ticketId, subject) {
    if (!confirm(`Are you sure you want to delete ticket "${subject}"? This action cannot be undone.`)) {
        return;
    }

    fetch(`${API_BASE_URL}/admin/tickets/${ticketId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Ticket deleted successfully', 'success');
            loadTickets();
        } else {
            showMessage('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error deleting ticket:', error);
        showMessage('Failed to delete ticket. Please try again.', 'danger');
    });
}

// Utility functions
function getStatusBadgeClass(status) {
    const statusMap = {
        'Open': 'bg-success',
        'Closed': 'bg-secondary',
        'Pending': 'bg-warning'
    };
    return statusMap[status] || 'bg-secondary';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showMessage(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    const container = document.querySelector('.main-content');
    container.insertBefore(alertDiv, container.firstChild);

    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}


