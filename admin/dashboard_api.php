<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'trinity');

// Connect to database
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

// Check if user is authenticated
function checkAuth() {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["message" => "Unauthorized"]);
        exit();
    }
    return $_SESSION['user_id'];
}

// Get dashboard statistics
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
   $user_id = checkAuth();
    
    try {
        // Get total users count
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users");
        $stmt->execute();
        $users_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
        
        // Get total songs count (tracks)
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_songs FROM tracks");
        $stmt->execute();
        $songs_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_songs'];
        
        // Get total tickets count
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_tickets FROM help_tickets");
        $stmt->execute();
        $tickets_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_tickets'];
        
        // Get total releases count
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_releases FROM releases");
        $stmt->execute();
        $releases_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_releases'];
        
        // Get total artists count
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_artists FROM artists");
        $stmt->execute();
        $artists_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_artists'];
        
        // Get total royalties amount
        $stmt = $pdo->prepare("SELECT SUM(amount) as total_royalties FROM royalties WHERE status = 'paid'");
        $stmt->execute();
        $royalties_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_royalties'] ?? 0;
        
        // Get user and submission data for the last 30 days
        $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
        
        // User registrations by day
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as day, COUNT(*) as user_count 
            FROM users 
            WHERE created_at >= :thirty_days_ago 
            GROUP BY DATE(created_at)
            ORDER BY day
        ");
        $stmt->execute(['thirty_days_ago' => $thirty_days_ago]);
        $user_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Song submissions by day (using releases as proxy)
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as day, COUNT(*) as song_count 
            FROM releases 
            WHERE created_at >= :thirty_days_ago 
            GROUP BY DATE(created_at)
            ORDER BY day
        ");
        $stmt->execute(['thirty_days_ago' => $thirty_days_ago]);
        $song_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ticket submissions by day
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as day, COUNT(*) as ticket_count 
            FROM help_tickets 
            WHERE created_at >= :thirty_days_ago 
            GROUP BY DATE(created_at)
            ORDER BY day
        ");
        $stmt->execute(['thirty_days_ago' => $thirty_days_ago]);
        $ticket_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine the data for chart
        $chart_data = [];
        $dates = [];
        
        // Generate all dates for the last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dates[] = $date;
            $formatted_date = date('M j', strtotime($date));
            $chart_data[$date] = [
                'user' => 0,
                'songs' => 0,
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
        
        // Fill song data
        foreach ($song_submissions as $submission) {
            $day = $submission['day'];
            if (isset($chart_data[$day])) {
                $chart_data[$day]['songs'] = (int)$submission['song_count'];
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
        $chart_data_array = array_values($chart_data);
        
        // Return dashboard data
        http_response_code(200);
        echo json_encode([
            "analytics" => [
                [
                    "label" => "Users",
                    "icon" => "Man",
                    "value" => $users_count
                ],
                [
                    "label" => "Songs",
                    "icon" => "Headset",
                    "value" => $songs_count
                ],
                [
                    "label" => "Tickets",
                    "icon" => "ConnectWithoutContact",
                    "value" => $tickets_count
                ],
                [
                    "label" => "Releases",
                    "icon" => "DirectionsRun",
                    "value" => $releases_count
                ],
                [
                    "label" => "Artists",
                    "icon" => "Woman",
                    "value" => $artists_count
                ],
                [
                    "label" => "Royalties",
                    "icon" => "BabyChangingStation",
                    "value" => number_format($royalties_amount, 2)
                ]
            ],
            "chartData" => $chart_data_array
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Failed to fetch dashboard data: " . $e->getMessage()]);
        exit();
    }
}

// If method not supported
http_response_code(405);
echo json_encode(["message" => "Method not allowed"]);
exit();
?>