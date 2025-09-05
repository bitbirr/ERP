import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Box,
  Button,
  Typography,
  Alert,
  CircularProgress,
  Chip,
} from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';
import { DataGrid, GridColDef, GridToolbar } from '@mui/x-data-grid';
import { orderService, Order } from '../../services/orderService';

const Orders: React.FC = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);

  // Fetch orders
  const { data, isLoading, error: queryError } = useQuery({
    queryKey: ['orders'],
    queryFn: () => orderService.getOrders({ per_page: 50 }),
  });

  // Delete order mutation
  const deleteMutation = useMutation({
    mutationFn: (orderId: string) => orderService.deleteOrder(orderId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['orders'] });
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to delete order');
    },
  });

  // Approve order mutation
  const approveMutation = useMutation({
    mutationFn: (orderId: string) => orderService.approveOrder(orderId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['orders'] });
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to approve order');
    },
  });

  // Cancel order mutation
  const cancelMutation = useMutation({
    mutationFn: (orderId: string) => orderService.cancelOrder(orderId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['orders'] });
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to cancel order');
    },
  });

  const handleCreateOrder = () => {
    navigate('/sales/orders/new');
  };

  const handleEditOrder = (orderId: string) => {
    navigate(`/sales/orders/${orderId}/edit`);
  };

  const handleViewOrder = (orderId: string) => {
    navigate(`/sales/orders/${orderId}`);
  };

  const handleDeleteOrder = (orderId: string) => {
    if (window.confirm('Are you sure you want to delete this order?')) {
      deleteMutation.mutate(orderId);
    }
  };

  const handleApproveOrder = (orderId: string) => {
    if (window.confirm('Are you sure you want to approve this order?')) {
      approveMutation.mutate(orderId);
    }
  };

  const handleCancelOrder = (orderId: string) => {
    if (window.confirm('Are you sure you want to cancel this order?')) {
      cancelMutation.mutate(orderId);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending': return 'warning';
      case 'approved': return 'success';
      case 'cancelled': return 'error';
      default: return 'default';
    }
  };

  const columns: GridColDef[] = [
    { field: 'order_number', headerName: 'Order Number', width: 150 },
    {
      field: 'status',
      headerName: 'Status',
      width: 120,
      renderCell: (params) => (
        <Chip
          label={params.value}
          color={getStatusColor(params.value)}
          size="small"
        />
      ),
    },
    {
      field: 'customer',
      headerName: 'Customer',
      width: 200,
      valueGetter: (params) => params.row.customer?.name || 'N/A',
    },
    {
      field: 'grand_total',
      headerName: 'Total',
      width: 120,
      valueFormatter: (params) => `$${params.value?.toFixed(2)}`,
    },
    {
      field: 'created_at',
      headerName: 'Created',
      width: 180,
      valueFormatter: (params) => new Date(params.value).toLocaleDateString(),
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 300,
      renderCell: (params) => (
        <Box>
          <Button
            size="small"
            onClick={() => handleViewOrder(params.row.id)}
            sx={{ mr: 1 }}
          >
            View
          </Button>
          {params.row.status === 'pending' && (
            <>
              <Button
                size="small"
                onClick={() => handleEditOrder(params.row.id)}
                sx={{ mr: 1 }}
              >
                Edit
              </Button>
              <Button
                size="small"
                onClick={() => handleApproveOrder(params.row.id)}
                color="success"
                sx={{ mr: 1 }}
              >
                Approve
              </Button>
              <Button
                size="small"
                color="error"
                onClick={() => handleCancelOrder(params.row.id)}
                sx={{ mr: 1 }}
              >
                Cancel
              </Button>
              <Button
                size="small"
                color="error"
                onClick={() => handleDeleteOrder(params.row.id)}
              >
                Delete
              </Button>
            </>
          )}
        </Box>
      ),
    },
  ];

  if (isLoading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="200px">
        <CircularProgress />
      </Box>
    );
  }

  if (queryError) {
    return (
      <Alert severity="error">
        Failed to load orders. Please try again.
      </Alert>
    );
  }

  const orders = data?.data || [];

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">Customer Orders</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={handleCreateOrder}
        >
          Create Order
        </Button>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      <div style={{ height: 600, width: '100%' }}>
        <DataGrid
          rows={orders}
          columns={columns}
          pageSize={10}
          rowsPerPageOptions={[10, 25, 50]}
          components={{ Toolbar: GridToolbar }}
          disableSelectionOnClick
          getRowId={(row) => row.id}
        />
      </div>
    </Box>
  );
};

export default Orders;