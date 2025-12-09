<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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
    echo json_encode(["success" => false, "message" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

// Check if user is authenticated
function checkAuth() {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Unauthorized - Please log in"]);
        exit();
    }
    return $_SESSION['user_id'];
}

// Get all songs with filters and pagination
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $user_id = checkAuth();
        
        // Get query parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 20;
        $offset = ($page - 1) * $limit;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
        $type = isset($_GET['type']) ? $_GET['type'] : 'song'; // song or video
        
        // Build base query
        $query = "
            SELECT 
                t.id,
                t.title as song_title,
                t.isrc_code as ISRC,
                t.duration,
                t.explicit,
                r.upc_code as UPC,
                r.title as release_title,
                r.release_date,
                r.created_at as date,
                r.id as release_id,
                GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') as artist_names,
                GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as user_names
            FROM tracks t
            JOIN releases r ON t.release_id = r.id
            JOIN users u ON r.user_id = u.id
            LEFT JOIN track_artists ta ON t.id = ta.track_id
            LEFT JOIN artists a ON ta.artist_id = a.id
        ";
        
        $count_query = "
            SELECT COUNT(DISTINCT t.id) as total
            FROM tracks t
            JOIN releases r ON t.release_id = r.id
            JOIN users u ON r.user_id = u.id
        ";
        
        $where_conditions = [];
        $params = [];
        
        // Add search conditions
        if (!empty($search)) {
            $where_conditions[] = "(
                t.title LIKE :search OR 
                r.title LIKE :search OR 
                a.name LIKE :search OR 
                u.first_name LIKE :search OR 
                u.last_name LIKE :search OR
                t.isrc_code LIKE :search OR
                r.upc_code LIKE :search
            )";
            $params[':search'] = "%$search%";
        }
        
        // Add date filters
        if (!empty($start_date)) {
            $where_conditions[] = "r.created_at >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        
        if (!empty($end_date)) {
            $where_conditions[] = "r.created_at <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        
        // Add type filter (for future video support)
        if ($type === 'video') {
            $where_conditions[] = "r.type = 'video'";
        } else {
            $where_conditions[] = "(r.type IS NULL OR r.type = 'audio' OR r.type = 'song')";
        }
        
        // Add WHERE clause if conditions exist
        if (!empty($where_conditions)) {
            $where_clause = " WHERE " . implode(" AND ", $where_conditions);
            $query .= $where_clause;
            $count_query .= $where_clause;
        }
        
        // Add GROUP BY and ORDER BY
        $query .= " GROUP BY t.id ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";
        
        // Get total count
        $stmt = $pdo->prepare($count_query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $total_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_rows = $total_result['total'];
        
        // Get paginated data
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the response
        $formatted_songs = [];
        foreach ($songs as $song) {
            $formatted_songs[] = [
                'id' => $song['id'],
                'date' => date('d-m-Y', strtotime($song['date'])),
                'artistName' => $song['artist_names'] ?: $song['user_names'],
                'songtitle' => $song['song_title'],
                'ISRC' => $song['ISRC'] ?: 'N/A',
                'UPC' => $song['UPC'] ?: 'N/A',
                'CatalogID' => $song['release_id'],
                'releaseDate' => $song['release_date'] ? date('d-m-Y', strtotime($song['release_date'])) : 'N/A',
                'duration' => $song['duration'],
                'explicit' => $song['explicit'] ? 'Yes' : 'No'
            ];
        }
        
        // Return response
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "data" => $formatted_songs,
            "pagination" => [
                "current_page" => $page,
                "per_page" => $limit,
                "total_rows" => $total_rows,
                "total_pages" => ceil($total_rows / $limit)
            ],
            "filters" => [
                "search" => $search,
                "start_date" => $start_date,
                "end_date" => $end_date,
                "type" => $type
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to fetch songs: " . $e->getMessage()]);
    }
}

// Handle other methods
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle song creation
    http_response_code(501);
    echo json_encode(["success" => false, "message" => "Create song endpoint not implemented yet"]);
}

else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Handle song update
    http_response_code(501);
    echo json_encode(["success" => false, "message" => "Update song endpoint not implemented yet"]);
}

else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Handle song deletion
    http_response_code(501);
    echo json_encode(["success" => false, "message" => "Delete song endpoint not implemented yet"]);
}

else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
}
exit();
?>