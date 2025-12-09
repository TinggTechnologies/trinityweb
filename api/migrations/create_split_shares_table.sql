-- Create split_shares table for managing royalty split invitations
CREATE TABLE IF NOT EXISTS split_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    release_id INT NOT NULL,
    inviter_user_id INT NOT NULL,
    invitee_email VARCHAR(255) NOT NULL,
    invitee_user_id INT NULL,
    split_percentage DECIMAL(5,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    invitation_token VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (release_id) REFERENCES releases(id) ON DELETE CASCADE,
    FOREIGN KEY (inviter_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invitee_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_release_id (release_id),
    INDEX idx_inviter_user_id (inviter_user_id),
    INDEX idx_invitee_email (invitee_email),
    INDEX idx_invitation_token (invitation_token),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

