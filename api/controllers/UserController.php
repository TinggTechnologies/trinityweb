<?php
/**
 * User Controller
 * Handles user-related operations
 */

class UserController {
    /**
     * Get user profile
     */
    public static function getUser($id) {
        $currentUserId = AuthMiddleware::authenticate();
        
        // Users can only view their own profile unless they're admin
        if ($currentUserId != $id) {
            AuthMiddleware::requireAdmin();
        }
        
        $userModel = new User();
        $user = $userModel->getProfile($id);
        
        if (!$user) {
            Response::notFound('User not found');
        }
        
        unset($user['password']);
        unset($user['verification_token']);
        
        Response::success($user);
    }
    
    /**
     * Update user profile
     */
    public static function updateUser($id) {
        $currentUserId = AuthMiddleware::authenticate();
        
        // Users can only update their own profile
        if ($currentUserId != $id) {
            Response::forbidden('You can only update your own profile');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator();
        $validator->required('first_name', $data['first_name'] ?? '')
                  ->required('last_name', $data['last_name'] ?? '')
                  ->email('email', $data['email'] ?? '');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $userModel = new User();
        
        // Prepare update data
        $updateData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'stage_name' => $data['stage_name'] ?? null,
            'mobile_number' => $data['mobile_number'] ?? null,
            'origin_country' => $data['origin_country'] ?? null,
            'residence_country' => $data['residence_country'] ?? null,
            'artist_bio' => $data['artist_bio'] ?? null
        ];
        
        // Handle password change
        if (!empty($data['new_password'])) {
            if (empty($data['current_password'])) {
                Response::error('Current password is required to change password');
            }
            
            $user = $userModel->findById($id);
            if (!password_verify($data['current_password'], $user['password'])) {
                Response::error('Current password is incorrect');
            }
            
            $updateData['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }
        
        $userModel->update($id, $updateData);
        
        Response::success(null, 'Profile updated successfully');
    }
    
    /**
     * Get all users (admin only)
     */
    public static function getAllUsers() {
        AuthMiddleware::requireAdmin();
        
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? DEFAULT_PAGE_SIZE;
        
        $userModel = new User();
        $users = $userModel->getAll($page, $limit);
        $total = $userModel->count();
        
        Response::success([
            'users' => $users,
            'pagination' => [
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
}

