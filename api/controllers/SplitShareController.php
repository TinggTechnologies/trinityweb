<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/SplitShare.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Email.php';

class SplitShareController {
    
    /**
     * Create a new split share invitation
     * POST /api/split-shares
     */
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        if (empty($data['release_id']) || empty($data['invitee_email']) || empty($data['split_percentage'])) {
            Response::error('Release ID, invitee email, and split percentage are required');
        }

        // Validate split percentage
        if ($data['split_percentage'] <= 0 || $data['split_percentage'] > 100) {
            Response::error('Split percentage must be between 0.01 and 100');
        }

        // Validate email format
        if (!filter_var($data['invitee_email'], FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address');
        }

        $splitShareModel = new SplitShare();

        // Check if there's already a pending invitation for this email
        if ($splitShareModel->hasPendingInvitation($data['release_id'], $data['invitee_email'])) {
            Response::error('A pending invitation already exists for this email');
        }

        // Check total split percentage doesn't exceed 100%
        $totalSplit = $splitShareModel->getTotalSplitPercentage($data['release_id']);
        if (($totalSplit + $data['split_percentage']) > 100) {
            Response::error('Total split percentage cannot exceed 100%. Current total: ' . $totalSplit . '%');
        }

        try {
            $result = $splitShareModel->create([
                'release_id' => $data['release_id'],
                'invitee_email' => $data['invitee_email'],
                'collaborator_name' => $data['collaborator_name'] ?? $data['invitee_email'],
                'split_percentage' => $data['split_percentage']
            ]);

            // Try to send invitation email (don't fail if email fails)
            $emailSent = false;
            try {
                $emailSent = $this->sendInvitationEmail(
                    $data['invitee_email'],
                    $result['token'],
                    $data['release_id'],
                    $data['split_percentage'],
                    $data['collaborator_name'] ?? $data['invitee_email']
                );
            } catch (Exception $emailError) {
                error_log("Failed to send invitation email: " . $emailError->getMessage());
            }

            $message = $emailSent
                ? 'Invitation sent successfully'
                : 'Invitation created successfully (email not sent - check email configuration)';

            Response::success(['invitation_id' => $result['id'], 'email_sent' => $emailSent], $message);
        } catch (Exception $e) {
            error_log("Error creating split share: " . $e->getMessage());
            Response::serverError('Failed to create split share invitation');
        }
    }

    /**
     * Get split shares for a release
     * GET /api/split-shares/{release_id}
     */
    public function getByRelease($releaseId) {
        $splitShareModel = new SplitShare();

        try {
            $db = Database::getInstance()->getConnection();

            // Get owner info
            $stmt = $db->prepare("
                SELECT r.user_id, u.first_name, u.last_name, u.email
                FROM releases r
                JOIN users u ON r.user_id = u.id
                WHERE r.id = ?
            ");
            $stmt->execute([$releaseId]);
            $ownerData = $stmt->fetch(PDO::FETCH_ASSOC);

            $invitations = $splitShareModel->getByReleaseId($releaseId);

            // Calculate total accepted percentage
            $totalAcceptedPercentage = 0;
            foreach ($invitations as $inv) {
                if ($inv['status'] === 'accepted') {
                    $totalAcceptedPercentage += floatval($inv['split_percentage']);
                }
            }

            // Owner percentage is 100% minus accepted splits
            $ownerPercentage = 100 - $totalAcceptedPercentage;

            // Build owner info
            $owner = null;
            if ($ownerData) {
                $ownerName = trim($ownerData['first_name'] . ' ' . $ownerData['last_name']);
                if (empty($ownerName)) {
                    $ownerName = $ownerData['email'];
                }
                $owner = [
                    'name' => $ownerName,
                    'email' => $ownerData['email'],
                    'split_percentage' => $ownerPercentage
                ];
            }

            // Separate pending and approved invitations
            $pending = [];
            $approved = [];
            foreach ($invitations as $inv) {
                if ($inv['status'] === 'accepted') {
                    $approved[] = $inv;
                } else if ($inv['status'] === 'pending') {
                    $pending[] = $inv;
                }
            }

            Response::success([
                'owner' => $owner,
                'pending' => $pending,
                'approved' => $approved
            ]);
        } catch (Exception $e) {
            error_log("Error fetching split shares: " . $e->getMessage());
            Response::serverError('Failed to fetch split shares');
        }
    }

    /**
     * Resend invitation email
     * POST /api/split-shares/{id}/resend
     */
    public function resend($id) {
        $splitShareModel = new SplitShare();

        try {
            $invitation = $splitShareModel->findById($id);

            if (!$invitation) {
                Response::error('Invitation not found', 404);
            }

            if ($invitation['status'] !== 'pending') {
                Response::error('Can only resend pending invitations');
            }

            // Try to send invitation email
            $emailSent = false;
            try {
                $emailSent = $this->sendInvitationEmail(
                    $invitation['collaborator_email'],
                    $invitation['token'],
                    $invitation['release_id'],
                    $invitation['percentage'],
                    $invitation['collaborator_name']
                );
            } catch (Exception $emailError) {
                error_log("Failed to resend invitation email: " . $emailError->getMessage());
            }

            $message = $emailSent
                ? 'Invitation resent successfully'
                : 'Invitation exists but email not sent - check email configuration';

            Response::success(['email_sent' => $emailSent], $message);
        } catch (Exception $e) {
            error_log("Error resending invitation: " . $e->getMessage());
            Response::serverError('Failed to resend invitation');
        }
    }

    /**
     * Get invitation details by token (public - no auth required)
     * GET /api/split-shares/invitation/{token}
     */
    public function getInvitationByToken($token) {
        $splitShareModel = new SplitShare();

        try {
            $invitation = $splitShareModel->findByToken($token);

            if (!$invitation) {
                Response::error('Invalid invitation token', 404);
                return;
            }

            // Get release details
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT release_title FROM releases WHERE id = ?");
            $stmt->execute([$invitation['release_id']]);
            $release = $stmt->fetch(PDO::FETCH_ASSOC);

            Response::success([
                'invitation' => [
                    'email' => $invitation['collaborator_email'],
                    'collaborator_name' => $invitation['collaborator_name'],
                    'percentage' => $invitation['percentage'],
                    'status' => $invitation['status'],
                    'release_title' => $release ? $release['release_title'] : 'Unknown Release'
                ]
            ]);
        } catch (Exception $e) {
            error_log("Error getting invitation: " . $e->getMessage());
            Response::serverError('Failed to get invitation details');
        }
    }

    /**
     * Accept invitation (for invitee)
     * POST /api/split-shares/accept/{token}
     * Does NOT require login - just checks if the email exists in users table
     */
    public function accept($token) {
        $splitShareModel = new SplitShare();

        try {
            $invitation = $splitShareModel->findByToken($token);

            if (!$invitation) {
                Response::error('Invalid invitation token', 404);
            }

            if ($invitation['status'] !== 'pending') {
                Response::error('This invitation has already been processed');
            }

            // Check if user with this email exists
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, email FROM users WHERE LOWER(email) = LOWER(?)");
            $stmt->execute([$invitation['collaborator_email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // User doesn't exist - tell them to create an account
                Response::error('No account found with email ' . $invitation['collaborator_email'] . '. Please create an account first.', 404);
            }

            // User exists - accept invitation and link user ID
            $splitShareModel->updateStatus($invitation['id'], 'accepted', $user['id']);

            Response::success([
                'user_exists' => true,
                'message' => 'Invitation accepted! Please login to view your dashboard.'
            ], 'Split share accepted successfully');
        } catch (Exception $e) {
            error_log("Error accepting invitation: " . $e->getMessage());
            Response::serverError('Failed to accept invitation');
        }
    }

    /**
     * Send invitation email
     * Returns true if email sent successfully, false otherwise
     */
    private function sendInvitationEmail($email, $token, $releaseId, $percentage = null, $collaboratorName = null) {
        $invitationLink = BASE_URL . "/public/split-share-accept?token=" . $token;

        // Get release title
        $releaseName = 'a release';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT release_title FROM releases WHERE id = ?");
            $stmt->execute([$releaseId]);
            $release = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($release && $release['release_title']) {
                $releaseName = $release['release_title'];
            }
        } catch (Exception $e) {
            error_log("Failed to get release name for email: " . $e->getMessage());
        }

        // Format percentage display
        $percentageText = '';
        if ($percentage) {
            $percentageText = "<p style='font-size: 18px; background-color: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center;'>
                <strong>Your Allocated Split:</strong> <span style='color: #dc3545; font-size: 24px;'>{$percentage}%</span>
            </p>";
        }

        // Greeting
        $greeting = $collaboratorName ? "Hello {$collaboratorName}!" : "Hello!";

        $subject = "You've been invited to collaborate on \"{$releaseName}\" - Trinity Distribution";
        $message = "
            <h2>{$greeting}</h2>
            <p>You have been invited to collaborate on <strong>\"{$releaseName}\"</strong> on Trinity Distribution and receive royalties.</p>
            {$percentageText}
            <p>Click the button below to accept your invitation:</p>
            <p><a href='{$invitationLink}' style='background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Accept Invitation</a></p>
            <p>Or copy this link: <a href='{$invitationLink}'>{$invitationLink}</a></p>
            <p><strong>What happens next?</strong></p>
            <ol>
                <li>Click the invitation link above</li>
                <li>Register or login to your Trinity Distribution account</li>
                <li>Accept the split share invitation</li>
                <li>Start receiving your royalty splits!</li>
            </ol>
            <p>If this email is not intended for you, please simply ignore this email.</p>
        ";

        return Email::send($email, $subject, $message);
    }
}

