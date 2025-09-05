const API_BASE = '/api';

// Ensure window.axios is available
declare global {
  interface Window {
    axios: any;
  }
}

export interface User {
  id: string;
  name: string;
  email: string;
  email_verified_at?: string;
  roles?: Role[];
  created_at: string;
  updated_at: string;
}

export interface CreateUserData {
  name: string;
  email: string;
  password: string;
  password_confirmation?: string;
  email_verified_at?: string;
}

export interface Role {
  id: number;
  name: string;
  capabilities: string[];
}

export const userService = {
  // Get all users with pagination, search, sorting, and filters
  getUsers: async (params?: {
    page?: number;
    per_page?: number;
    search?: string;
    sort_by?: string;
    sort_order?: 'asc' | 'desc';
    roles?: string[];
    email_verified?: 'verified' | 'not_verified';
  }) => {
    console.log('Making request to:', `${API_BASE}/users`, 'with params:', params);
    try {
      const response = await window.axios.get(`${API_BASE}/users`, { params });
      console.log('Response received:', response.status, response.data);
      return response.data;
    } catch (error) {
      console.error('Error in getUsers:', error);
      throw error;
    }
  },

  // Create a new user
  createUser: async (userData: CreateUserData): Promise<User> => {
    const response = await window.axios.post(`${API_BASE}/users`, userData);
    return response.data;
  },

  // Get a specific user
  getUser: async (id: string): Promise<User> => {
    const response = await window.axios.get(`${API_BASE}/users/${id}`);
    return response.data;
  },

  // Update a user
  updateUser: async (id: string, userData: Partial<CreateUserData>): Promise<User> => {
    const response = await window.axios.patch(`${API_BASE}/users/${id}`, userData);
    return response.data;
  },

  // Delete a user
  deleteUser: async (id: string): Promise<void> => {
    await window.axios.delete(`${API_BASE}/users/${id}`);
  },

  // Get all roles
  getRoles: async (): Promise<Role[]> => {
    const response = await window.axios.get(`${API_BASE}/rbac/roles`);
    return response.data;
  },

  // Create a role
  createRole: async (roleData: { name: string; capabilities: string[] }): Promise<Role> => {
    const response = await window.axios.post(`${API_BASE}/rbac/roles`, roleData);
    return response.data;
  },

  // Update a role
  updateRole: async (id: number, roleData: { name?: string; capabilities?: string[] }): Promise<Role> => {
    const response = await window.axios.patch(`${API_BASE}/rbac/roles/${id}`, roleData);
    return response.data;
  },

  // Assign role to user
  assignRole: async (userId: string, roleId: number): Promise<void> => {
    await window.axios.post(`${API_BASE}/rbac/users/${userId}/roles`, { role_id: roleId });
  },

  // Get user permissions
  getUserPermissions: async (userId: string) => {
    const response = await window.axios.get(`${API_BASE}/rbac/users/${userId}/permissions`);
    return response.data;
  },
};