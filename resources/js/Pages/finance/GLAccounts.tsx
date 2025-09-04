import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  Box,
  Typography,
  Alert,
  CircularProgress,
  Chip,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  TextField,
  Grid,
  Paper,
  Button,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
} from '@mui/material';
import { AccountBalance as BalanceIcon } from '@mui/icons-material';
import { DataGrid, GridColDef, GridToolbar } from '@mui/x-data-grid';
import { financeService, GlAccount, AccountBalance } from '../../services/financeService';

const GLAccounts: React.FC = () => {
  const [filters, setFilters] = useState({
    type: '',
    status: 'ACTIVE',
    is_postable: '',
  });
  const [selectedAccount, setSelectedAccount] = useState<GlAccount | null>(null);
  const [balanceDialogOpen, setBalanceDialogOpen] = useState(false);
  const [accountBalance, setAccountBalance] = useState<AccountBalance | null>(null);

  // Fetch accounts
  const { data, isLoading, error: queryError } = useQuery({
    queryKey: ['accounts', filters],
    queryFn: () => financeService.getAccounts({
      type: filters.type || undefined,
      status: filters.status || undefined,
      is_postable: filters.is_postable ? filters.is_postable === 'true' : undefined,
    }),
  });

  // Fetch account balance
  const { data: balanceData, isLoading: isLoadingBalance } = useQuery({
    queryKey: ['account-balance', selectedAccount?.id],
    queryFn: () => selectedAccount ? financeService.getAccountBalance(selectedAccount.id) : null,
    enabled: !!selectedAccount && balanceDialogOpen,
  });

  const handleViewBalance = (account: GlAccount) => {
    setSelectedAccount(account);
    setBalanceDialogOpen(true);
  };

  const handleCloseBalanceDialog = () => {
    setBalanceDialogOpen(false);
    setSelectedAccount(null);
    setAccountBalance(null);
  };

  const getTypeColor = (type: string) => {
    switch (type) {
      case 'ASSET': return 'primary';
      case 'LIABILITY': return 'secondary';
      case 'EQUITY': return 'success';
      case 'REVENUE': return 'info';
      case 'EXPENSE': return 'warning';
      default: return 'default';
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'ACTIVE': return 'success';
      case 'INACTIVE': return 'error';
      default: return 'default';
    }
  };

  const columns: GridColDef[] = [
    { field: 'code', headerName: 'Account Code', width: 150 },
    { field: 'name', headerName: 'Account Name', width: 300 },
    {
      field: 'type',
      headerName: 'Type',
      width: 120,
      renderCell: (params) => (
        <Chip
          label={params.value}
          color={getTypeColor(params.value)}
          size="small"
        />
      ),
    },
    {
      field: 'normal_balance',
      headerName: 'Normal Balance',
      width: 130,
      renderCell: (params) => (
        <Chip
          label={params.value}
          variant="outlined"
          size="small"
        />
      ),
    },
    {
      field: 'level',
      headerName: 'Level',
      width: 80,
      align: 'center',
    },
    {
      field: 'is_postable',
      headerName: 'Postable',
      width: 100,
      renderCell: (params) => (
        <Chip
          label={params.value ? 'Yes' : 'No'}
          color={params.value ? 'success' : 'default'}
          size="small"
        />
      ),
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
      field: 'actions',
      headerName: 'Actions',
      width: 120,
      renderCell: (params) => {
        const account = params.row as GlAccount;
        return (
          <Box>
            <Button
              size="small"
              startIcon={<BalanceIcon />}
              onClick={() => handleViewBalance(account)}
              sx={{ mr: 1 }}
            >
              Balance
            </Button>
          </Box>
        );
      },
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
        Failed to load GL accounts. Please try again.
      </Alert>
    );
  }

  const accounts = data?.data || [];

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        General Ledger Accounts
      </Typography>

      {/* Filters */}
      <Paper sx={{ p: 2, mb: 3 }}>
        <Typography variant="h6" gutterBottom>
          Filters
        </Typography>
        <Grid container spacing={2}>
          <Grid item xs={12} sm={6} md={3}>
            <FormControl fullWidth size="small">
              <InputLabel>Type</InputLabel>
              <Select
                value={filters.type}
                label="Type"
                onChange={(e) => setFilters({ ...filters, type: e.target.value })}
              >
                <MenuItem value="">All Types</MenuItem>
                <MenuItem value="ASSET">Asset</MenuItem>
                <MenuItem value="LIABILITY">Liability</MenuItem>
                <MenuItem value="EQUITY">Equity</MenuItem>
                <MenuItem value="REVENUE">Revenue</MenuItem>
                <MenuItem value="EXPENSE">Expense</MenuItem>
              </Select>
            </FormControl>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <FormControl fullWidth size="small">
              <InputLabel>Status</InputLabel>
              <Select
                value={filters.status}
                label="Status"
                onChange={(e) => setFilters({ ...filters, status: e.target.value })}
              >
                <MenuItem value="">All Status</MenuItem>
                <MenuItem value="ACTIVE">Active</MenuItem>
                <MenuItem value="INACTIVE">Inactive</MenuItem>
              </Select>
            </FormControl>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <FormControl fullWidth size="small">
              <InputLabel>Postable</InputLabel>
              <Select
                value={filters.is_postable}
                label="Postable"
                onChange={(e) => setFilters({ ...filters, is_postable: e.target.value })}
              >
                <MenuItem value="">All</MenuItem>
                <MenuItem value="true">Postable Only</MenuItem>
                <MenuItem value="false">Non-Postable Only</MenuItem>
              </Select>
            </FormControl>
          </Grid>
        </Grid>
      </Paper>

      <div style={{ height: 600, width: '100%' }}>
        <DataGrid
          rows={accounts}
          columns={columns}
          pageSize={10}
          rowsPerPageOptions={[10, 25, 50]}
          components={{ Toolbar: GridToolbar }}
          disableSelectionOnClick
          getRowId={(row) => row.id}
        />
      </div>

      {/* Account Balance Dialog */}
      <Dialog
        open={balanceDialogOpen}
        onClose={handleCloseBalanceDialog}
        maxWidth="md"
        fullWidth
      >
        <DialogTitle>
          Account Balance - {selectedAccount?.code} - {selectedAccount?.name}
        </DialogTitle>
        <DialogContent>
          {isLoadingBalance ? (
            <Box display="flex" justifyContent="center" p={3}>
              <CircularProgress />
            </Box>
          ) : balanceData ? (
            <Grid container spacing={2}>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Account Code"
                  value={balanceData.account_code}
                  InputProps={{ readOnly: true }}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Account Name"
                  value={balanceData.account_name}
                  InputProps={{ readOnly: true }}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Current Balance"
                  value={balanceData.balance.toFixed(2)}
                  InputProps={{ readOnly: true }}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="As of Date"
                  value={new Date(balanceData.as_of_date).toLocaleDateString()}
                  InputProps={{ readOnly: true }}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Total Debit"
                  value={balanceData.debit_total.toFixed(2)}
                  InputProps={{ readOnly: true }}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Total Credit"
                  value={balanceData.credit_total.toFixed(2)}
                  InputProps={{ readOnly: true }}
                />
              </Grid>
            </Grid>
          ) : (
            <Typography>No balance information available</Typography>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={handleCloseBalanceDialog}>Close</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default GLAccounts;