<?php
/**
 * API Router
 * Main entry point for all API requests
 */

// Load configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/config/Database.php';

// Load utilities
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Validator.php';
require_once __DIR__ . '/utils/FileUpload.php';

// Load middleware
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/middleware/AdminAuthMiddleware.php';

// Load models
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Release.php';
require_once __DIR__ . '/models/Track.php';
require_once __DIR__ . '/models/Royalty.php';
require_once __DIR__ . '/models/Payment.php';
require_once __DIR__ . '/models/Ticket.php';
require_once __DIR__ . '/models/Analytics.php';
require_once __DIR__ . '/models/SplitShare.php';
require_once __DIR__ . '/models/Admin.php';
require_once __DIR__ . '/models/StreamEarning.php';

// Load controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/ProfileController.php';
require_once __DIR__ . '/controllers/ReleaseController.php';
require_once __DIR__ . '/controllers/TrackController.php';
require_once __DIR__ . '/controllers/AnalyticsController.php';
require_once __DIR__ . '/controllers/RoyaltyController.php';
require_once __DIR__ . '/controllers/PaymentController.php';
require_once __DIR__ . '/controllers/TicketController.php';
require_once __DIR__ . '/controllers/HelpTicketController.php';
require_once __DIR__ . '/controllers/SplitShareController.php';
require_once __DIR__ . '/controllers/UserEarningsController.php';
require_once __DIR__ . '/controllers/ArtistController.php';

// Load admin controllers
require_once __DIR__ . '/controllers/admin/AdminAuthController.php';
require_once __DIR__ . '/controllers/admin/AdminController.php';
require_once __DIR__ . '/controllers/admin/AdminDashboardController.php';
require_once __DIR__ . '/controllers/admin/AdminUserController.php';
require_once __DIR__ . '/controllers/admin/AdminSongController.php';
require_once __DIR__ . '/controllers/admin/AdminRoyaltyController.php';
require_once __DIR__ . '/controllers/admin/AdminTicketController.php';
require_once __DIR__ . '/controllers/admin/AdminAdministratorController.php';
require_once __DIR__ . '/controllers/admin/AdminEarningsController.php';
require_once __DIR__ . '/controllers/admin/AdminPaymentController.php';

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove base path and /api from path
// Handles both /trinity/api/... (local) and /api/... (production)
if (preg_match('#/api(/.*)?$#', $path, $matches)) {
    $path = isset($matches[1]) ? $matches[1] : '';
}
$path = trim($path, '/');

// Split path into segments
$segments = explode('/', $path);
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$action = $segments[2] ?? null;

// Route the request
try {
    switch ($resource) {
        // Authentication routes
        case 'auth':
            $action = $id;
            switch ($action) {
                case 'login':
                    if ($method === 'POST') {
                        AuthController::login();
                    }
                    break;
                case 'register':
                    if ($method === 'POST') {
                        AuthController::register();
                    }
                    break;
                case 'logout':
                    if ($method === 'POST') {
                        AuthController::logout();
                    }
                    break;
                case 'verify':
                    if ($method === 'GET') {
                        AuthController::verify();
                    }
                    break;
                case 'me':
                    if ($method === 'GET') {
                        AuthController::getCurrentUser();
                    }
                    break;
                case 'check':
                    if ($method === 'GET') {
                        AuthController::checkAuth();
                    }
                    break;
                case 'check-email':
                    if ($method === 'GET') {
                        AuthController::checkEmail();
                    }
                    break;
                default:
                    Response::notFound('Auth endpoint not found');
            }
            break;
            
        // User routes
        case 'users':
            if ($method === 'GET' && $id) {
                UserController::getUser($id);
            } elseif ($method === 'PUT' && $id) {
                UserController::updateUser($id);
            } elseif ($method === 'GET') {
                UserController::getAllUsers();
            } else {
                Response::notFound();
            }
            break;

        // Profile routes
        case 'profile':
            $action = $id;
            $subAction = $segments[2] ?? null;

            if ($method === 'GET' && !$action) {
                ProfileController::getProfile();
            } elseif ($method === 'PUT' && !$action) {
                ProfileController::updateProfile();
            } elseif ($method === 'POST' && $action === 'images') {
                ProfileController::uploadImages();
            } elseif ($method === 'DELETE' && $action === 'images' && $subAction) {
                ProfileController::deleteImage($subAction);
            } elseif ($method === 'PUT' && $action === 'images' && $subAction && ($segments[3] ?? null) === 'primary') {
                ProfileController::setPrimaryImage($subAction);
            } elseif ($method === 'PUT' && $action === 'password') {
                ProfileController::updatePassword();
            } else {
                Response::notFound();
            }
            break;
            
        // Release routes
        case 'releases':
            if ($method === 'GET' && $id === 'next-catalog') {
                ReleaseController::getNextCatalog();
            } elseif ($method === 'GET' && $id && $action === 'tracks') {
                // GET /api/releases/{id}/tracks
                TrackController::getReleaseTracks($id);
            } elseif ($method === 'POST' && $id && $action === 'tracks') {
                // POST /api/releases/{id}/tracks
                TrackController::createTrack($id);
            } elseif ($method === 'GET' && $id) {
                ReleaseController::getRelease($id);
            } elseif ($method === 'POST') {
                ReleaseController::createRelease();
            } elseif ($method === 'PUT' && $id) {
                ReleaseController::updateRelease($id);
            } elseif ($method === 'DELETE' && $id) {
                ReleaseController::deleteRelease($id);
            } elseif ($method === 'GET') {
                ReleaseController::getAllReleases();
            } else {
                Response::notFound();
            }
            break;

        // Track routes
        case 'tracks':
            if ($method === 'GET' && $id) {
                TrackController::getTrack($id);
            } elseif ($method === 'PUT' && $id) {
                TrackController::updateTrack($id);
            } elseif ($method === 'DELETE' && $id) {
                TrackController::deleteTrack($id);
            } else {
                Response::notFound();
            }
            break;

        // Audio upload routes
        case 'audio-upload':
            if ($method === 'POST') {
                TrackController::uploadAudio();
            } else {
                Response::notFound();
            }
            break;

        // Analytics routes
        case 'analytics':
            $action = $id;
            if ($method === 'GET' && $action === 'artists') {
                AnalyticsController::getTopArtists();
            } elseif ($method === 'GET' && $action === 'tracks') {
                AnalyticsController::getTopTracks();
            } elseif ($method === 'GET') {
                AnalyticsController::getAnalytics();
            } else {
                Response::notFound();
            }
            break;

        // User earnings routes
        case 'earnings':
            if ($method === 'GET' && $id === 'summary') {
                UserEarningsController::getEarningsSummary();
            } elseif ($method === 'GET') {
                UserEarningsController::getUserEarnings();
            } else {
                Response::notFound();
            }
            break;
            
        // Royalty routes
        case 'royalties':
            $action = $id;
            if ($method === 'GET' && !$action) {
                RoyaltyController::getRoyalties();
            } elseif ($method === 'POST' && $action === 'request-payment') {
                RoyaltyController::requestPayment();
            } else {
                Response::notFound();
            }
            break;
            
        // Payment routes
        case 'payments':
            if ($method === 'GET') {
                PaymentController::getPaymentMethods();
            } elseif ($method === 'POST') {
                PaymentController::savePaymentMethod();
            } else {
                Response::notFound();
            }
            break;

        // Payment details routes
        case 'payment-details':
            if ($method === 'GET') {
                PaymentController::getPaymentDetails();
            } elseif ($method === 'PUT') {
                PaymentController::updateAllPaymentDetails();
            } else {
                Response::notFound();
            }
            break;

        // Ticket routes
        case 'tickets':
            if ($method === 'GET' && $id) {
                TicketController::getTicket($id);
            } elseif ($method === 'POST') {
                TicketController::createTicket();
            } elseif ($method === 'GET') {
                TicketController::getAllTickets();
            } else {
                Response::notFound();
            }
            break;

        // Artist routes
        case 'artists':
            if ($method === 'GET' && $id === 'search') {
                ArtistController::search();
            } elseif ($method === 'GET' && $id) {
                ArtistController::getById($id);
            } elseif ($method === 'GET') {
                ArtistController::getAll();
            } elseif ($method === 'POST') {
                ArtistController::create();
            } elseif ($method === 'PUT' && $id) {
                ArtistController::update($id);
            } elseif ($method === 'DELETE' && $id) {
                ArtistController::delete($id);
            } else {
                Response::notFound();
            }
            break;

        // Help Ticket routes
        case 'help-tickets':
            $ticketId = $id;
            $ticketAction = $action; // $action is already segments[2]

            if ($method === 'GET' && $ticketId === 'stats') {
                HelpTicketController::getStats();
            } elseif ($method === 'POST' && $ticketId && $ticketAction === 'reply') {
                HelpTicketController::replyToTicket($ticketId);
            } elseif ($method === 'GET' && $ticketId) {
                HelpTicketController::getTicket($ticketId);
            } elseif ($method === 'GET') {
                HelpTicketController::getTickets();
            } elseif ($method === 'POST') {
                HelpTicketController::createTicket();
            } elseif ($method === 'PUT' && $ticketId) {
                HelpTicketController::updateTicket($ticketId);
            } elseif ($method === 'DELETE' && $ticketId) {
                HelpTicketController::deleteTicket($ticketId);
            } else {
                Response::notFound();
            }
            break;

        // Split Share routes
        case 'split-shares':
            $controller = new SplitShareController();

            // Public endpoint - get invitation by token (no auth required)
            if ($method === 'GET' && $id === 'invitation' && $action) {
                $controller->getInvitationByToken($action);
            } elseif ($method === 'POST' && $id === 'accept' && $action) {
                // Accept invitation (token is in $action) - no auth required for accepting
                $controller->accept($action);
            } else {
                // All other routes require authentication
                AuthMiddleware::authenticate();

                if ($method === 'POST' && !$id) {
                    // Create new split share invitation
                    $controller->create();
                } elseif ($method === 'GET' && $id === 'release' && $action) {
                    // Get split shares for a release: /api/split-shares/release/{id}
                    $controller->getByRelease($action);
                } elseif ($method === 'GET' && $id && !$action) {
                    // Get split shares for a release (legacy)
                    $controller->getByRelease($id);
                } elseif ($method === 'POST' && $id && $action === 'resend') {
                    // Resend invitation
                    $controller->resend($id);
                } else {
                    Response::notFound();
                }
            }
            break;

        // Admin routes
        case 'admin':
            $adminResource = $id;
            $adminId = $action;
            $adminAction = $segments[3] ?? null;

            switch ($adminResource) {
                // Admin authentication
                case 'login':
                    if ($method === 'POST') {
                        AdminAuthController::login();
                    }
                    break;

                case 'logout':
                    if ($method === 'POST') {
                        AdminAuthController::logout();
                    }
                    break;

                case 'check-auth':
                    if ($method === 'GET') {
                        AdminAuthController::checkAuth();
                    }
                    break;

                case 'me':
                    if ($method === 'GET') {
                        AdminAuthController::getCurrentAdmin();
                    }
                    break;

                // Admin dashboard
                case 'dashboard':
                    if ($adminId === 'stats' && $method === 'GET') {
                        AdminDashboardController::getStats();
                    } elseif ($adminId === 'activities' && $method === 'GET') {
                        AdminDashboardController::getRecentActivities();
                    } elseif ($adminId === 'chart-data' && $method === 'GET') {
                        AdminDashboardController::getChartData();
                    }
                    break;

                // Admin user management
                case 'users':
                    if ($method === 'GET' && !$adminId) {
                        AdminUserController::getUsers();
                    } elseif ($method === 'GET' && $adminId) {
                        AdminUserController::getUser($adminId);
                    } elseif ($method === 'PUT' && $adminId) {
                        AdminUserController::updateUser($adminId);
                    } elseif ($method === 'DELETE' && $adminId) {
                        AdminUserController::deleteUser($adminId);
                    } elseif ($method === 'POST' && $adminId && $adminAction === 'verify') {
                        AdminUserController::verifyUser($adminId);
                    } elseif ($method === 'POST' && $adminAction === 'bulk-delete') {
                        AdminUserController::bulkDelete();
                    }
                    break;

                // Admin song/release management
                case 'songs':
                case 'releases':
                    if ($method === 'GET' && !$adminId) {
                        AdminSongController::getReleases();
                    } elseif ($method === 'GET' && $adminId) {
                        AdminSongController::getRelease($adminId);
                    } elseif ($method === 'PUT' && $adminId && $adminAction === 'status') {
                        AdminSongController::updateReleaseStatus($adminId);
                    } elseif ($method === 'PUT' && $adminId && !$adminAction) {
                        AdminSongController::updateRelease($adminId);
                    } elseif ($method === 'DELETE' && $adminId) {
                        AdminSongController::deleteRelease($adminId);
                    } elseif ($method === 'POST' && $adminAction === 'bulk-delete') {
                        AdminSongController::bulkDelete();
                    } elseif ($method === 'POST' && $adminAction === 'bulk-update-status') {
                        AdminSongController::bulkUpdateStatus();
                    }
                    break;

                // Admin royalty management
                case 'royalties':
                    if ($method === 'GET' && $adminId === 'payment-requests') {
                        AdminRoyaltyController::getPaymentRequests();
                    } elseif ($method === 'PUT' && $adminId === 'payment-requests' && $adminAction) {
                        AdminRoyaltyController::updatePaymentRequest($adminAction);
                    } elseif ($method === 'GET' && !$adminId) {
                        AdminRoyaltyController::getRoyalties();
                    } elseif ($method === 'POST' && !$adminId) {
                        AdminRoyaltyController::createRoyalty();
                    } elseif ($method === 'PUT' && $adminId && $adminAction === 'status') {
                        AdminRoyaltyController::updateRoyaltyStatus($adminId);
                    } elseif ($method === 'DELETE' && $adminId) {
                        AdminRoyaltyController::deleteRoyalty($adminId);
                    }
                    break;

                // Admin ticket management
                case 'tickets':
                    if ($method === 'GET' && !$adminId) {
                        AdminTicketController::getTickets();
                    } elseif ($method === 'GET' && $adminId && $adminAction === 'messages') {
                        AdminTicketController::getTicketMessages($adminId);
                    } elseif ($method === 'GET' && $adminId) {
                        AdminTicketController::getTicket($adminId);
                    } elseif ($method === 'POST' && $adminId && $adminAction === 'reply') {
                        AdminTicketController::replyToTicket($adminId);
                    } elseif ($method === 'PUT' && $adminId && $adminAction === 'status') {
                        AdminTicketController::updateTicketStatus($adminId);
                    } elseif ($method === 'DELETE' && $adminId) {
                        AdminTicketController::deleteTicket($adminId);
                    }
                    break;

                // Admin administrator management
                case 'administrators':
                    if ($method === 'GET' && !$adminId) {
                        AdminAdministratorController::getAdministrators();
                    } elseif ($method === 'POST' && !$adminId) {
                        AdminAdministratorController::createAdministrator();
                    } elseif ($method === 'PUT' && $adminId) {
                        AdminAdministratorController::updateAdministrator($adminId);
                    } elseif ($method === 'DELETE' && $adminId) {
                        AdminAdministratorController::deleteAdministrator($adminId);
                    }
                    break;

                // Admin earnings management
                case 'earnings':
                    if ($method === 'POST' && $adminId === 'upload') {
                        AdminEarningsController::uploadCSV();
                    } elseif ($method === 'GET' && $adminId === 'uploads') {
                        AdminEarningsController::getUploads();
                    } elseif ($method === 'GET' && $adminId === 'data') {
                        AdminEarningsController::getEarnings();
                    } elseif ($method === 'GET' && $adminId === 'analytics') {
                        AdminEarningsController::getAnalytics();
                    }
                    break;

                // Admin payment requests management
                case 'payments':
                    if ($method === 'GET' && !$adminId) {
                        AdminPaymentController::getAllPaymentRequests();
                    } elseif ($method === 'PUT' && $adminId) {
                        AdminPaymentController::updatePaymentStatus($adminId);
                    }
                    break;

                // Admin roles
                case 'roles':
                    if ($method === 'GET') {
                        AdminController::getRoles();
                    }
                    break;

                // Admin track management
                case 'tracks':
                    if ($method === 'GET' && $adminId) {
                        AdminSongController::getTrack($adminId);
                    } elseif ($method === 'PUT' && $adminId) {
                        AdminSongController::updateTrack($adminId);
                    } elseif ($method === 'DELETE' && $adminId) {
                        AdminSongController::deleteTrack($adminId);
                    }
                    break;

                default:
                    Response::notFound('Admin endpoint not found');
            }
            break;

        default:
            Response::notFound('API endpoint not found');
    }
} catch (Exception $e) {
    if (DEBUG_MODE) {
        Response::serverError($e->getMessage());
    } else {
        Response::serverError('An error occurred');
    }
}

