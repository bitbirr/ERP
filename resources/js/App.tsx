import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { LocalizationProvider } from '@mui/x-date-pickers';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';

// Components
import Layout from './components/layout/Layout';
import ProtectedRoute from './components/ProtectedRoute';
import Login from './pages/auth/Login';
import Dashboard from './pages/Dashboard';

// Module Pages
import Users from './Pages/users/Users';
import UserForm from './Pages/users/UserForm';
import Products from './Pages/products/Products';
import ProductForm from './Pages/products/ProductForm';
import Inventory from './Pages/inventory/Inventory';
import StockMovements from './Pages/inventory/StockMovements';
import StockTransfer from './Pages/inventory/StockTransfer';
import Customers from './Pages/customers/Customers';
import CustomerForm from './Pages/customers/CustomerForm';
import Orders from './Pages/sales/Orders';
import Journals from './Pages/finance/Journals';
import JournalForm from './Pages/finance/JournalForm';
import GLAccounts from './Pages/finance/GLAccounts';
import BankAccounts from './Pages/finance/BankAccounts';
import TelebirrDashboard from './Pages/telebirr/Dashboard';
import Agents from './Pages/telebirr/Agents';
import AgentForm from './Pages/telebirr/AgentForm';
import Transactions from './Pages/telebirr/Transactions';
import TransactionForm from './Pages/telebirr/TransactionForm';
import Reports from './Pages/reports/Reports';

// Context
import { AuthProvider } from './contexts/AuthContext';

// Theme
const theme = createTheme({
  palette: {
    mode: 'light',
    primary: {
      main: '#1976d2',
    },
    secondary: {
      main: '#dc004e',
    },
  },
  typography: {
    fontFamily: '"Roboto", "Helvetica", "Arial", sans-serif',
  },
});

// Query Client
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      retry: 1,
    },
  },
});

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>
        <LocalizationProvider dateAdapter={AdapterDateFns}>
          <CssBaseline />
          <AuthProvider>
            <Router>
              <Routes>
                {/* Public Routes */}
                <Route path="/login" element={<Login />} />

                {/* Protected Routes */}
                <Route path="/" element={
                  <ProtectedRoute>
                    <Layout />
                  </ProtectedRoute>
                }>
                  <Route index element={<Dashboard />} />

                  {/* User Management */}
                  <Route path="users" element={<Users />} />
                  <Route path="users/new" element={<UserForm />} />
                  <Route path="users/:id/edit" element={<UserForm />} />

                  {/* Product Management */}
                  <Route path="products" element={<Products />} />
                  <Route path="products/new" element={<ProductForm />} />
                  <Route path="products/:id/edit" element={<ProductForm />} />

                  {/* Inventory Management */}
                  <Route path="inventory" element={<Inventory />} />
                  <Route path="inventory/movements" element={<StockMovements />} />
                  <Route path="inventory/transfer" element={<StockTransfer />} />

                  {/* Customer Management */}
                  <Route path="customers" element={<Customers />} />
                  <Route path="customers/new" element={<CustomerForm />} />
                  <Route path="customers/:id/edit" element={<CustomerForm />} />

                  {/* Sales Management */}
                  <Route path="sales/orders" element={<Orders />} />

                  {/* Finance Management */}
                  <Route path="finance/journals" element={<Journals />} />
                  <Route path="finance/journals/new" element={<JournalForm />} />
                  <Route path="finance/journals/:id/edit" element={<JournalForm />} />
                  <Route path="finance/accounts" element={<GLAccounts />} />
                  <Route path="finance/bank-accounts" element={<BankAccounts />} />

                  {/* Telebirr Management */}
                  <Route path="telebirr" element={<TelebirrDashboard />} />
                  <Route path="telebirr/agents" element={<Agents />} />
                  <Route path="telebirr/agents/new" element={<AgentForm />} />
                  <Route path="telebirr/agents/:id/edit" element={<AgentForm />} />
                  <Route path="telebirr/transactions" element={<Transactions />} />
                  <Route path="telebirr/transactions/new" element={<TransactionForm />} />
                  <Route path="telebirr/transactions/:id" element={<TransactionForm />} />

                  {/* Reports */}
                  <Route path="reports" element={<Reports />} />
                </Route>
              </Routes>
            </Router>
          </AuthProvider>
        </LocalizationProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}

export default App;