import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Enable cookies for session-based authentication
window.axios.defaults.withCredentials = true;

// Add request interceptor to include Authorization token
window.axios.interceptors.request.use(
  (config) => {
    // Add Authorization header if token exists
    const token = localStorage.getItem('auth_token');
    if (token && !config.headers.Authorization) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    // Add CSRF token for non-GET requests (for web routes)
    if (config.method !== 'get' && !config.url?.startsWith('/api/')) {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (csrfToken) {
        config.headers['X-CSRF-TOKEN'] = csrfToken;
      }
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Add response interceptor to handle auth errors
window.axios.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid
      console.log('Axios interceptor: 401 detected, token expired or invalid');

      // Clear authentication data
      localStorage.removeItem('auth_token');
      localStorage.removeItem('auth_user');
      delete window.axios.defaults.headers.common['Authorization'];

      // Use React's forceLogout if available, otherwise fallback to direct redirect
      if (window.authForceLogout) {
        window.authForceLogout();
      } else {
        window.location.href = '/login';
      }
    } else if (error.response?.status === 419) {
      // CSRF token mismatch (for web routes)
      console.log('Axios interceptor: 419 detected, CSRF token mismatch');
      // This might not require logout for API routes, but for consistency we'll handle it
      if (window.authForceLogout) {
        window.authForceLogout();
      } else {
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);
