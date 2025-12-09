<?php
// songs.php - Full single-file implementation (backend + frontend)
// Matches DB schema in trinitydistribution_trinity.sql
// - Uses PDO
// - Does NOT manually delete analytics rows (relies on FK ON DELETE CASCADE)
// - Uses correct column names: track_title, isrc, explicit_content, upc, etc.

// ---------------------
// Error reporting (dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ---------------------

session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database config - update if needed
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'trinity');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// map UI status names to DB statuses (if your UI uses different terms)
function mapStatusToDB(string $status): string {
    $map = [
        'submitted'   => 'pending',
        'distributed' => 'live',
        // allow direct DB statuses too
        'draft'       => 'draft',
        'pending'     => 'pending',
        'approved'    => 'approved',
        'rejected'    => 'rejected',
        'live'        => 'live',
        'taken_down'  => 'taken_down',
    ];
    return $map[$status] ?? $status;
}

// Common allowed DB statuses
$ALLOWED_STATUSES = ['draft','pending','approved','rejected','live','taken_down'];

// message to show on page (set by POST handlers)
$message = null;

// ---------------------------
// POST: handle actions
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        // Update release status from a track id -> release
        case 'update_status':
            $song_id = (int)($_POST['song_id'] ?? 0);
            $status_in = $_POST['status'] ?? '';
            $status = mapStatusToDB($status_in);

            if (!in_array($status, $ALLOWED_STATUSES, true)) {
                $message = ['type' => 'danger', 'text' => 'Invalid status'];
                break;
            }

            try {
                $stmt = $pdo->prepare("SELECT release_id FROM tracks WHERE id = ?");
                $stmt->execute([$song_id]);
                $track = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$track) {
                    $message = ['type' => 'danger', 'text' => 'Track not found'];
                    break;
                }

                $stmt = $pdo->prepare("UPDATE releases SET status = ?, updated_at = NOW() WHERE id = ?");
                $ok = $stmt->execute([$status, $track['release_id']]);

                if ($ok) $message = ['type' => 'success', 'text' => 'Status updated successfully'];
                else $message = ['type' => 'danger', 'text' => 'Failed to update status'];
            } catch (PDOException $e) {
                $message = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
            }
            break;

        // Delete single song (track)
        case 'delete_song':
            $song_id = (int)($_POST['song_id'] ?? 0);

            try {
                $pdo->beginTransaction();

                // fetch release_id first
                $getRel = $pdo->prepare("SELECT release_id FROM tracks WHERE id = ?");
                $getRel->execute([$song_id]);
                $track = $getRel->fetch(PDO::FETCH_ASSOC);
                if (!$track) throw new Exception('Track not found');

                $release_id = (int)$track['release_id'];

                // delete track_artists entries (table uses artist_name; no artist_id)
                $delTA = $pdo->prepare("DELETE FROM track_artists WHERE track_id = ?");
                $delTA->execute([$song_id]);

                // Do NOT explicitly delete from analytics table — rely on FK ON DELETE CASCADE.
                // If your DB doesn't have cascade, you could uncomment the following:
                // $delAnalytics = $pdo->prepare("DELETE FROM analytics WHERE track_id = ?");
                // $delAnalytics->execute([$song_id]);

                // delete the track itself
                $delTrack = $pdo->prepare("DELETE FROM tracks WHERE id = ?");
                $delTrack->execute([$song_id]);

                // if no more tracks for the release, delete release and its children
                $countTracks = $pdo->prepare("SELECT COUNT(*) AS cnt FROM tracks WHERE release_id = ?");
                $countTracks->execute([$release_id]);
                $cnt = (int)$countTracks->fetch(PDO::FETCH_ASSOC)['cnt'];

                if ($cnt === 0) {
                    // delete release dependents, if any (release_artists, release_stores, release_analytics)
                    $pdo->prepare("DELETE FROM release_artists WHERE release_id = ?")->execute([$release_id]);
                    $pdo->prepare("DELETE FROM release_stores WHERE release_id = ?")->execute([$release_id]);
                    $pdo->prepare("DELETE FROM release_analytics WHERE release_id = ?")->execute([$release_id]);
                    $pdo->prepare("DELETE FROM releases WHERE id = ?")->execute([$release_id]);
                }

                $pdo->commit();
                $message = ['type' => 'success', 'text' => 'Track deleted successfully'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = ['type' => 'danger', 'text' => 'Error deleting track: ' . $e->getMessage()];
            }
            break;

        // Update song & release fields
        case 'update_song':
            $song_id = (int)($_POST['song_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $isrc = trim($_POST['isrc'] ?? '');
            $upc = trim($_POST['upc'] ?? '');
            $release_date = trim($_POST['release_date'] ?? '');
            $status_in = trim($_POST['status'] ?? '');
            $status = mapStatusToDB($status_in);
            $duration = intval($_POST['duration'] ?? 0);
            $explicit_checked = ($_POST['explicit'] ?? '0');
            // explicit_content uses enum('yes','no','clean')
            $explicit_value = ($explicit_checked === '1' || $explicit_checked === 'yes') ? 'yes' : 'no';

            if ($title === '') {
                $message = ['type' => 'danger', 'text' => 'Title is required'];
                break;
            }

            try {
                $pdo->beginTransaction();

                // Update tracks table
                $stmt = $pdo->prepare("UPDATE tracks SET track_title = ?, isrc = ?, duration = ?, explicit_content = ? WHERE id = ?");
                $stmt->execute([$title, $isrc ?: null, $duration > 0 ? $duration : null, $explicit_value, $song_id]);

                // Update releases (upc, release_date, status) if present
                $stmt = $pdo->prepare("SELECT release_id FROM tracks WHERE id = ?");
                $stmt->execute([$song_id]);
                $track = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($track && !empty($track['release_id'])) {
                    $release_id = (int)$track['release_id'];
                    $update_parts = [];
                    $params = [];

                    if ($upc !== '') {
                        $update_parts[] = "upc = ?";
                        $params[] = $upc;
                    }
                    if ($release_date !== '') {
                        $update_parts[] = "release_date = ?";
                        $params[] = $release_date;
                    }
                    if ($status !== '' && in_array($status, $ALLOWED_STATUSES, true)) {
                        $update_parts[] = "status = ?";
                        $params[] = $status;
                    }

                    if (!empty($update_parts)) {
                        $update_parts[] = "updated_at = NOW()";
                        $params[] = $release_id;
                        $sql = "UPDATE releases SET " . implode(", ", $update_parts) . " WHERE id = ?";
                        $pdo->prepare($sql)->execute($params);
                    }
                }

                $pdo->commit();
                $message = ['type' => 'success', 'text' => 'Song updated successfully'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = ['type' => 'danger', 'text' => 'Error updating song: ' . $e->getMessage()];
            }
            break;

        // Bulk update status for a set of tracks' releases
        case 'bulk_update':
            // Accepts song_ids[] inputs or song_ids JSON string or indexed song_ids[0],song_ids[1]...
            $song_ids = $_POST['song_ids'] ?? [];
            if (is_string($song_ids)) {
                $decoded = json_decode($song_ids, true);
                $song_ids = $decoded ?: [];
            } elseif (!is_array($song_ids)) {
                // build array from indexed fields like song_ids[0]
                $song_ids = array_values(array_filter($_POST, function($k){ return strpos($k, 'song_ids') === 0; }, ARRAY_FILTER_USE_KEY));
            }

            $status_in = $_POST['status'] ?? '';
            $status = mapStatusToDB($status_in);

            if (!in_array($status, $ALLOWED_STATUSES, true)) {
                $message = ['type' => 'danger', 'text' => 'Invalid status'];
                break;
            }
            if (empty($song_ids)) {
                $message = ['type' => 'danger', 'text' => 'No songs selected'];
                break;
            }

            try {
                $pdo->beginTransaction();
                $getRel = $pdo->prepare("SELECT release_id FROM tracks WHERE id = ?");
                $updRelease = $pdo->prepare("UPDATE releases SET status = ?, updated_at = NOW() WHERE id = ?");

                foreach ($song_ids as $sid) {
                    $id = (int)$sid;
                    $getRel->execute([$id]);
                    $track = $getRel->fetch(PDO::FETCH_ASSOC);
                    if ($track && !empty($track['release_id'])) {
                        $updRelease->execute([$status, (int)$track['release_id']]);
                    }
                }

                $pdo->commit();
                $message = ['type' => 'success', 'text' => count($song_ids) . ' songs updated successfully'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = ['type' => 'danger', 'text' => 'Error updating songs: ' . $e->getMessage()];
            }
            break;

        // Bulk delete tracks
        case 'bulk_delete':
            $song_ids = $_POST['song_ids'] ?? [];
            if (is_string($song_ids)) {
                $decoded = json_decode($song_ids, true);
                $song_ids = $decoded ?: [];
            } elseif (!is_array($song_ids)) {
                $song_ids = array_values(array_filter($_POST, function($k){ return strpos($k, 'song_ids') === 0; }, ARRAY_FILTER_USE_KEY));
            }

            if (empty($song_ids)) {
                $message = ['type' => 'danger', 'text' => 'No songs selected'];
                break;
            }

            try {
                $pdo->beginTransaction();

                $getRel = $pdo->prepare("SELECT release_id FROM tracks WHERE id = ?");
                $delTA = $pdo->prepare("DELETE FROM track_artists WHERE track_id = ?");
                $delTrack = $pdo->prepare("DELETE FROM tracks WHERE id = ?");
                $countTracks = $pdo->prepare("SELECT COUNT(*) AS cnt FROM tracks WHERE release_id = ?");
                $delReleaseArtists = $pdo->prepare("DELETE FROM release_artists WHERE release_id = ?");
                $delReleaseStores = $pdo->prepare("DELETE FROM release_stores WHERE release_id = ?");
                $delReleaseAnalytics = $pdo->prepare("DELETE FROM release_analytics WHERE release_id = ?");
                $delRelease = $pdo->prepare("DELETE FROM releases WHERE id = ?");

                foreach ($song_ids as $sid) {
                    $id = (int)$sid;
                    $getRel->execute([$id]);
                    $track = $getRel->fetch(PDO::FETCH_ASSOC);
                    if (!$track) continue;

                    $release_id = (int)$track['release_id'];

                    $delTA->execute([$id]);

                    // rely on FK cascade for analytics (do not manually delete analytics)
                    $delTrack->execute([$id]);

                    // If no other tracks exist for this release, delete release+dependent rows
                    $countTracks->execute([$release_id]);
                    $cnt = (int)$countTracks->fetch(PDO::FETCH_ASSOC)['cnt'];
                    if ($cnt === 0) {
                        $delReleaseArtists->execute([$release_id]);
                        $delReleaseStores->execute([$release_id]);
                        $delReleaseAnalytics->execute([$release_id]);
                        $delRelease->execute([$release_id]);
                    }
                }

                $pdo->commit();
                $message = ['type' => 'success', 'text' => count($song_ids) . ' songs deleted successfully'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = ['type' => 'danger', 'text' => 'Error deleting songs: ' . $e->getMessage()];
            }
            break;

        default:
            // unknown action - ignore
            break;
    }
}

// ---------------------------
// Prepare analytics summary
// ---------------------------
$analytics_data = [];

try {
    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM tracks");
    $total_tracks = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $analytics_data[] = ['label' => 'Total Tracks', 'value' => number_format($total_tracks), 'icon' => 'music'];

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM releases");
    $total_releases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $analytics_data[] = ['label' => 'Total Releases', 'value' => number_format($total_releases), 'icon' => 'compact-disc'];

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM releases WHERE status IN ('pending', 'draft')");
    $pending = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $analytics_data[] = ['label' => 'Pending', 'value' => number_format($pending), 'icon' => 'clock'];

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM releases WHERE status = 'live'");
    $distributed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $analytics_data[] = ['label' => 'Distributed', 'value' => number_format($distributed), 'icon' => 'check-circle'];

    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM artists");
    $total_artists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $analytics_data[] = ['label' => 'Artists', 'value' => number_format($total_artists), 'icon' => 'microphone'];

    $stmt = $pdo->query("SELECT SUM(amount) AS total FROM royalties WHERE status = 'paid'");
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_royalties = $r['total'] ?? 0;
    $analytics_data[] = ['label' => 'Royalties', 'value' => '$' . number_format((float)$total_royalties, 2), 'icon' => 'dollar-sign'];
} catch (PDOException $e) {
    // ignore and show zeros
}

// ---------------------------
// Fetch songs for display
// ---------------------------
$songs = [];
try {
    $sql = "
        SELECT
            t.id AS id,
            t.track_title AS song_title,
            t.isrc AS isrc,
            t.duration AS duration,
            t.explicit_content AS explicit_content,
            r.id AS release_id,
            r.release_title AS release_title,
            r.upc AS upc,
            r.release_date AS release_date,
            r.status AS status,
            r.created_at AS created_at,
            u.id AS user_id,
            u.first_name AS first_name,
            u.last_name AS last_name,
            u.stage_name AS stage_name,
            GROUP_CONCAT(DISTINCT ta.artist_name SEPARATOR ', ') AS all_artists
        FROM tracks t
        LEFT JOIN releases r ON t.release_id = r.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN track_artists ta ON t.id = ta.track_id
        GROUP BY t.id
        ORDER BY r.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching songs: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>All Songs - Trinity Distribution</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Styles -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
:root { --primary-red: #ED3237; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; }
.sidebar { background: var(--primary-red); color: white; height: 100vh; position: fixed; width: 250px; padding-top: 20px; }
.main-content { margin-left: 250px; padding: 20px; }
.analysis-card { background: white; border-radius: 10px; padding: 20px; margin: 10px; }
.status-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
.table-responsive { max-height: 60vh; overflow: auto; }
@media (max-width: 768px) { .sidebar { position: relative; width: 100%; height: auto; } .main-content { margin-left: 0; } }
</style>
</head>
<body>
<!-- Toast container -->
<div class="toast-container position-fixed top-0 end-0 p-3"></div>

<!-- Edit Song Modal -->
<div class="modal fade" id="editSongModal" tabindex="-1" aria-labelledby="editSongModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editSongModalLabel">Edit Song Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editSongForm" method="POST">
            <input type="hidden" id="editSongId" name="song_id">
            <input type="hidden" name="action" value="update_song">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Song Title</label>
                    <input type="text" class="form-control" id="editSongTitle" name="title" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">ISRC Code</label>
                    <input type="text" class="form-control" id="editIsrcCode" name="isrc">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">UPC Code</label>
                    <input type="text" class="form-control" id="editUpcCode" name="upc">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Release Date</label>
                    <input type="date" class="form-control" id="editReleaseDate" name="release_date">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Duration (seconds)</label>
                    <input type="number" class="form-control" id="editDuration" name="duration" min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="editStatus" name="status">
                        <option value="draft">Draft</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="live">Live</option>
                        <option value="taken_down">Taken Down</option>
                    </select>
                </div>
            </div>
            <div class="mb-3 form-check">
                <input class="form-check-input" type="checkbox" id="editExplicit" name="explicit" value="1">
                <label class="form-check-label" for="editExplicit">Explicit Content</label>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="saveSongChanges" type="button" class="btn btn-danger" style="background-color:var(--primary-red); border-color:var(--primary-red);">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- Sidebar -->
<div class="sidebar">
  <div class="p-3">
    <h1 class="h3 mb-0">trinity</h1>
    <p class="mb-0">DISTRIBUTION</p>
  </div>
  <ul class="nav flex-column p-2">
    <li class="nav-item"><a class="nav-link text-white" href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
    <li class="nav-item"><a class="nav-link active text-white" href="#"><i class="fas fa-music"></i> All Songs</a></li>
    <li class="nav-item"><a class="nav-link text-white" href="royalty.php"><i class="fas fa-hand-holding-usd"></i> Royalty Request</a></li>
    <li class="nav-item"><a class="nav-link text-white" href="ticket.php"><i class="fas fa-comments"></i> Messaging</a></li>
    <li class="nav-item"><a class="nav-link text-white" href="users.php"><i class="fas fa-users"></i> Users</a></li>
  </ul>
</div>

<!-- Main content -->
<div class="main-content">
  <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4">
    <div class="container-fluid">
      <div class="ms-auto d-flex align-items-center">
        <span class="me-3 d-none d-md-block">Welcome, <?= htmlspecialchars($_SESSION['first_name'] ?? 'Admin') ?></span>
        <img src="images/admin.jpg" width="60" height="60" class="rounded-circle" alt="Admin">
      </div>
    </div>
  </nav>

  <div class="page-label mb-3 d-flex justify-content-between align-items-center">
    <div>
      <h3 class="text-danger">All Songs</h3>
      <p class="text-muted">Manage all songs submitted for distribution</p>
    </div>
    <div>
      <button class="btn me-2" onclick="bulkAction('distributed')" style="background-color:var(--primary-red); color:white;"><i class="fas fa-check me-1"></i> Mark as Distributed</button>
      <button class="btn btn-danger" onclick="bulkDelete()"><i class="fas fa-trash me-1"></i> Delete Selected</button>
    </div>
  </div>

  <div class="container p-0">
    <?php if (isset($message)): ?>
      <div class="alert alert-<?php echo htmlspecialchars($message['type']); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
      <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($error_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="board-analysis mb-4 d-flex gap-3 flex-wrap">
      <?php foreach ($analytics_data as $item): ?>
        <div class="analysis-card flex-fill" style="min-width:200px;">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <p class="mb-0 text-secondary"><?php echo htmlspecialchars($item['label']); ?></p>
              <h4 class="text-danger mb-0"><?php echo $item['value']; ?></h4>
            </div>
            <div class="text-danger"><i class="fas fa-<?php echo htmlspecialchars($item['icon']); ?> fa-2x"></i></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card p-3 mb-4">
      <div class="row mb-3">
        <div class="col-md-6">
          <ul class="nav nav-tabs">
            <li class="nav-item"><button class="nav-link active">Songs</button></li>
            <li class="nav-item"><button class="nav-link">Videos</button></li>
          </ul>
        </div>
        <div class="col-md-6">
          <div class="d-flex justify-content-end gap-2">
            <input type="date" class="form-control" id="startDate">
            <input type="date" class="form-control" id="endDate">
            <div class="position-relative" style="width:260px;">
              <i class="fas fa-search position-absolute" style="left:12px; top:10px; color:#888"></i>
              <input id="searchInput" type="text" class="form-control ps-5" placeholder="Search...">
            </div>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table id="songsTable" class="table table-striped table-hover">
          <thead class="table-dark">
            <tr>
              <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
              <th>Date</th>
              <th>Artist Name</th>
              <th>Song Title</th>
              <th>ISRC</th>
              <th>UPC</th>
              <th>Duration</th>
              <th>Release Date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($songs)): ?>
              <tr><td colspan="10" class="text-center py-4"><i class="fas fa-music fa-3x text-muted mb-3"></i><p class="text-muted">No songs found in the database.</p></td></tr>
            <?php else: foreach ($songs as $song): 
                $badge_class = 'bg-secondary';
                switch ($song['status']) {
                    case 'draft': $badge_class = 'bg-secondary'; break;
                    case 'pending': $badge_class = 'bg-warning'; break;
                    case 'approved': $badge_class = 'bg-info'; break;
                    case 'live': $badge_class = 'bg-success'; break;
                    case 'rejected': $badge_class = 'bg-danger'; break;
                    case 'taken_down': $badge_class = 'bg-dark'; break;
                }
                $created_date = !empty($song['created_at']) ? date('M d, Y', strtotime($song['created_at'])) : 'N/A';
                $release_date = !empty($song['release_date']) ? date('M d, Y', strtotime($song['release_date'])) : 'Not set';
                $duration_formatted = 'N/A';
                if (!empty($song['duration']) && is_numeric($song['duration'])) {
                    $mins = floor($song['duration'] / 60);
                    $secs = $song['duration'] % 60;
                    $duration_formatted = sprintf('%d:%02d', $mins, $secs);
                }
                if (!empty($song['all_artists'])) $artist_display = $song['all_artists'];
                elseif (!empty($song['stage_name'])) $artist_display = $song['stage_name'];
                elseif (!empty($song['first_name']) || !empty($song['last_name'])) $artist_display = trim($song['first_name'] . ' ' . $song['last_name']);
                else $artist_display = 'Unknown Artist';
            ?>
            <tr>
              <td><input type="checkbox" class="song-checkbox" value="<?= (int)$song['id'] ?>"></td>
              <td><?= htmlspecialchars($created_date) ?></td>
              <td title="<?= htmlspecialchars($artist_display) ?>"><?= htmlspecialchars($artist_display) ?><?php if ($song['explicit_content'] === 'yes'): ?><span class="badge bg-warning ms-1" title="Explicit Content">E</span><?php endif; ?></td>
              <td><?= htmlspecialchars($song['song_title'] ?? 'Untitled') ?></td>
              <td><?= htmlspecialchars($song['isrc'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars($song['upc'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars($duration_formatted) ?></td>
              <td><?= htmlspecialchars($release_date) ?></td>
              <td><span class="badge <?php echo $badge_class; ?> status-badge"><?= htmlspecialchars($song['status'] ?? 'draft') ?></span></td>
              <td>
                <div class="dropdown">
                  <button class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                  <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= (int)$song['id'] ?>, 'approved')"><i class="fas fa-thumbs-up text-info me-2"></i>Mark as Approved</a></li>
                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= (int)$song['id'] ?>, 'rejected')"><i class="fas fa-times text-danger me-2"></i>Mark as Rejected</a></li>
                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= (int)$song['id'] ?>, 'distributed')"><i class="fas fa-check text-success me-2"></i>Mark as Distributed</a></li>
                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= (int)$song['id'] ?>, 'submitted')"><i class="fas fa-clock text-warning me-2"></i>Mark as Pending</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="editSong(<?= (int)$song['id'] ?>)"><i class="fas fa-edit text-primary me-2"></i>Edit</a></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteSong(<?= (int)$song['id'] ?>)"><i class="fas fa-trash me-2"></i>Delete</a></li>
                  </ul>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
const songsData = <?php echo json_encode($songs, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
let dataTable;

$(document).ready(function() {
    dataTable = $('#songsTable').DataTable({
        pageLength: 20,
        lengthMenu: [[20,40,50,100],[20,40,50,100]],
        order: [[1, 'desc']],
        columnDefs: [{ orderable: false, targets: [0, -1] }],
        language: { search: "", searchPlaceholder: "Search songs..." }
    });

    $('#searchInput').on('keyup', function() { dataTable.search(this.value).draw(); });
    $('#saveSongChanges').on('click', saveSongChanges);
});

// Toast helper
function showToast(message, type='success') {
    const id = 't' + Date.now();
    const html = `<div id="${id}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true"><div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div>`;
    $('.toast-container').append(html);
    const el = document.getElementById(id);
    const toast = new bootstrap.Toast(el);
    toast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

// Selection helpers
function toggleSelectAll() {
    const checked = $('#selectAll').is(':checked');
    $('.song-checkbox').prop('checked', checked);
    $('#songsTable tbody tr').toggleClass('selected', checked);
}
$(document).on('change', '.song-checkbox', function() {
    $(this).closest('tr').toggleClass('selected', $(this).is(':checked'));
    const total = $('.song-checkbox').length;
    const checked = $('.song-checkbox:checked').length;
    $('#selectAll').prop('checked', total === checked).prop('indeterminate', checked > 0 && checked < total);
});
function getSelectedSongs() { return $('.song-checkbox:checked').map(function(){ return $(this).val(); }).get(); }

// Map UI statuses to DB statuses (client-side convenience)
function mapStatusForSubmit(status) {
    if (status === 'distributed') return 'live';
    if (status === 'submitted') return 'pending';
    return status;
}

// Update single status (submits POST form)
function updateStatus(songId, status) {
    if (!confirm('Are you sure you want to update this song status?')) return;
    const mapped = mapStatusForSubmit(status);
    const form = $('<form method="POST" style="display:none"></form>');
    form.append('<input name="action" value="update_status">');
    form.append(`<input name="song_id" value="${songId}">`);
    form.append(`<input name="status" value="${mapped}">`);
    $('body').append(form);
    form.submit();
}

// Delete single song
function deleteSong(songId) {
    if (!confirm('Are you sure you want to delete this song? This action cannot be undone.')) return;
    const form = $('<form method="POST" style="display:none"></form>');
    form.append('<input name="action" value="delete_song">');
    form.append(`<input name="song_id" value="${songId}">`);
    $('body').append(form);
    form.submit();
}

// Edit song - populate modal and show
function editSong(songId) {
    const song = songsData.find(s => Number(s.id) === Number(songId));
    if (!song) { showToast('Song data not found', 'danger'); return; }

    $('#editSongId').val(song.id);
    $('#editSongTitle').val(song.song_title || '');
    $('#editIsrcCode').val(song.isrc || '');
    $('#editUpcCode').val(song.upc || '');
    $('#editReleaseDate').val(song.release_date ? song.release_date.split(' ')[0] : '');
    $('#editDuration').val(song.duration || '');
    $('#editStatus').val(song.status || 'draft');
    $('#editExplicit').prop('checked', song.explicit_content === 'yes');

    const modal = new bootstrap.Modal(document.getElementById('editSongModal'));
    modal.show();
}

// Save song changes - submits the edit form
function saveSongChanges() {
    const songId = $('#editSongId').val();
    const title = $('#editSongTitle').val().trim();
    if (!title) { showToast('Song title is required', 'warning'); return; }

    // Submit the form normally (POST)
    // We already have method and hidden action in the form
    $('#editSongForm').append(`<input type="hidden" name="song_id" value="${$('<div>').text(songId).html()}">`);
    // Make sure explicit checkbox is converted to 1/0
    if ($('#editExplicit').is(':checked')) {
        if ($('#editSongForm input[name="explicit"]').length === 0) {
            $('#editSongForm').append('<input type="hidden" name="explicit" value="1">');
        } else {
            $('#editSongForm input[name="explicit"]').val('1');
        }
    } else {
        if ($('#editSongForm input[name="explicit"]').length === 0) {
            $('#editSongForm').append('<input type="hidden" name="explicit" value="0">');
        } else {
            $('#editSongForm input[name="explicit"]').val('0');
        }
    }
    $('#editSongForm').submit();
}

// Bulk actions
function bulkAction(statusRaw) {
    const selected = getSelectedSongs();
    if (selected.length === 0) { showToast('Please select at least one song', 'warning'); return; }
    if (!confirm(`Are you sure you want to mark ${selected.length} songs as ${statusRaw}?`)) return;
    const status = mapStatusForSubmit(statusRaw);

    const form = $('<form method="POST" style="display:none"></form>');
    form.append('<input name="action" value="bulk_update">');
    selected.forEach((id, idx) => form.append(`<input name="song_ids[${idx}]" value="${id}">`));
    form.append(`<input name="status" value="${status}">`);
    $('body').append(form);
    form.submit();
}

function bulkDelete() {
    const selected = getSelectedSongs();
    if (selected.length === 0) { showToast('Please select at least one song to delete', 'warning'); return; }
    if (!confirm(`Are you sure you want to delete ${selected.length} selected songs? This action cannot be undone.`)) return;

    const form = $('<form method="POST" style="display:none"></form>');
    form.append('<input name="action" value="bulk_delete">');
    selected.forEach((id, idx) => form.append(`<input name="song_ids[${idx}]" value="${id}">`));
    $('body').append(form);
    form.submit();
}

// DataTables date filter
$.fn.dataTable.ext.search.push(function(settings, data) {
    const start = $('#startDate').val();
    const end = $('#endDate').val();
    if (!start && !end) return true;
    const rowDateStr = data[1]; // Date column
    const rowDate = new Date(rowDateStr);
    if (start) { const s = new Date(start); if (rowDate < s) return false; }
    if (end) { const e = new Date(end); if (rowDate > e) return false; }
    return true;
});
$('#startDate, #endDate').on('change', function(){ dataTable.draw(); });
</script>
</body>
</html>
