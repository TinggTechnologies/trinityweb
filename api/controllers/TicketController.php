<?php
/**
 * Ticket Controller
 * Handles support ticket operations
 */

class TicketController {
    /**
     * Get all tickets for current user
     */
    public static function getAllTickets() {
        $userId = AuthMiddleware::authenticate();
        
        $ticketModel = new Ticket();
        $tickets = $ticketModel->getByUserId($userId);
        
        Response::success($tickets);
    }
    
    /**
     * Get single ticket with messages
     */
    public static function getTicket($id) {
        $userId = AuthMiddleware::authenticate();
        
        $ticketModel = new Ticket();
        $ticket = $ticketModel->findById($id);
        
        if (!$ticket) {
            Response::notFound('Ticket not found');
        }
        
        // Check ownership
        if ($ticket['user_id'] != $userId) {
            Response::forbidden('You do not have access to this ticket');
        }
        
        // Get messages
        $ticket['messages'] = $ticketModel->getMessages($id);
        
        Response::success($ticket);
    }
    
    /**
     * Create new ticket
     */
    public static function createTicket() {
        $userId = AuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator();
        $validator->required('subject', $data['subject'] ?? '')
                  ->required('message', $data['message'] ?? '');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $ticketModel = new Ticket();
        $ticketId = $ticketModel->create($userId, $data['subject'], $data['message']);
        
        Response::success(['ticket_id' => $ticketId], 'Ticket created successfully', 201);
    }
    
    /**
     * Add message to ticket
     */
    public static function addMessage($id) {
        $userId = AuthMiddleware::authenticate();
        
        $ticketModel = new Ticket();
        $ticket = $ticketModel->findById($id);
        
        if (!$ticket) {
            Response::notFound('Ticket not found');
        }
        
        if ($ticket['user_id'] != $userId) {
            Response::forbidden('You do not have access to this ticket');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator();
        $validator->required('message', $data['message'] ?? '');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $ticketModel->addMessage($id, $data['message'], 'User');
        
        Response::success(null, 'Message added successfully');
    }
}

