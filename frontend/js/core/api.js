/**
 * BONHAGAN TRANSIT — Core API Client
 */

const Api = {
    async request(endpoint, options = {}) {
        const url = endpoint.startsWith('http') ? endpoint : `${API_CONFIG.BASE_URL}${endpoint}`;
        
        const defaultHeaders = {
            'Accept': 'application/json'
        };

        const csrfToken = Session.getCsrfToken();
        if (csrfToken) {
            defaultHeaders['X-CSRF-Token'] = csrfToken;
        }

        // Only set Content-Type to application/json if body is not FormData
        if (options.body && !(options.body instanceof FormData)) {
            defaultHeaders['Content-Type'] = 'application/json';
            if (typeof options.body !== 'string') {
                options.body = JSON.stringify(options.body);
            }
        }

        const config = {
            method: options.method || 'GET',
            headers: {
                ...defaultHeaders,
                ...(options.headers || {})
            },
            credentials: 'include', // Important: send cookies to PHP session
            ...options
        };

        try {
            const response = await fetch(url, config);
            let result;

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                const text = await response.text();
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    result = {
                        success: response.ok,
                        message: text || response.statusText,
                        data: null
                    };
                }
            }

            // Sync CSRF token if returned in header or body
            const newCsrf = response.headers.get('X-CSRF-Token') || (result.data && result.data.csrf_token);
            if (newCsrf) {
                Session.setCsrfToken(newCsrf);
            }

            // Handle unauthorized globally
            if (response.status === 401) {
                Session.clear();
                // If not already on login page, let components decide or redirect
            }

            if (!response.ok && result.success !== false) {
                result.success = false;
            }

            return result;
        } catch (error) {
            console.error('API request error:', error);
            return {
                success: false,
                message: error.message || 'Network error occurred. Please check connection.',
                data: null,
                errors: [error.message]
            };
        }
    },

    get(endpoint, params = {}) {
        let queryString = '';
        const keys = Object.keys(params);
        if (keys.length > 0) {
            const query = keys
                .filter(k => params[k] !== undefined && params[k] !== null && params[k] !== '')
                .map(k => `${encodeURIComponent(k)}=${encodeURIComponent(params[k])}`)
                .join('&');
            if (query) {
                queryString = (endpoint.includes('?') ? '&' : '?') + query;
            }
        }
        return this.request(`${endpoint}${queryString}`, { method: 'GET' });
    },

    post(endpoint, data = {}, isFormData = false) {
        return this.request(endpoint, {
            method: 'POST',
            body: isFormData ? data : data
        });
    },

    put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: data
        });
    },

    delete(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'DELETE',
            body: data
        });
    }
};

window.Api = Api;

