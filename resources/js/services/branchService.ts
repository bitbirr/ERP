const API_BASE = '/api';

// Ensure window.axios is available
declare global {
  interface Window {
    axios: any;
  }
}

export interface Branch {
  id: string;
  name: string;
  code: string;
  address?: string;
  phone?: string;
  manager?: string;
  location?: string;
  status: 'active' | 'inactive';
  created_at: string;
  updated_at: string;
  deleted_at?: string;
}

export interface BranchStats {
  total_branches: number;
  active_branches: number;
  inactive_branches: number;
}

export interface BranchFilters {
  name?: string;
  status?: string;
  page?: number;
  per_page?: number;
  sort?: string;
  direction?: 'asc' | 'desc';
}

export interface PaginatedBranches {
  data: Branch[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export const branchService = {
  // Get all branches with pagination and filters
  getBranches: async (filters: BranchFilters = {}): Promise<PaginatedBranches> => {
    try {
      const params = new URLSearchParams();
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          params.append(key, value.toString());
        }
      });

      const response = await window.axios.get(`${API_BASE}/branches?${params}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching branches:', error);
      throw error;
    }
  },

  // Get branch statistics
  getStats: async (): Promise<BranchStats> => {
    try {
      const response = await window.axios.get(`${API_BASE}/branches/stats`);
      return response.data;
    } catch (error) {
      console.error('Error fetching branch stats:', error);
      throw error;
    }
  },

  // Get single branch
  getBranch: async (id: string): Promise<Branch> => {
    try {
      const response = await window.axios.get(`${API_BASE}/branches/${id}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching branch:', error);
      throw error;
    }
  },

  // Create new branch
  createBranch: async (branchData: Omit<Branch, 'id' | 'created_at' | 'updated_at' | 'deleted_at'>): Promise<Branch> => {
    try {
      const response = await window.axios.post(`${API_BASE}/branches`, branchData);
      return response.data;
    } catch (error) {
      console.error('Error creating branch:', error);
      throw error;
    }
  },

  // Update branch
  updateBranch: async (id: string, branchData: Partial<Branch>): Promise<Branch> => {
    try {
      const response = await window.axios.patch(`${API_BASE}/branches/${id}`, branchData);
      return response.data;
    } catch (error) {
      console.error('Error updating branch:', error);
      throw error;
    }
  },

  // Delete branch (soft delete)
  deleteBranch: async (id: string): Promise<void> => {
    try {
      await window.axios.delete(`${API_BASE}/branches/${id}`);
    } catch (error) {
      console.error('Error deleting branch:', error);
      throw error;
    }
  },

  // Get branch UUIDs (if needed for external integration)
  getBranchUuids: async (): Promise<string[]> => {
    try {
      const response = await window.axios.get(`${API_BASE}/branches/uuids`);
      return response.data;
    } catch (error) {
      console.error('Error fetching branch UUIDs:', error);
      throw error;
    }
  },
};