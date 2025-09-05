import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Enable cookies for session-based authentication
window.axios.defaults.withCredentials = true;

// Add request interceptor to include CSRF token for non-GET requests
window.axios.interceptors.request.use(
  (config) => {
    // Add CSRF token for non-GET requests
    if (config.method !== 'get') {
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
    if (error.response?.status === 401 || error.response?.status === 419) {
      // Session expired or CSRF token mismatch, redirect to login
      localStorage.removeItem('auth_user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);
