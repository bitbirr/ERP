import React, { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Grid,
  Card,
  CardContent,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  CircularProgress,
  Alert,
} from '@mui/material';
import {
  People as PeopleIcon,
  ShoppingCart as ShoppingCartIcon,
  Inventory as InventoryIcon,
  AttachMoney as MoneyIcon,
  Warning as WarningIcon,
  TrendingUp as TrendingUpIcon,
} from '@mui/icons-material';
import reportService, { DashboardData } from '../../services/reportService';

const Reports: React.FC = () => {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchDashboardData = async () => {
      try {
        const dashboardData = await reportService.getDashboard();
        setData(dashboardData);
      } catch (err) {
        setError('Failed to load dashboard data');
        console.error('Error fetching dashboard data:', err);
      } finally {
        setLoading(false);
      }
    };

    fetchDashboardData();
  }, []);

  if (loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="400px">
        <CircularProgress />
      </Box>
    );
  }

  if (error) {
    return (
      <Box p={3}>
        <Alert severity="error">{error}</Alert>
      </Box>
    );
  }

  if (!data) {
    return (
      <Box p={3}>
        <Alert severity="info">No data available</Alert>
      </Box>
    );
  }

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
  };

  const stats = [
    {
      title: 'Total Users',
      value: data.summary.total_users.toString(),
      icon: <PeopleIcon sx={{ fontSize: 40, color: 'primary.main' }} />,
    },
    {
      title: 'Total Customers',
      value: data.summary.total_customers.toString(),
      subtitle: `${data.summary.active_customers} active`,
      icon: <PeopleIcon sx={{ fontSize: 40, color: 'secondary.main' }} />,
    },
    {
      title: 'Total Orders',
      value: data.summary.total_orders.toString(),
      icon: <ShoppingCartIcon sx={{ fontSize: 40, color: 'success.main' }} />,
    },
    {
      title: 'Total Revenue',
      value: formatCurrency(data.summary.total_revenue),
      icon: <MoneyIcon sx={{ fontSize: 40, color: 'warning.main' }} />,
    },
    {
      title: 'Total Products',
      value: data.summary.total_products.toString(),
      subtitle: `${data.summary.active_products} active`,
      icon: <InventoryIcon sx={{ fontSize: 40, color: 'info.main' }} />,
    },
    {
      title: 'Inventory Value',
      value: formatCurrency(data.summary.total_inventory_value),
      icon: <InventoryIcon sx={{ fontSize: 40, color: 'success.main' }} />,
    },
    {
      title: 'Low Stock Items',
      value: data.summary.low_stock_items.toString(),
      icon: <WarningIcon sx={{ fontSize: 40, color: 'error.main' }} />,
    },
    {
      title: 'Stock Movements',
      value: data.summary.total_stock_movements.toString(),
      icon: <TrendingUpIcon sx={{ fontSize: 40, color: 'primary.main' }} />,
    },
  ];

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        System Reports Dashboard
      </Typography>

      {/* Summary Cards */}
      <Grid container spacing={3} sx={{ mb: 4 }}>
        {stats.map((stat, index) => (
          <Grid item xs={12} sm={6} md={3} key={index}>
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
                    {stat.subtitle && (
                      <Typography variant="body2" color="textSecondary">
                        {stat.subtitle}
                      </Typography>
                    )}
                  </Box>
                  {stat.icon}
                </Box>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      {/* Orders Summary */}
      <Grid container spacing={3} sx={{ mb: 4 }}>
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Orders by Status
              </Typography>
              <TableContainer component={Paper}>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Status</TableCell>
                      <TableCell align="right">Count</TableCell>
                      <TableCell align="right">Total Value</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {data.orders_summary.map((order) => (
                      <TableRow key={order.status}>
                        <TableCell component="th" scope="row">
                          {order.status}
                        </TableCell>
                        <TableCell align="right">{order.count}</TableCell>
                        <TableCell align="right">{formatCurrency(order.total)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Top Selling Products
              </Typography>
              <TableContainer component={Paper}>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Product</TableCell>
                      <TableCell align="right">Quantity</TableCell>
                      <TableCell align="right">Revenue</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {data.top_selling_products.slice(0, 5).map((product, index) => (
                      <TableRow key={index}>
                        <TableCell component="th" scope="row">
                          {product.name}
                        </TableCell>
                        <TableCell align="right">{product.total_quantity}</TableCell>
                        <TableCell align="right">{formatCurrency(product.total_revenue)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Low Stock Items */}
      <Card sx={{ mb: 4 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Low Stock Items (≤ 10 units)
          </Typography>
          <TableContainer component={Paper}>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Product</TableCell>
                  <TableCell>Branch</TableCell>
                  <TableCell align="right">Stock Level</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.low_stock_items.slice(0, 10).map((item) => (
                  <TableRow key={item.id}>
                    <TableCell component="th" scope="row">
                      {item.product?.name || 'N/A'}
                    </TableCell>
                    <TableCell>{item.branch?.name || 'N/A'}</TableCell>
                    <TableCell align="right">{item.on_hand}</TableCell>
                  </TableRow>
                ))}
                {data.low_stock_items.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={3} align="center">
                      No low stock items
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>

      {/* Recent Orders */}
      <Card>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Recent Orders
          </Typography>
          <TableContainer component={Paper}>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Order #</TableCell>
                  <TableCell>Customer</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell align="right">Total</TableCell>
                  <TableCell>Created By</TableCell>
                  <TableCell>Date</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.recent_orders.map((order) => (
                  <TableRow key={order.id}>
                    <TableCell component="th" scope="row">
                      {order.order_number}
                    </TableCell>
                    <TableCell>{order.customer?.name || 'N/A'}</TableCell>
                    <TableCell>{order.status}</TableCell>
                    <TableCell align="right">{formatCurrency(order.grand_total)}</TableCell>
                    <TableCell>{order.creator?.name || 'N/A'}</TableCell>
                    <TableCell>{formatDate(order.created_at)}</TableCell>
                  </TableRow>
                ))}
                {data.recent_orders.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6} align="center">
                      No recent orders
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>
    </Box>
  );
};

export default Reports;