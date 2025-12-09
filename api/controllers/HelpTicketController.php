<?php
/**
 * HelpTicket Controller
 * Handles help ticket API requests
 */

require_once __DIR__ . '/../models/HelpTicket.php';

class HelpTicketController {
    
    /**
     * Get all tickets for the authenticated user
     * GET /api/help-tickets
     */
    public static function getTickets() {
        $userId = AuthMiddleware::authenticate();
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $ticketModel = new HelpTicket();
        $tickets = $ticketModel->getUserTickets($userId, $limit, $offset);
        $total = $ticketModel->getUserTicketCount($userId);
        
        Response::success([
            'tickets' => $tickets,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Get a single ticket with messages
     * GET /api/help-tickets/{id}
     */
    public static function getTicket($ticketId) {
        $userId = AuthMiddleware::authenticate();

        $ticketModel = new HelpTicket();
        $ticket = $ticketModel->getTicket($ticketId, $userId);

        if (!$ticket) {
            Response::error('Ticket not found', 404);
        }

        // Get messages for this ticket
        $ticket['messages'] = $ticketModel->getMessages($ticketId);

        Response::success($ticket);
    }
    
    /**
     * Create a new help ticket
     * POST /api/help-tickets
     */
    public static function createTicket() {
        $userId = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['subject']) || empty($data['message'])) {
            Response::error('Subject and message are required', 400);
        }
        
        $upcCode = $data['upc_code'] ?? null;
        $subject = trim($data['subject']);
        $message = trim($data['message']);
        
        // Validate subject length
        if (strlen($subject) > 255) {
            Response::error('Subject must be less than 255 characters', 400);
        }
        
        // Validate message length
        if (strlen($message) < 10) {
            Response::error('Message must be at least 10 characters', 400);
        }
        
        $ticketModel = new HelpTicket();
        $ticket = $ticketModel->create($userId, $upcCode, $subject, $message);
        
        Response::success(
            $ticket,
            'Help ticket created successfully. Our team will get back to you soon.',
            201
        );
    }

    /**
     * Reply to a ticket
     * POST /api/help-tickets/{id}/reply
     */
    public static function replyToTicket($ticketId) {
        $userId = AuthMiddleware::authenticate();

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['message'])) {
            Response::error('Message is required', 400);
        }

        $message = trim($data['message']);

        if (strlen($message) < 2) {
            Response::error('Message must be at least 2 characters', 400);
        }

        $ticketModel = new HelpTicket();

        // Verify ticket exists and belongs to user
        $ticket = $ticketModel->getTicket($ticketId, $userId);
        if (!$ticket) {
            Response::error('Ticket not found', 404);
        }

        // Check if ticket is closed
        if ($ticket['status'] === 'closed' || $ticket['status'] === 'resolved') {
            Response::error('Cannot reply to a closed ticket', 400);
        }

        // Add the reply message
        $success = $ticketModel->addMessage($ticketId, $userId, $message, false);

        if (!$success) {
            Response::error('Failed to send reply', 500);
        }

        Response::success(null, 'Reply sent successfully', 201);
    }

    /**
     * Update ticket status
     * PUT /api/help-tickets/{id}
     */
    public static function updateTicket($ticketId) {
        $userId = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['status'])) {
            Response::error('Status is required', 400);
        }
        
        $ticketModel = new HelpTicket();
        
        // Verify ticket exists and belongs to user
        $ticket = $ticketModel->getTicket($ticketId, $userId);
        if (!$ticket) {
            Response::error('Ticket not found', 404);
        }
        
        $success = $ticketModel->updateStatus($ticketId, $userId, $data['status']);
        
        if (!$success) {
            Response::error('Invalid status or failed to update ticket', 400);
        }
        
        Response::success(
            ['id' => $ticketId, 'status' => $data['status']],
            'Ticket status updated successfully'
        );
    }
    
    /**
     * Delete a ticket
     * DELETE /api/help-tickets/{id}
     */
    public static function deleteTicket($ticketId) {
        $userId = AuthMiddleware::authenticate();
        
        $ticketModel = new HelpTicket();
        
        // Verify ticket exists and belongs to user
        $ticket = $ticketModel->getTicket($ticketId, $userId);
        if (!$ticket) {
            Response::error('Ticket not found', 404);
        }
        
        $success = $ticketModel->delete($ticketId, $userId);
        
        if (!$success) {
            Response::error('Failed to delete ticket', 500);
        }
        
        Response::success(
            ['id' => $ticketId],
            'Ticket deleted successfully'
        );
    }
    
    /**
     * Get ticket statistics
     * GET /api/help-tickets/stats
     */
    public static function getStats() {
        $userId = AuthMiddleware::authenticate();
        
        $ticketModel = new HelpTicket();
        $stats = $ticketModel->getUserTicketStats($userId);
        
        Response::success($stats);
    }
}

