import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { LocalizationProvider } from '@mui/x-date-pickers';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';

// Components
import Layout from './components/layout/Layout';
import Login from './pages/auth/Login';
import Dashboard from './pages/Dashboard';

// Module Pages
import Users from './pages/users/Users';
import UserForm from './pages/users/UserForm';
import Products from './pages/products/Products';
import ProductForm from './pages/products/ProductForm';
import Inventory from './pages/inventory/Inventory';
import StockMovements from './pages/inventory/StockMovements';
import StockTransfer from './pages/inventory/StockTransfer';
import Customers from './pages/customers/Customers';
import CustomerForm from './pages/customers/CustomerForm';
import Orders from './pages/sales/Orders';
import Journals from './pages/finance/Journals';
import JournalForm from './pages/finance/JournalForm';
import GLAccounts from './pages/finance/GLAccounts';
import TelebirrDashboard from './pages/telebirr/Dashboard';
import Agents from './pages/telebirr/Agents';
import AgentForm from './pages/telebirr/AgentForm';
import Transactions from './pages/telebirr/Transactions';
import TransactionForm from './pages/telebirr/TransactionForm';
import Reports from './pages/reports/Reports';

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
                <Route path="/" element={<Layout />}>
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