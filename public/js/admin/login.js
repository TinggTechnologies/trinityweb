/**
 * Admin Login JavaScript
 */

// Use AppConfig if available, otherwise fallback
const API_BASE_URL = (typeof AppConfig !== 'undefined')
    ? AppConfig.apiUrl
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? 'http://localhost/trinity/api'
        : 'https://trinity.futurewebhost.com.ng/api';

// Check if already logged in
document.addEventListener('DOMContentLoaded', () => {
    checkAdminAuth();
});

async function checkAdminAuth() {
    try {
        const response = await fetch(`${API_BASE_URL}/admin/me`, {
            credentials: 'include'
        });
        
        if (response.ok) {
            // Already logged in, redirect to dashboard
            window.location.href = './dashboard';
        }
    } catch (error) {
        // Not logged in, stay on login page
    }
}

// Handle login form submission
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const remember_me = document.getElementById('rememberMe').checked;

    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Logging in...';

    try {
        const response = await fetch(`${API_BASE_URL}/admin/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ email, password, remember_me })
        });

        const data = await response.json();

        if (response.ok) {
            showAlert('Login successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = './dashboard';
            }, 1000);
        } else {
            showAlert(data.message || 'Login failed', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    } catch (error) {
        showAlert('An error occurred. Please try again.', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

function showAlert(message, type) {
    const alertContainer = document.getElementById('alertContainer');
    alertContainer.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

