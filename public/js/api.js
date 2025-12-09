/**
 * API Client
 * Handles all API requests to the backend
 */

// Use AppConfig if available, otherwise fallback to detecting environment
const API_BASE_URL = (function() {
    if (typeof AppConfig !== 'undefined') {
        return AppConfig.apiUrl;
    }
    // Fallback detection
    const hostname = window.location.hostname;
    const isLocal = hostname === 'localhost' || hostname === '127.0.0.1';
    return isLocal
        ? 'http://localhost/trinity/api'
        : 'https://trinity.futurewebhost.com.ng/api';
})();

class API {
    static baseURL = API_BASE_URL;

    /**
     * Make API request
     */
    static async request(endpoint, options = {}) {
        // Remove leading slash if present to avoid double slashes
        const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
        const url = `${API_BASE_URL}/${cleanEndpoint}`;
        
        const config = {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            credentials: 'include', // Include cookies for session
            ...options
        };
        
        // Add body if present
        if (options.body && typeof options.body === 'object') {
            config.body = JSON.stringify(options.body);
        }
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
    
    /**
     * GET request
     */
    static async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }

    /**
     * POST request
     */
    static async post(endpoint, data, isMultipart = false) {
        if (isMultipart) {
            return this.upload(endpoint, data);
        }
        return this.request(endpoint, {
            method: 'POST',
            body: data
        });
    }

    /**
     * PUT request
     */
    static async put(endpoint, data, isMultipart = false) {
        if (isMultipart) {
            return this.uploadWithMethod(endpoint, data, 'PUT');
        }
        return this.request(endpoint, {
            method: 'PUT',
            body: data
        });
    }

    /**
     * DELETE request
     */
    static async delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE'
        });
    }

    /**
     * Upload file
     */
    static async upload(endpoint, formData) {
        return this.uploadWithMethod(endpoint, formData, 'POST');
    }

    /**
     * Upload file with custom HTTP method
     */
    static async uploadWithMethod(endpoint, formData, method = 'POST') {
        const url = `${API_BASE_URL}/${endpoint}`;

        try {
            const response = await fetch(url, {
                method: method,
                body: formData,
                credentials: 'include'
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Upload failed');
            }

            return data;
        } catch (error) {
            console.error('Upload Error:', error);
            throw error;
        }
    }
    
    // Authentication
    static login(email, password, remember = false) {
        return this.request('auth/login', {
            method: 'POST',
            body: { email, password, remember }
        });
    }
    
    static register(firstName, lastName, email, password) {
        return this.request('auth/register', {
            method: 'POST',
            body: { first_name: firstName, last_name: lastName, email, password }
        });
    }
    
    static logout() {
        return this.request('auth/logout', { method: 'POST' });
    }
    
    static getCurrentUser() {
        return this.request('auth/me');
    }
    
    // Users
    static getUser(id) {
        return this.request(`users/${id}`);
    }
    
    static updateUser(id, data) {
        return this.request(`users/${id}`, {
            method: 'PUT',
            body: data
        });
    }
    
    // Releases
    static getReleases(page = 1, limit = 10) {
        return this.request(`releases?page=${page}&limit=${limit}`);
    }
    
    static getRelease(id) {
        return this.request(`releases/${id}`);
    }
    
    static createRelease(formData) {
        return this.upload('releases', formData);
    }
    
    static updateRelease(id, data) {
        return this.request(`releases/${id}`, {
            method: 'PUT',
            body: data
        });
    }
    
    static deleteRelease(id) {
        return this.request(`releases/${id}`, { method: 'DELETE' });
    }
    
    // Analytics
    static getAnalytics() {
        return this.request('analytics');
    }

    static getTopArtists(limit = 10, offset = 0) {
        return this.request(`analytics/artists?limit=${limit}&offset=${offset}`);
    }

    static getTopTracks(limit = 10, offset = 0) {
        return this.request(`analytics/tracks?limit=${limit}&offset=${offset}`);
    }
    
    // Royalties
    static getRoyalties() {
        return this.request('royalties');
    }
    
    static requestPayment(amount) {
        return this.request('royalties/request', {
            method: 'POST',
            body: { amount }
        });
    }
    
    // Payments
    static getPaymentMethods() {
        return this.request('payments');
    }
    
    static savePaymentMethod(data) {
        return this.request('payments', {
            method: 'POST',
            body: data
        });
    }
    
    // Tickets
    static getTickets() {
        return this.request('tickets');
    }
    
    static getTicket(id) {
        return this.request(`tickets/${id}`);
    }
    
    static createTicket(subject, message) {
        return this.request('tickets', {
            method: 'POST',
            body: { subject, message }
        });
    }
}

