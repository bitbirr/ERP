import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import axios from 'axios';

interface User {
  id: string;
  name: string;
  email: string;
}

interface AuthContextType {
  user: User | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
  isAuthenticated: boolean;
  loading: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

const API_BASE = '';

export const AuthProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Check for stored user session on app start
    const storedUser = localStorage.getItem('auth_user');
    console.log('AuthContext: Checking stored user on app start:', !!storedUser);

    if (storedUser) {
      const userData = JSON.parse(storedUser);
      console.log('AuthContext: Setting user from localStorage:', userData.name);
      setUser(userData);
    }

    setLoading(false);
  }, []);

  const login = async (email: string, password: string) => {
    try {
      // For session-based authentication, just call the login endpoint
      const response = await window.axios.post('/login', {
        email,
        password,
      });

      const { user: userData } = response.data;

      setUser(userData);
      localStorage.setItem('auth_user', JSON.stringify(userData));
    } catch (error: any) {
      throw new Error(error.response?.data?.message || 'Login failed. Please check your credentials.');
    }
  };

  const logout = async () => {
    console.log('AuthContext: Making logout request to:', '/logout');
    try {
      await window.axios.post('/logout');
      console.log('AuthContext: Logout request successful');
    } catch (error: any) {
      console.error('AuthContext: Logout error:', error.message, error.code, 'Status:', error.response?.status);
    } finally {
      setUser(null);
      localStorage.removeItem('auth_user');
    }
  };

  const value = {
    user,
    login,
    logout,
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