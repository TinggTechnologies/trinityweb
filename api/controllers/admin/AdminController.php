<?php
/**
 * Admin Controller
 * Handles administrator management
 */

class AdminController {
    /**
     * Get all administrators
     * GET /api/admin/administrators
     */
    public static function getAdministrators() {
        AdminAuthMiddleware::authenticate();
        
        $adminModel = new Admin();
        $administrators = $adminModel->getAll();
        
        Response::success(['administrators' => $administrators]);
    }
    
    /**
     * Get all admin roles
     * GET /api/admin/roles
     */
    public static function getRoles() {
        AdminAuthMiddleware::authenticate();
        
        $adminModel = new Admin();
        $roles = $adminModel->getRoles();
        
        Response::success(['roles' => $roles]);
    }
    
    /**
     * Create new administrator
     * POST /api/admin/administrators
     */
    public static function createAdministrator() {
        AdminAuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator();
        $validator->required('user_id', $data['user_id'] ?? '')
                  ->required('role_id', $data['role_id'] ?? '');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        try {
            $adminModel = new Admin();
            $adminId = $adminModel->create($data['user_id'], $data['role_id']);
            
            Response::success(['admin_id' => $adminId], 'Administrator created successfully', 201);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * Update administrator role
     * PUT /api/admin/administrators/{id}
     */
    public static function updateAdministrator($id) {
        AdminAuthMiddleware::authenticate();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['role_id'])) {
            Response::error('Role ID is required', 400);
        }
        
        $adminModel = new Admin();
        $adminModel->updateRole($id, $data['role_id']);
        
        Response::success(null, 'Administrator updated successfully');
    }
    
    /**
     * Delete administrator
     * DELETE /api/admin/administrators/{id}
     */
    public static function deleteAdministrator($id) {
        AdminAuthMiddleware::authenticate();
        
        $adminModel = new Admin();
        $adminModel->delete($id);
        
        Response::success(null, 'Administrator deleted successfully');
    }
}

