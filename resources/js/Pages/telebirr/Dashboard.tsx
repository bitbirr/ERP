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

  // Fetch summary data
  const { data: agentBalances, isLoading: isLoadingBalances } = useQuery({
    queryKey: ['telebirr-agent-balances'],
    queryFn: () => telebirrService.getAgentBalances(),
  });

  const { data: transactionSummary, isLoading: isLoadingSummary } = useQuery({
    queryKey: ['telebirr-transaction-summary'],
    queryFn: () => telebirrService.getTransactionSummary({
      date_from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      date_to: new Date().toISOString().split('T')[0],
    }),
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
      value: agentBalances?.data?.length || 0,
      icon: <PeopleIcon />,
      color: 'primary',
    },
    {
      title: 'Active Agents',
      value: agentBalances?.data?.filter((agent: any) => agent.agent.status === 'Active').length || 0,
      icon: <PeopleIcon />,
      color: 'success',
    },
    {
      title: 'Total Transactions (30 days)',
      value: transactionSummary?.totals?.count || 0,
      icon: <ReceiptIcon />,
      color: 'secondary',
    },
    {
      title: 'Total Amount (30 days)',
      value: `ETB ${transactionSummary?.totals?.amount?.toLocaleString() || 0}`,
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
                      {isLoadingBalances || isLoadingSummary ? (
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

      {/* Recent Activity */}
      <Typography variant="h5" gutterBottom sx={{ mt: 4 }}>
        Recent Activity
      </Typography>

      <Alert severity="info">
        Recent transactions and agent activities will be displayed here.
      </Alert>
    </Box>
  );
};

export default TelebirrDashboard;