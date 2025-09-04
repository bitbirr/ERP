import React from 'react';
import {
  Grid,
  Card,
  CardContent,
  Typography,
  Box,
} from '@mui/material';
import {
  People as PeopleIcon,
  Inventory as InventoryIcon,
  ShoppingCart as ShoppingCartIcon,
  AccountBalance as AccountBalanceIcon,
} from '@mui/icons-material';

const Dashboard: React.FC = () => {
  const stats = [
    {
      title: 'Total Users',
      value: '0',
      icon: <PeopleIcon sx={{ fontSize: 40, color: 'primary.main' }} />,
    },
    {
      title: 'Products',
      value: '0',
      icon: <InventoryIcon sx={{ fontSize: 40, color: 'secondary.main' }} />,
    },
    {
      title: 'Active Orders',
      value: '0',
      icon: <ShoppingCartIcon sx={{ fontSize: 40, color: 'success.main' }} />,
    },
    {
      title: 'Revenue',
      value: '$0',
      icon: <AccountBalanceIcon sx={{ fontSize: 40, color: 'warning.main' }} />,
    },
  ];

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Dashboard
      </Typography>

      <Grid container spacing={3}>
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
                  </Box>
                  {stat.icon}
                </Box>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      <Box mt={4}>
        <Typography variant="h5" gutterBottom>
          Recent Activity
        </Typography>
        <Card>
          <CardContent>
            <Typography variant="body2" color="textSecondary">
              No recent activity to display.
            </Typography>
          </CardContent>
        </Card>
      </Box>
    </Box>
  );
};

export default Dashboard;