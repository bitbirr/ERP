import React, { useState, useEffect } from 'react';
import {
  Grid,
  Card,
  CardContent,
  Typography,
  Box,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Alert,
  AlertTitle,
  Chip,
} from '@mui/material';
import {
  People as PeopleIcon,
  Inventory as InventoryIcon,
  ShoppingCart as ShoppingCartIcon,
  AccountBalance as AccountBalanceIcon,
  Business as BusinessIcon,
  Person as PersonIcon,
  PhoneAndroid as PhoneIcon,
} from '@mui/icons-material';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
} from 'recharts';
import reportService, { DashboardData } from '../services/reportService';

const Dashboard: React.FC = () => {
  const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchDashboardData = async () => {
      try {
        const data = await reportService.getDashboard();
        setDashboardData(data);
      } catch (error) {
        console.error('Failed to fetch dashboard data:', error);
        setError('Failed to load dashboard data');
      } finally {
        setLoading(false);
      }
    };

    fetchDashboardData();
  }, []);

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'ETB',
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const summaryCards = [
    {
      title: 'Total Customers',
      value: dashboardData?.summary.total_customers || 0,
      icon: <PeopleIcon sx={{ fontSize: 40, color: 'primary.main' }} />,
    },
    {
      title: 'Total Products',
      value: dashboardData?.summary.total_products || 0,
      icon: <InventoryIcon sx={{ fontSize: 40, color: 'secondary.main' }} />,
    },
    {
      title: 'Total Agents',
      value: dashboardData?.summary.total_agents || 0,
      icon: <PhoneIcon sx={{ fontSize: 40, color: 'success.main' }} />,
    },
    {
      title: 'Total Users',
      value: dashboardData?.summary.total_users || 0,
      icon: <PersonIcon sx={{ fontSize: 40, color: 'warning.main' }} />,
    },
    {
      title: 'Total Branches',
      value: dashboardData?.summary.total_branches || 0,
      icon: <BusinessIcon sx={{ fontSize: 40, color: 'info.main' }} />,
    },
    {
      title: 'Total Revenue',
      value: formatCurrency(dashboardData?.summary.total_revenue || 0),
      icon: <AccountBalanceIcon sx={{ fontSize: 40, color: 'error.main' }} />,
    },
  ];

  const chartColors = ['#8884d8', '#82ca9d', '#ffc658', '#ff7c7c', '#8dd1e1'];

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="400px">
        <Typography variant="h6">Loading dashboard...</Typography>
      </Box>
    );
  }

  if (error) {
    return (
      <Box p={3}>
        <Alert severity="error">
          <AlertTitle>Error</AlertTitle>
          {error}
        </Alert>
      </Box>
    );
  }

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Dashboard
      </Typography>

      {/* Summary Cards */}
      <Grid container spacing={3} mb={4}>
        {summaryCards.map((stat, index) => (
          <Grid item xs={12} sm={6} md={4} lg={2} key={index}>
            <Card>
              <CardContent>
                <Box display="flex" alignItems="center" justifyContent="space-between">
                  <Box>
                    <Typography color="textSecondary" gutterBottom>
                      {stat.title}
                    </Typography>
                    <Typography variant="h5" component="div">
                      {stat.value}
                    </Typography>
                  </Box>
                  {stat.icon}
                </Box>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      {/* Charts Section */}
      <Grid container spacing={3} mb={4}>
        {/* Transactions per Branch Chart */}
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Transactions per Branch
              </Typography>
              <Box height={300}>
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={dashboardData?.transactions_per_branch || []}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="branch_name" />
                    <YAxis />
                    <Tooltip />
                    <Bar dataKey="transaction_count" fill="#8884d8" />
                  </BarChart>
                </ResponsiveContainer>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        {/* Top Products by Volume Chart */}
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Top Products by Volume
              </Typography>
              <Box height={300}>
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={dashboardData?.top_products_by_volume || []}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="name" />
                    <YAxis />
                    <Tooltip />
                    <Bar dataKey="total_volume" fill="#82ca9d" />
                  </BarChart>
                </ResponsiveContainer>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Recent Transactions Table */}
      <Card mb={4}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Recent Transactions
          </Typography>
          <TableContainer component={Paper}>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Type</TableCell>
                  <TableCell>Description</TableCell>
                  <TableCell>Date</TableCell>
                  <TableCell align="right">Amount</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {dashboardData?.recent_transactions?.slice(0, 10).map((transaction) => (
                  <TableRow key={transaction.id}>
                    <TableCell>
                      <Chip
                        label={transaction.type}
                        size="small"
                        color={
                          transaction.type.includes('Customer') ? 'primary' :
                          transaction.type.includes('Product') ? 'secondary' :
                          transaction.type.includes('Telebirr') ? 'success' : 'default'
                        }
                      />
                    </TableCell>
                    <TableCell>{transaction.description}</TableCell>
                    <TableCell>{formatDate(transaction.date)}</TableCell>
                    <TableCell align="right">
                      {transaction.amount ? formatCurrency(transaction.amount) : '-'}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>

      {/* Low Stock Alert */}
      {dashboardData?.low_stock_items && dashboardData.low_stock_items.length > 0 && (
        <Card>
          <CardContent>
            <Typography variant="h6" gutterBottom color="error">
              Low Stock Alert
            </Typography>
            <Grid container spacing={2}>
              {dashboardData.low_stock_items.map((item) => (
                <Grid item xs={12} sm={6} md={4} key={item.id}>
                  <Alert severity="warning">
                    <AlertTitle>{item.product_name}</AlertTitle>
                    <Typography variant="body2">
                      Current Stock: {item.current_stock}
                    </Typography>
                    <Typography variant="body2">
                      Reorder Threshold: {item.reorder_threshold}
                    </Typography>
                    <Typography variant="body2">
                      Branch: {item.branch_name}
                    </Typography>
                  </Alert>
                </Grid>
              ))}
            </Grid>
          </CardContent>
        </Card>
      )}
    </Box>
  );
};

export default Dashboard;