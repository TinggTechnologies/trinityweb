<?php
/**
 * Admin Dashboard Controller
 * Handles admin dashboard data and statistics
 */

class AdminDashboardController {
    /**
     * Get dashboard statistics
     * GET /api/admin/dashboard/stats
     */
    public static function getStats() {
        AdminAuthMiddleware::authenticate();
        
        $adminModel = new Admin();
        $stats = $adminModel->getDashboardStats();
        
        Response::success($stats);
    }
    
    /**
     * Get recent activities
     * GET /api/admin/dashboard/activities
     */
    public static function getRecentActivities() {
        AdminAuthMiddleware::authenticate();
        
        $db = Database::getInstance()->getConnection();
        
        // Get recent releases
        $stmt = $db->prepare("
            SELECT 
                r.id,
                r.release_title,
                r.status,
                r.created_at,
                u.first_name,
                u.last_name,
                u.stage_name
            FROM releases r
            INNER JOIN users u ON r.user_id = u.id
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $recent_releases = $stmt->fetchAll();
        
        // Get recent users
        $stmt = $db->prepare("
            SELECT 
                id,
                first_name,
                last_name,
                email,
                stage_name,
                created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $recent_users = $stmt->fetchAll();
        
        // Get recent tickets
        $stmt = $db->prepare("
            SELECT 
                st.id,
                st.subject,
                st.status,
                st.created_at,
                u.first_name,
                u.last_name,
                u.email
            FROM support_tickets st
            INNER JOIN users u ON st.user_id = u.id
            ORDER BY st.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $recent_tickets = $stmt->fetchAll();
        
        Response::success([
            'recent_releases' => $recent_releases,
            'recent_users' => $recent_users,
            'recent_tickets' => $recent_tickets
        ]);
    }

    /**
     * Get chart data for last 30 days
     * GET /api/admin/dashboard/chart-data
     */
    public static function getChartData() {
        AdminAuthMiddleware::authenticate();

        $db = Database::getInstance()->getConnection();
        $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));

        // User registrations by day
        $stmt = $db->prepare("
            SELECT DATE(created_at) as day, COUNT(*) as user_count
            FROM users
            WHERE created_at >= :thirty_days_ago
            GROUP BY DATE(created_at)
            ORDER BY day
        ");
        $stmt->execute(['thirty_days_ago' => $thirty_days_ago]);
        $user_registrations = $stmt->fetchAll();

        // Release submissions by day
        $stmt = $db->prepare("
            SELECT DATE(created_at) as day, COUNT(*) as release_count
            FROM releases
            WHERE created_at >= :thirty_days_ago
            GROUP BY DATE(created_at)
            ORDER BY day
        ");
        $stmt->execute(['thirty_days_ago' => $thirty_days_ago]);
        $release_submissions = $stmt->fetchAll();

        // Ticket submissions by day
        $stmt = $db->prepare("
            SELECT DATE(created_at) as day, COUNT(*) as ticket_count
            FROM help_tickets
            WHERE created_at >= :thirty_days_ago
            GROUP BY DATE(created_at)
            ORDER BY day
        ");
        $stmt->execute(['thirty_days_ago' => $thirty_days_ago]);
        $ticket_submissions = $stmt->fetchAll();

        // Generate all dates for the last 30 days
        $chart_data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $formatted_date = date('M j', strtotime($date));
            $chart_data[$date] = [
                'user' => 0,
                'releases' => 0,
                'tickets' => 0,
                'day' => $formatted_date
            ];
        }

        // Fill user data
        foreach ($user_registrations as $registration) {
            $day = $registration['day'];
            if (isset($chart_data[$day])) {
                $chart_data[$day]['user'] = (int)$registration['user_count'];
            }
        }

        // Fill release data
        foreach ($release_submissions as $submission) {
            $day = $submission['day'];
            if (isset($chart_data[$day])) {
                $chart_data[$day]['releases'] = (int)$submission['release_count'];
            }
        }

        // Fill ticket data
        foreach ($ticket_submissions as $submission) {
            $day = $submission['day'];
            if (isset($chart_data[$day])) {
                $chart_data[$day]['tickets'] = (int)$submission['ticket_count'];
            }
        }

        // Convert to array for JSON response
        $chart_data = array_values($chart_data);

        Response::success($chart_data);
    }
}

