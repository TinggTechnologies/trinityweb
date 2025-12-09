/**
 * Profile Page JavaScript
 * Handles profile viewing, editing, image uploads, and password changes
 */

// Get the correct base paths for uploads and assets
const PROFILE_UPLOADS_PATH = (typeof AppConfig !== 'undefined')
    ? AppConfig.uploadsPath
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? '/trinity/uploads'
        : '/uploads';

const PROFILE_ASSETS_PATH = (typeof AppConfig !== 'undefined')
    ? AppConfig.assetsPath
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? '/trinity/assets'
        : '/assets';

let profileData = null;
let selectedFiles = [];

// Load profile data on page load
document.addEventListener('DOMContentLoaded', () => {
    loadProfile();
    setupEventListeners();
});

/**
 * Load user profile
 */
async function loadProfile() {
    try {
        console.log('Loading profile...');
        const response = await API.get('profile');
        console.log('Profile response:', response);

        if (response.success) {
            profileData = response.data;
            console.log('Profile data:', profileData);
            populateForm(profileData);
            displayImages(profileData.images || []);
        } else {
            console.error('Profile load failed:', response.message);
            showError(response.message || 'Failed to load profile');
        }
    } catch (error) {
        console.error('Error loading profile:', error);
        showError('Failed to load profile. Please try again.');
    }
}

/**
 * Populate form with profile data
 */
function populateForm(data) {
    document.getElementById('firstName').value = data.first_name || '';
    document.getElementById('lastName').value = data.last_name || '';
    document.getElementById('stageName').value = data.stage_name || '';
    document.getElementById('email').value = data.email || '';
    document.getElementById('mobileNumber').value = data.mobile_number || '';
    document.getElementById('originCountry').value = data.origin_country || '';
    document.getElementById('residenceCountry').value = data.residence_country || '';
    document.getElementById('artistBio').value = data.artist_bio || '';

    // Populate social media
    const socialMedia = data.social_media || {};
    document.getElementById('spotify').value = socialMedia.spotify || '';
    document.getElementById('appleMusic').value = socialMedia.apple_music || '';
    document.getElementById('youtube').value = socialMedia.youtube || '';
    document.getElementById('facebook').value = socialMedia.facebook || '';
    document.getElementById('twitter').value = socialMedia.twitter || '';
    document.getElementById('instagram').value = socialMedia.instagram || '';
    document.getElementById('tiktok').value = socialMedia.tiktok || '';
    document.getElementById('website').value = socialMedia.website || '';
}

/**
 * Display profile images
 */
function displayImages(images) {
    const container = document.getElementById('imageGallery');

    console.log('Displaying images:', images);

    if (!images || images.length === 0) {
        container.innerHTML = `
            <div class="no-images">
                <i class="fas fa-images fa-2x mb-2"></i>
                <p>No profile images uploaded yet</p>
            </div>
        `;
        return;
    }

    container.innerHTML = images.map(image => {
        // Normalize image path - use the correct base path
        let imagePath = image.image_path;

        // Remove leading '../' or './' or 'uploads/' if present
        if (imagePath.startsWith('../') || imagePath.startsWith('./')) {
            imagePath = imagePath.replace(/^\.\.\/|^\.\//, '');
        }

        // Remove 'uploads/' prefix if present and build full path
        const cleanPath = imagePath.replace(/^uploads\//, '');
        const fullImagePath = `${PROFILE_UPLOADS_PATH}/${cleanPath}`;
        const defaultAvatar = `${PROFILE_ASSETS_PATH}/images/user.png`;

        console.log('Image path:', image.image_path, '-> Display path:', fullImagePath);

        return `
            <div class="image-item ${image.is_primary ? 'primary' : ''}">
                <img src="${fullImagePath}" alt="Profile Image" onerror="console.error('Failed to load image:', '${fullImagePath}'); this.src='${defaultAvatar}';">

                <div class="image-controls">
                    ${!image.is_primary ? `
                        <button type="button" onclick="setPrimaryImage(${image.id})" title="Set as Primary">
                            <i class="fas fa-star"></i>
                        </button>
                    ` : ''}

                    <button type="button" onclick="deleteImage(${image.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>

                ${image.is_primary ? '<div class="primary-badge">PRIMARY</div>' : ''}
            </div>
        `;
    }).join('');
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Profile form submission
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', handleProfileUpdate);
    }

    // Password form submission
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', handlePasswordUpdate);
    }

    // Image upload
    const fileInput = document.getElementById('profileImages');
    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelection);
    }

    // Upload images button
    const uploadBtn = document.getElementById('uploadImagesBtn');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', uploadImages);
    }
}

/**
 * Handle file selection
 */
function handleFileSelection(e) {
    const files = Array.from(e.target.files);

    console.log('Files selected:', files.length);

    if (files.length > 0) {
        // Add new files to selectedFiles array
        files.forEach(file => {
            const exists = selectedFiles.some(f => f.name === file.name && f.size === file.size);
            if (!exists) {
                selectedFiles.push(file);
                console.log('Added file:', file.name);
            }
        });

        console.log('Total selected files:', selectedFiles.length);
        updatePreview();
    }
}

/**
 * Update image preview
 */
function updatePreview() {
    const container = document.getElementById('imagePreviewContainer');
    const uploadBtn = document.getElementById('uploadImagesBtn');

    console.log('Updating preview for', selectedFiles.length, 'files');

    if (selectedFiles.length === 0) {
        console.log('No files selected, hiding preview');
        container.style.display = 'none';
        container.innerHTML = '';
        if (uploadBtn) {
            uploadBtn.style.display = 'none';
        }
        return;
    }

    console.log('Showing preview container');
    container.style.display = 'grid';
    container.innerHTML = '';

    // Show upload button
    if (uploadBtn) {
        uploadBtn.style.display = 'block';
        console.log('Upload button shown');
    }

    selectedFiles.forEach((file, index) => {
        console.log('Creating preview for:', file.name);
        const reader = new FileReader();

        reader.onload = function (e) {
            console.log('File loaded:', file.name);
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            previewItem.innerHTML = `
                <img src="${e.target.result}" alt="${file.name}">
                <button type="button" class="preview-remove" onclick="removePreviewImage(${index})" title="Remove">
                    <i class="fas fa-times"></i>
                </button>
                <div class="preview-filename">${file.name}</div>
            `;

            container.appendChild(previewItem);
        };

        reader.onerror = function (error) {
            console.error('Error reading file:', file.name, error);
        };

        reader.readAsDataURL(file);
    });
}

/**
 * Remove image from preview
 */
function removePreviewImage(index) {
    selectedFiles.splice(index, 1);
    updatePreview();
}

/**
 * Upload images
 */
async function uploadImages() {
    console.log('Upload images clicked');

    if (selectedFiles.length === 0) {
        showError('Please select images to upload');
        return;
    }

    console.log('Uploading', selectedFiles.length, 'files');

    const formData = new FormData();
    selectedFiles.forEach((file) => {
        // Append as 'images[]' to create an array in PHP
        formData.append('images[]', file);
        console.log('Appended to FormData:', file.name);
    });

    // Debug: Log FormData contents
    for (let pair of formData.entries()) {
        console.log('FormData entry:', pair[0], pair[1]);
    }

    try {
        console.log('Sending upload request to:', `${API.baseURL}/profile/images`);
        const response = await fetch(`${API.baseURL}/profile/images`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        console.log('Response status:', response.status);
        const data = await response.json();
        console.log('Upload response:', data);

        if (data.success) {
            showSuccess(data.message || 'Images uploaded successfully');
            selectedFiles = [];
            updatePreview();
            loadProfile(); // Reload to show new images
        } else {
            showError(data.message || 'Failed to upload images');
        }
    } catch (error) {
        console.error('Error uploading images:', error);
        showError('Failed to upload images. Please try again.');
    }
}

/**
 * Delete image
 */
async function deleteImage(imageId) {
    if (!confirm('Are you sure you want to delete this image?')) {
        return;
    }

    try {
        const response = await API.delete(`profile/images/${imageId}`);

        if (response.success) {
            showSuccess('Image deleted successfully');
            loadProfile(); // Reload to update gallery
        } else {
            showError(response.message || 'Failed to delete image');
        }
    } catch (error) {
        console.error('Error deleting image:', error);
        showError('Failed to delete image. Please try again.');
    }
}

/**
 * Set primary image
 */
async function setPrimaryImage(imageId) {
    try {
        const response = await API.put(`profile/images/${imageId}/primary`);

        if (response.success) {
            showSuccess('Primary image updated successfully');
            loadProfile(); // Reload to update gallery
        } else {
            showError(response.message || 'Failed to set primary image');
        }
    } catch (error) {
        console.error('Error setting primary image:', error);
        showError('Failed to set primary image. Please try again.');
    }
}

/**
 * Handle profile update
 */
async function handleProfileUpdate(e) {
    e.preventDefault();

    console.log('Profile update triggered');

    const formData = {
        first_name: document.getElementById('firstName').value.trim(),
        last_name: document.getElementById('lastName').value.trim(),
        stage_name: document.getElementById('stageName').value.trim(),
        email: document.getElementById('email').value.trim(),
        mobile_number: document.getElementById('mobileNumber').value.trim(),
        origin_country: document.getElementById('originCountry').value,
        residence_country: document.getElementById('residenceCountry').value,
        artist_bio: document.getElementById('artistBio').value.trim(),
        social_media: {
            spotify: document.getElementById('spotify').value.trim(),
            apple_music: document.getElementById('appleMusic').value.trim(),
            youtube: document.getElementById('youtube').value.trim(),
            facebook: document.getElementById('facebook').value.trim(),
            twitter: document.getElementById('twitter').value.trim(),
            instagram: document.getElementById('instagram').value.trim(),
            tiktok: document.getElementById('tiktok').value.trim(),
            website: document.getElementById('website').value.trim()
        }
    };

    console.log('Form data:', formData);

    try {
        const response = await API.put('profile', formData);
        console.log('Update response:', response);

        if (response.success) {
            showSuccess('Profile updated successfully');
            loadProfile(); // Reload profile data
        } else {
            console.error('Update failed:', response.message);
            showError(response.message || 'Failed to update profile');
        }
    } catch (error) {
        console.error('Error updating profile:', error);
        showError('Failed to update profile. Please try again.');
    }
}

/**
 * Handle password update
 */
async function handlePasswordUpdate(e) {
    e.preventDefault();

    console.log('Password update triggered');

    const oldPassword = document.getElementById('oldPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    console.log('Password fields filled:', {
        oldPassword: !!oldPassword,
        newPassword: !!newPassword,
        confirmPassword: !!confirmPassword
    });

    if (!oldPassword || !newPassword || !confirmPassword) {
        showError('All password fields are required');
        return;
    }

    if (newPassword !== confirmPassword) {
        showError('New passwords do not match');
        return;
    }

    if (newPassword.length < 8) {
        showError('Password must be at least 8 characters');
        return;
    }

    console.log('Sending password update request...');

    try {
        const response = await API.put('profile/password', {
            old_password: oldPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
        });

        console.log('Password update response:', response);

        if (response.success) {
            showSuccess('Password updated successfully');
            document.getElementById('passwordForm').reset();
        } else {
            console.error('Password update failed:', response.message);
            showError(response.message || 'Failed to update password');
        }
    } catch (error) {
        console.error('Error updating password:', error);
        showError('Failed to update password. Please try again.');
    }
}

/**
 * Show success message
 */
function showSuccess(message) {
    const successDiv = document.getElementById('successMessage');
    const successText = document.getElementById('successText');
    successText.textContent = message;
    successDiv.style.display = 'block';

    setTimeout(() => {
        successDiv.style.display = 'none';
    }, 5000);
}

/**
 * Show error message
 */
function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');
    errorText.textContent = message;
    errorDiv.style.display = 'block';

    setTimeout(() => {
        errorDiv.style.display = 'none';
    }, 5000);
}

