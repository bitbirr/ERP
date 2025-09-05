import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  Box,
  Typography,
  Grid,
  Card,
  CardContent,
  Button,
  Alert,
  CircularProgress,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
} from '@mui/material';
import {
  People as PeopleIcon,
  Receipt as ReceiptIcon,
  AccountBalance as AccountBalanceIcon,
  Assessment as AssessmentIcon,
  Add as AddIcon,
  List as ListIcon,
} from '@mui/icons-material';
import { telebirrService } from '../../services/telebirrService';

const TelebirrDashboard: React.FC = () => {
  const navigate = useNavigate();

  // Fetch dashboard data
  const { data: dashboardData, isLoading: isLoadingDashboard } = useQuery({
    queryKey: ['telebirr-dashboard'],
    queryFn: () => telebirrService.getDashboard(),
    refetchOnWindowFocus: true,
    refetchInterval: 30000, // Refetch every 30 seconds
  });

  const quickActions = [
    {
      title: 'Manage Agents',
      description: 'View and manage Telebirr agents',
      icon: <PeopleIcon sx={{ fontSize: 40 }} />,
      action: () => navigate('/telebirr/agents'),
      color: 'primary',
    },
    {
      title: 'View Transactions',
      description: 'Browse all Telebirr transactions',
      icon: <ReceiptIcon sx={{ fontSize: 40 }} />,
      action: () => navigate('/telebirr/transactions'),
      color: 'secondary',
    },
    {
      title: 'New Transaction',
      description: 'Create a new Telebirr transaction',
      icon: <AddIcon sx={{ fontSize: 40 }} />,
      action: () => navigate('/telebirr/transactions/new'),
      color: 'success',
    },
    {
      title: 'Reports',
      description: 'View Telebirr reports and analytics',
      icon: <AssessmentIcon sx={{ fontSize: 40 }} />,
      action: () => navigate('/reports'),
      color: 'info',
    },
  ];

  const summaryCards = [
    {
      title: 'Total Agents',
      value: dashboardData?.agent_counts?.total || 0,
      icon: <PeopleIcon />,
      color: 'primary',
    },
    {
      title: 'Active Agents',
      value: dashboardData?.agent_counts?.active || 0,
      icon: <PeopleIcon />,
      color: 'success',
    },
    {
      title: 'Total Transactions (30 days)',
      value: dashboardData?.transaction_summary?.totals?.count || 0,
      icon: <ReceiptIcon />,
      color: 'secondary',
    },
    {
      title: 'Total Amount (30 days)',
      value: `ETB ${dashboardData?.transaction_summary?.totals?.amount?.toLocaleString() || 0}`,
      icon: <AccountBalanceIcon />,
      color: 'info',
    },
  ];

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Telebirr Management Dashboard
      </Typography>

      <Typography variant="body1" color="text.secondary" sx={{ mb: 4 }}>
        Manage Telebirr agents, transactions, and financial operations
      </Typography>

      {/* Summary Cards */}
      <Grid container spacing={3} sx={{ mb: 4 }}>
        {summaryCards.map((card, index) => (
          <Grid item xs={12} sm={6} md={3} key={index}>
            <Card>
              <CardContent>
                <Box display="flex" alignItems="center" justifyContent="space-between">
                  <Box>
                    <Typography variant="h6" color="text.secondary">
                      {card.title}
                    </Typography>
                    <Typography variant="h4">
                      {isLoadingDashboard ? (
                        <CircularProgress size={20} />
                      ) : (
                        card.value
                      )}
                    </Typography>
                  </Box>
                  <Box color={`${card.color}.main`}>
                    {card.icon}
                  </Box>
                </Box>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      {/* Quick Actions */}
      <Typography variant="h5" gutterBottom sx={{ mt: 4 }}>
        Quick Actions
      </Typography>

      <Grid container spacing={3}>
        {quickActions.map((action, index) => (
          <Grid item xs={12} sm={6} md={3} key={index}>
            <Card
              sx={{
                cursor: 'pointer',
                '&:hover': { boxShadow: 3 },
                height: '100%',
                display: 'flex',
                flexDirection: 'column',
              }}
              onClick={action.action}
            >
              <CardContent sx={{ flexGrow: 1, textAlign: 'center' }}>
                <Box color={`${action.color}.main`} sx={{ mb: 2 }}>
                  {action.icon}
                </Box>
                <Typography variant="h6" gutterBottom>
                  {action.title}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {action.description}
                </Typography>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      {/* Daily Transaction Amounts */}
      <Typography variant="h5" gutterBottom sx={{ mt: 4 }}>
        Daily Transaction Amounts (Last 30 Days)
      </Typography>

      {dashboardData?.transaction_summary?.daily_amounts && dashboardData.transaction_summary.daily_amounts.length > 0 ? (
        <TableContainer component={Paper} sx={{ mt: 2 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>Date</TableCell>
                <TableCell align="right">Amount (ETB)</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {dashboardData.transaction_summary.daily_amounts.map((day: any) => (
                <TableRow key={day.date}>
                  <TableCell>{new Date(day.date).toLocaleDateString()}</TableCell>
                  <TableCell align="right">{day.amount.toLocaleString()}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      ) : (
        <Alert severity="info" sx={{ mt: 2 }}>
          No transaction data available for the selected period.
        </Alert>
      )}

      {/* Recent Activity */}
      <Typography variant="h5" gutterBottom sx={{ mt: 4 }}>
        Recent Transactions
      </Typography>

      {dashboardData?.recent_transactions && dashboardData.recent_transactions.length > 0 ? (
        <TableContainer component={Paper} sx={{ mt: 2 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>Type</TableCell>
                <TableCell>Agent</TableCell>
                <TableCell align="right">Amount (ETB)</TableCell>
                <TableCell>Status</TableCell>
                <TableCell>Date</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {dashboardData.recent_transactions.map((transaction: any) => (
                <TableRow key={transaction.id}>
                  <TableCell>{transaction.tx_type}</TableCell>
                  <TableCell>{transaction.agent?.short_code || 'N/A'}</TableCell>
                  <TableCell align="right">{transaction.amount.toLocaleString()}</TableCell>
                  <TableCell>{transaction.status}</TableCell>
                  <TableCell>{new Date(transaction.created_at).toLocaleDateString()}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      ) : (
        <Alert severity="info" sx={{ mt: 2 }}>
          No recent transactions found.
        </Alert>
      )}
    </Box>
  );
};

export default TelebirrDashboard;