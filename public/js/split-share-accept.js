/**
 * Split Share Accept Page
 * Flow:
 * 1. Load invitation details
 * 2. Check if user has an account with that email
 * 3. If user exists -> Show "Accept Invitation" button, click to approve
 * 4. If user doesn't exist -> Show "Create Account" button, after registration come back and click Accept
 */

let invitationToken = null;
let invitationData = null;

document.addEventListener('DOMContentLoaded', async () => {
    // Get token from URL
    const urlParams = new URLSearchParams(window.location.search);
    invitationToken = urlParams.get('token');

    if (!invitationToken) {
        showError('Invalid invitation link');
        return;
    }

    // Load invitation details
    await loadInvitationDetails();

    // Setup accept button
    const acceptBtn = document.getElementById('acceptBtn');
    if (acceptBtn) {
        acceptBtn.addEventListener('click', acceptInvitation);
    }
});

/**
 * Load invitation details
 */
async function loadInvitationDetails() {
    try {
        // Fetch invitation details from API
        const response = await API.get(`/split-shares/invitation/${invitationToken}`);

        if (!response.success || !response.data.invitation) {
            showError(response.message || 'Invalid or expired invitation');
            return;
        }

        invitationData = response.data.invitation;

        // Check if invitation is still pending
        if (invitationData.status !== 'pending') {
            // Already processed - show appropriate message
            if (invitationData.status === 'accepted') {
                showSuccess('This invitation has already been accepted. Please login to view your dashboard.');
            } else {
                showError('This invitation has already been ' + invitationData.status);
            }
            return;
        }

        // Display invitation details
        document.getElementById('releaseName').textContent = invitationData.release_title || 'Unknown Release';
        document.getElementById('splitPercentage').textContent = invitationData.percentage || '0';

        // Check if user account exists with this email
        const userExists = await checkUserExists(invitationData.email || invitationData.collaborator_email);

        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('invitationDetails').style.display = 'block';

        if (userExists) {
            // User has an account - show Accept button
            document.getElementById('hasAccount').style.display = 'block';
        } else {
            // User needs to create account first
            document.getElementById('notLoggedIn').style.display = 'block';

            // Update register link with token AND email
            const registerLink = document.getElementById('registerLink');
            const inviteeEmail = encodeURIComponent(invitationData.email || invitationData.collaborator_email);

            if (registerLink) {
                registerLink.href = `./register?redirect=split-share-accept&token=${invitationToken}&email=${inviteeEmail}`;
            }
        }

    } catch (error) {
        console.error('Error loading invitation:', error);
        showError(error.message || 'Failed to load invitation details');
    }
}

/**
 * Check if user account exists with this email
 */
async function checkUserExists(email) {
    try {
        const response = await API.get(`/auth/check-email?email=${encodeURIComponent(email)}`);
        return response.success && response.data.exists;
    } catch (error) {
        return false;
    }
}

/**
 * Accept invitation (called when user clicks Accept button)
 */
async function acceptInvitation() {
    const acceptBtn = document.getElementById('acceptBtn');
    const originalText = acceptBtn.innerHTML;

    acceptBtn.disabled = true;
    acceptBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Accepting...';

    try {
        const response = await API.post(`/split-shares/accept/${invitationToken}`);

        if (response.success) {
            showSuccess('Congratulations! Your invitation has been accepted. Please login to view your dashboard.');
        } else {
            showError(response.message || 'Failed to accept invitation');
            acceptBtn.disabled = false;
            acceptBtn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error accepting invitation:', error);
        showError(error.message || 'Failed to accept invitation');
        acceptBtn.disabled = false;
        acceptBtn.innerHTML = originalText;
    }
}

/**
 * Show success state
 */
function showSuccess(message) {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('invitationDetails').style.display = 'none';
    document.getElementById('errorState').style.display = 'none';
    document.getElementById('successState').style.display = 'block';

    // Update success message if provided
    if (message) {
        const successP = document.querySelector('#successState p');
        if (successP) {
            successP.textContent = message;
        }
    }
}

/**
 * Show error state
 */
function showError(message) {
    document.getElementById('loadingState').style.display = 'none';
    document.getElementById('invitationDetails').style.display = 'none';
    document.getElementById('successState').style.display = 'none';
    document.getElementById('errorState').style.display = 'block';
    document.getElementById('errorMessage').textContent = message;
}

