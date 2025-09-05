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
  TextField,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
} from '@mui/material';
import { Add as AddIcon, Visibility as ViewIcon } from '@mui/icons-material';
import { DataGrid, GridColDef, GridToolbar } from '@mui/x-data-grid';
import { telebirrService, TelebirrTransaction } from '../../services/telebirrService';

const Transactions: React.FC = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState({
    tx_type: '',
    status: '',
    search: '',
    date_from: '',
    date_to: '',
  });

  // Fetch transactions
  const { data, isLoading, error: queryError } = useQuery({
    queryKey: ['telebirr-transactions', filters],
    queryFn: () => telebirrService.getTransactions({
      per_page: 50,
      tx_type: filters.tx_type || undefined,
      status: filters.status || undefined,
      search: filters.search || undefined,
      date_from: filters.date_from || undefined,
      date_to: filters.date_to || undefined,
    }),
  });

  // Void transaction mutation
  const voidMutation = useMutation({
    mutationFn: (transactionId: string) => telebirrService.voidTransaction(transactionId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telebirr-transactions'] });
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to void transaction');
    },
  });

  const handleCreateTransaction = () => {
    navigate('/telebirr/transactions/new');
  };

  const handleViewTransaction = (transactionId: string) => {
    navigate(`/telebirr/transactions/${transactionId}`);
  };

  const handleVoidTransaction = (transactionId: string) => {
    if (window.confirm('Are you sure you want to void this transaction?')) {
      voidMutation.mutate(transactionId);
    }
  };

  const handleFilterChange = (field: string, value: string) => {
    setFilters(prev => ({ ...prev, [field]: value }));
  };

  const getTransactionTypeColor = (txType: string) => {
    switch (txType) {
      case 'TOPUP': return 'primary';
      case 'ISSUE': return 'success';
      case 'REPAY': return 'info';
      case 'LOAN': return 'warning';
      default: return 'default';
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'Posted': return 'success';
      case 'Voided': return 'error';
      case 'Draft': return 'warning';
      default: return 'default';
    }
  };

  const columns: GridColDef[] = [
    { field: 'id', headerName: 'ID', width: 100 },
    {
      field: 'tx_type',
      headerName: 'Type',
      width: 120,
      renderCell: (params) => (
        <Chip
          label={params.value}
          color={getTransactionTypeColor(params.value)}
          size="small"
        />
      ),
    },
    {
      field: 'amount',
      headerName: 'Amount',
      width: 120,
      valueFormatter: (params) => `ETB ${params?.value?.toLocaleString()}`,
    },
    {
      field: 'agent',
      headerName: 'Agent',
      width: 150,
      valueGetter: (params) => params?.row?.agent?.short_code || 'N/A',
      renderCell: (params) => params?.row?.agent?.short_code || 'N/A',
    },
    {
      field: 'status',
      headerName: 'Status',
      width: 100,
      renderCell: (params) => (
        <Chip
          label={params.value}
          color={getStatusColor(params.value)}
          size="small"
        />
      ),
    },
    {
      field: 'external_ref',
      headerName: 'Reference',
      width: 150,
    },
    {
      field: 'created_at',
      headerName: 'Created',
      width: 180,
      valueFormatter: (params) => params?.value ? new Date(params.value).toLocaleDateString() : 'N/A',
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 120,
      renderCell: (params) => (
        <Box>
          <Button
            size="small"
            onClick={() => handleViewTransaction(params?.row?.id)}
            sx={{ mr: 1 }}
          >
            <ViewIcon fontSize="small" />
          </Button>
          {params?.row?.status === 'Posted' && (
            <Button
              size="small"
              color="error"
              onClick={() => handleVoidTransaction(params?.row?.id)}
            >
              Void
            </Button>
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
        Failed to load transactions. Please try again.
      </Alert>
    );
  }

  const rawTransactions = data?.data || [];
  const transactions = rawTransactions.filter(t => t != null && t !== undefined);

  // Filter out null/undefined transactions

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">Telebirr Transactions</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={handleCreateTransaction}
        >
          New Transaction
        </Button>
      </Box>

      {/* Filters */}
      <Box display="flex" gap={2} mb={3} flexWrap="wrap">
        <TextField
          label="Search"
          variant="outlined"
          size="small"
          value={filters.search}
          onChange={(e) => handleFilterChange('search', e.target.value)}
          placeholder="Search by reference or remarks"
          sx={{ minWidth: 200 }}
        />

        <FormControl size="small" sx={{ minWidth: 120 }}>
          <InputLabel>Type</InputLabel>
          <Select
            value={filters.tx_type}
            label="Type"
            onChange={(e) => handleFilterChange('tx_type', e.target.value)}
          >
            <MenuItem value="">All</MenuItem>
            <MenuItem value="TOPUP">Topup</MenuItem>
            <MenuItem value="ISSUE">Issue</MenuItem>
            <MenuItem value="REPAY">Repay</MenuItem>
            <MenuItem value="LOAN">Loan</MenuItem>
          </Select>
        </FormControl>

        <FormControl size="small" sx={{ minWidth: 120 }}>
          <InputLabel>Status</InputLabel>
          <Select
            value={filters.status}
            label="Status"
            onChange={(e) => handleFilterChange('status', e.target.value)}
          >
            <MenuItem value="">All</MenuItem>
            <MenuItem value="Posted">Posted</MenuItem>
            <MenuItem value="Voided">Voided</MenuItem>
            <MenuItem value="Draft">Draft</MenuItem>
          </Select>
        </FormControl>

        <TextField
          label="From Date"
          type="date"
          size="small"
          value={filters.date_from}
          onChange={(e) => handleFilterChange('date_from', e.target.value)}
          InputLabelProps={{ shrink: true }}
        />

        <TextField
          label="To Date"
          type="date"
          size="small"
          value={filters.date_to}
          onChange={(e) => handleFilterChange('date_to', e.target.value)}
          InputLabelProps={{ shrink: true }}
        />
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      <div style={{ height: 600, width: '100%' }}>
        <DataGrid
          rows={transactions}
          columns={columns}
          pageSize={10}
          rowsPerPageOptions={[10, 25, 50]}
          components={{ Toolbar: GridToolbar }}
          disableSelectionOnClick
          getRowId={(row) => row?.id || `temp-${Math.random()}`}
        />
      </div>
    </Box>
  );
};

export default Transactions;