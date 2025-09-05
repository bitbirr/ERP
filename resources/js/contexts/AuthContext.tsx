import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import axios from 'axios';

interface User {
  id: string;
  name: string;
  email: string;
}

interface AuthContextType {
  user: User | null;
  login: (email: string, password: string, deviceName?: string) => Promise<void>;
  logout: () => void;
  forceLogout: () => void;
  refreshToken: () => Promise<string | null>;
  isAuthenticated: boolean;
  loading: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

const API_BASE = '';

export const AuthProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  // Token management functions
  const getToken = () => localStorage.getItem('auth_token');
  const setToken = (token: string) => localStorage.setItem('auth_token', token);
  const removeToken = () => localStorage.removeItem('auth_token');

  useEffect(() => {
    // Check for stored token and user on app start
    const storedToken = getToken();
    const storedUser = localStorage.getItem('auth_user');
    console.log('AuthContext: Checking stored token and user on app start:', !!storedToken, !!storedUser);

    if (storedToken && storedUser) {
      const userData = JSON.parse(storedUser);
      console.log('AuthContext: Setting user from localStorage:', userData.name);
      setUser(userData);

      // Set the token in axios defaults for subsequent requests
      window.axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;
    }

    setLoading(false);
  }, []);

  const login = async (email: string, password: string, deviceName: string = 'web-app') => {
    try {
      console.log('AuthContext: Attempting Sanctum token login for:', email);

      // Use Sanctum token endpoint
      const response = await window.axios.post('/api/sanctum/token', {
        email,
        password,
        device_name: deviceName,
      });

      const { token, user: userData } = response.data;
      console.log('AuthContext: Login successful, received token and user data');

      // Store token and user data
      setToken(token);
      setUser(userData);
      localStorage.setItem('auth_user', JSON.stringify(userData));

      // Set authorization header for future requests
      window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } catch (error: any) {
      console.error('AuthContext: Login failed:', error.response?.data || error.message);
      throw new Error(error.response?.data?.message || 'Login failed. Please check your credentials.');
    }
  };

  const logout = async () => {
    console.log('AuthContext: Making logout request');
    try {
      // For Sanctum, we can optionally revoke the token on the server
      // but for now, we'll just clear local storage
      console.log('AuthContext: Logout request successful');
    } catch (error: any) {
      console.error('AuthContext: Logout error:', error.message, error.code, 'Status:', error.response?.status);
    } finally {
      // Clear all auth data
      setUser(null);
      removeToken();
      localStorage.removeItem('auth_user');
      delete window.axios.defaults.headers.common['Authorization'];
    }
  };

  const refreshToken = async (): Promise<string | null> => {
    try {
      console.log('AuthContext: Attempting token refresh');

      const response = await window.axios.post('/api/sanctum/refresh', {
        device_name: 'web-app',
      });

      const { token, user: userData } = response.data;
      console.log('AuthContext: Token refresh successful');

      // Update stored token and user data
      setToken(token);
      setUser(userData);
      localStorage.setItem('auth_user', JSON.stringify(userData));

      // Update authorization header
      window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

      return token;
    } catch (error: any) {
      console.error('AuthContext: Token refresh failed:', error.response?.data || error.message);
      // If refresh fails, trigger logout
      forceLogout();
      return null;
    }
  };

  const forceLogout = () => {
    console.log('AuthContext: Force logout triggered');
    setUser(null);
    removeToken();
    localStorage.removeItem('auth_user');
    delete window.axios.defaults.headers.common['Authorization'];
  };

  // Expose forceLogout globally for axios interceptor
  React.useEffect(() => {
    (window as any).authForceLogout = forceLogout;
  }, []);

  const value = {
    user,
    login,
    logout,
    forceLogout,
    refreshToken,
    isAuthenticated: !!user,
    loading
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};