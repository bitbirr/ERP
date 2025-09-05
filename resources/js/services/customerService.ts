const API_BASE = '/api';

// Ensure window.axios is available
declare global {
  interface Window {
    axios: any;
  }
}

export interface CustomerStats {
  total_customers: number;
  active_customers: number;
  inactive_customers: number;
}

export const customerService = {
  // Get customer statistics
  getStats: async (): Promise<CustomerStats> => {
    try {
      const response = await window.axios.get(`${API_BASE}/customers/stats`);
      return response.data;
    } catch (error) {
      console.error('Error fetching customer stats:', error);
      throw error;
    }
  },
};