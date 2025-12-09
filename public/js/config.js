/**
 * Application Configuration
 * Handles environment detection and path configuration for local and production
 * 
 * Local: localhost/trinity/ng
 * Production: trinity.futurewebhost.com.ng/ng
 */

const AppConfig = (function() {
    // Detect environment based on hostname
    const hostname = window.location.hostname;
    const isLocal = hostname === 'localhost' || hostname === '127.0.0.1';
    
    // Configuration for different environments
    const config = {
        local: {
            basePath: '/trinity/ng',           // URL prefix for local
            apiUrl: 'http://localhost/trinity/api',
            assetsPath: '/trinity/assets',
            uploadsPath: '/trinity/uploads'
        },
        production: {
            basePath: '/ng',                   // URL prefix for production
            apiUrl: 'https://trinity.futurewebhost.com.ng/api',
            assetsPath: '/assets',
            uploadsPath: '/uploads'
        }
    };
    
    // Get current environment config
    const currentConfig = isLocal ? config.local : config.production;
    
    return {
        // Environment info
        isLocal: isLocal,
        isProduction: !isLocal,
        hostname: hostname,
        
        // Paths
        basePath: currentConfig.basePath,
        apiUrl: currentConfig.apiUrl,
        assetsPath: currentConfig.assetsPath,
        uploadsPath: currentConfig.uploadsPath,
        
        /**
         * Get full URL for a page within the app
         * @param {string} page - Page path (e.g., 'dashboard', 'profile')
         * @returns {string} Full URL path
         */
        getPageUrl: function(page) {
            // Remove leading slash if present
            page = page.replace(/^\/+/, '');
            return `${this.basePath}/${page}`;
        },
        
        /**
         * Get full URL for an asset (images in assets folder)
         * @param {string} assetPath - Asset path (e.g., 'images/logo.png')
         * @returns {string} Full asset URL
         */
        getAssetUrl: function(assetPath) {
            assetPath = assetPath.replace(/^\/+/, '');
            return `${this.assetsPath}/${assetPath}`;
        },
        
        /**
         * Get full URL for an upload (user uploads)
         * @param {string} uploadPath - Upload path (e.g., 'artworks/cover.jpg')
         * @returns {string} Full upload URL
         */
        getUploadUrl: function(uploadPath) {
            uploadPath = uploadPath.replace(/^\/+/, '');
            return `${this.uploadsPath}/${uploadPath}`;
        },
        
        /**
         * Get API endpoint URL
         * @param {string} endpoint - API endpoint (e.g., 'auth/login')
         * @returns {string} Full API URL
         */
        getApiUrl: function(endpoint) {
            endpoint = endpoint.replace(/^\/+/, '');
            return `${this.apiUrl}/${endpoint}`;
        },
        
        /**
         * Debug: Log current configuration
         */
        debug: function() {
            console.log('AppConfig:', {
                environment: this.isLocal ? 'local' : 'production',
                hostname: this.hostname,
                basePath: this.basePath,
                apiUrl: this.apiUrl,
                assetsPath: this.assetsPath,
                uploadsPath: this.uploadsPath
            });
        },

        /**
         * Fix a relative path to use absolute path based on environment
         * Converts ../assets/... to /trinity/assets/... (local) or /assets/... (production)
         * @param {string} path - The path to fix
         * @returns {string} Fixed absolute path
         */
        fixPath: function(path) {
            if (!path) return path;

            // Already absolute path starting with http/https
            if (path.startsWith('http://') || path.startsWith('https://')) {
                return path;
            }

            // Handle ../assets/ paths
            if (path.includes('../assets/') || path.includes('./assets/')) {
                const cleanPath = path.replace(/^\.\.\/|^\.\//, '');
                return this.isLocal ? `/trinity/${cleanPath}` : `/${cleanPath}`;
            }

            // Handle ../uploads/ paths
            if (path.includes('../uploads/') || path.includes('./uploads/')) {
                const cleanPath = path.replace(/^\.\.\/|^\.\//, '');
                return this.isLocal ? `/trinity/${cleanPath}` : `/${cleanPath}`;
            }

            // Handle relative paths starting with ../
            if (path.startsWith('../')) {
                const cleanPath = path.replace(/^\.\.\//, '');
                return this.isLocal ? `/trinity/${cleanPath}` : `/${cleanPath}`;
            }

            // Handle relative paths starting with ./
            if (path.startsWith('./')) {
                const cleanPath = path.replace(/^\.\//, '');
                return `${this.basePath}/${cleanPath}`;
            }

            return path;
        },

        /**
         * Initialize and fix all paths on the page
         * Call this on DOMContentLoaded
         */
        initializePaths: function() {
            const self = this;

            // Fix all img src attributes
            document.querySelectorAll('img[src]').forEach(function(img) {
                const src = img.getAttribute('src');
                if (src && (src.startsWith('../') || src.startsWith('./'))) {
                    img.setAttribute('src', self.fixPath(src));
                }
            });

            // Fix all link href attributes (for favicons, stylesheets)
            document.querySelectorAll('link[href]').forEach(function(link) {
                const href = link.getAttribute('href');
                if (href && (href.startsWith('../') || href.startsWith('./'))) {
                    link.setAttribute('href', self.fixPath(href));
                }
            });

            // Fix all anchor href attributes
            document.querySelectorAll('a[href]').forEach(function(a) {
                const href = a.getAttribute('href');
                if (href && href.startsWith('./')) {
                    a.setAttribute('href', self.fixPath(href));
                }
            });

            // Fix background images in style attributes
            document.querySelectorAll('[style*="url("]').forEach(function(el) {
                const style = el.getAttribute('style');
                if (style) {
                    const fixedStyle = style.replace(/url\(['"]?(\.\.\/[^'")\s]+)['"]?\)/g, function(match, url) {
                        return 'url(' + self.fixPath(url) + ')';
                    });
                    el.setAttribute('style', fixedStyle);
                }
            });
        }
    };
})();

// Make it available globally
window.AppConfig = AppConfig;

// Auto-initialize paths when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        AppConfig.initializePaths();
    });
} else {
    // DOM already loaded
    AppConfig.initializePaths();
}

