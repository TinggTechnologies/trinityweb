<?php
/**
 * Analytics Controller
 * Handles analytics and streaming data operations
 */

class AnalyticsController {
    /**
     * Get analytics data
     */
    public static function getAnalytics() {
        $userId = AuthMiddleware::authenticate();

        $analyticsModel = new Analytics();

        // Get stream analytics
        $streamData = $analyticsModel->getStreamAnalytics($userId);

        // Prepare chart data
        $chartData = [];
        foreach ($streamData as $row) {
            $platform = $row['platform'] ?: 'Unknown';
            $date = $row['date'];
            $streams = (int)$row['total_streams'];

            if (!isset($chartData[$platform])) {
                $chartData[$platform] = ['dates' => [], 'streams' => []];
            }

            $chartData[$platform]['dates'][] = $date;
            $chartData[$platform]['streams'][] = $streams;
        }

        Response::success($chartData);
    }

    /**
     * Get top artists with pagination
     */
    public static function getTopArtists() {
        $userId = AuthMiddleware::authenticate();

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $analyticsModel = new Analytics();

        $artists = $analyticsModel->getTopArtists($userId, $limit, $offset);
        $total = $analyticsModel->getTotalArtistsCount($userId);

        Response::success([
            'artists' => $artists,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * Get top tracks with pagination
     */
    public static function getTopTracks() {
        $userId = AuthMiddleware::authenticate();

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $analyticsModel = new Analytics();

        $tracks = $analyticsModel->getTopTracks($userId, $limit, $offset);
        $total = $analyticsModel->getTotalTracksCount($userId);

        Response::success([
            'tracks' => $tracks,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
}

