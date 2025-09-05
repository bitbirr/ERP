import React, { useState, useMemo } from 'react';
import axios from 'axios';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
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
  Card,
  CardContent,
  IconButton,
  Tooltip,
  Snackbar,
  Tabs,
  Tab,
  Accordion,
  AccordionSummary,
  AccordionDetails,
  Switch,
  FormControlLabel,
  InputAdornment,
} from '@mui/material';
import {
  AccountBalance as AccountIcon,
  Add as AddIcon,
  Edit as EditIcon,
  Delete as DeleteIcon,
  Search as SearchIcon,
  FilterList as FilterIcon,
  ExpandMore as ExpandMoreIcon,
  Help as HelpIcon,
  Refresh as RefreshIcon,
  Visibility as ViewIcon,
} from '@mui/icons-material';
import { DataGrid, GridColDef, GridToolbar, GridPaginationModel } from '@mui/x-data-grid';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';
import {
  bankAccountService,
  BankAccount,
  BankAccountFilters,
  CreateBankAccountData,
  UpdateBankAccountData,
  BankAccountSummary
} from '../../services/bankAccountService';

const BankAccounts: React.FC = () => {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState<BankAccountFilters>({
    status: 'active',
    per_page: 10,
  });
  const [paginationModel, setPaginationModel] = useState<GridPaginationModel>({
    page: 0,
    pageSize: 10,
  });
  const [selectedAccount, setSelectedAccount] = useState<BankAccount | null>(null);
  const [createDialogOpen, setCreateDialogOpen] = useState(false);
  const [editDialogOpen, setEditDialogOpen] = useState(false);
  const [viewDialogOpen, setViewDialogOpen] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [snackbar, setSnackbar] = useState<{ open: boolean; message: string; severity: 'success' | 'error' }>({
    open: false,
    message: '',
    severity: 'success',
  });
  const [helpOpen, setHelpOpen] = useState(false);
  const [activeTab, setActiveTab] = useState(0);
  const [auditLogs, setAuditLogs] = useState([]);
  const [auditLoading, setAuditLoading] = useState(false);

  // Form states
  const [createForm, setCreateForm] = useState<CreateBankAccountData>({
    name: '',
    external_number: '',
    account_number: '',
    gl_account_id: '',
    account_type: '',
    balance: 0,
    branch_id: '',
    customer_id: '',
  });
  const [editForm, setEditForm] = useState<UpdateBankAccountData>({});

  // Fetch accounts
  const { data, isLoading, error: queryError, refetch } = useQuery({
    queryKey: ['bank-accounts', filters, paginationModel],
    queryFn: () => bankAccountService.getAccounts({
      ...filters,
      page: paginationModel.page + 1,
      per_page: paginationModel.pageSize,
    }),
  });

  // Fetch audit logs
  const fetchAuditLogs = async () => {
    setAuditLoading(true);
    try {
      const response = await axios.get('/api/audit/logs', {
        params: { entity_type: 'BankAccount' }
      });
      setAuditLogs(response.data.data || []);
    } catch (error) {
      console.error('Failed to fetch audit logs:', error);
    } finally {
      setAuditLoading(false);
    }
  };

  // Fetch audit logs when audit tab is selected
  React.useEffect(() => {
    if (activeTab === 3) {
      fetchAuditLogs();
    }
  }, [activeTab]);

  // Mutations
  const createMutation = useMutation({
    mutationFn: bankAccountService.createAccount,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bank-accounts'] });
      setCreateDialogOpen(false);
      setCreateForm({
        name: '',
        external_number: '',
        account_number: '',
        gl_account_id: '',
        account_type: '',
        balance: 0,
        branch_id: '',
        customer_id: '',
      });
      setSnackbar({ open: true, message: 'Bank account created successfully', severity: 'success' });
    },
    onError: (error: any) => {
      setSnackbar({ open: true, message: error.response?.data?.message || 'Failed to create account', severity: 'error' });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: UpdateBankAccountData }) =>
      bankAccountService.updateAccount(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bank-accounts'] });
      setEditDialogOpen(false);
      setSelectedAccount(null);
      setSnackbar({ open: true, message: 'Bank account updated successfully', severity: 'success' });
    },
    onError: (error: any) => {
      setSnackbar({ open: true, message: error.response?.data?.message || 'Failed to update account', severity: 'error' });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: bankAccountService.deleteAccount,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bank-accounts'] });
      setDeleteDialogOpen(false);
      setSelectedAccount(null);
      setSnackbar({ open: true, message: 'Bank account deleted successfully', severity: 'success' });
    },
    onError: (error: any) => {
      setSnackbar({ open: true, message: error.response?.data?.message || 'Failed to delete account', severity: 'error' });
    },
  });

  const handleCreate = () => {
    createMutation.mutate(createForm);
  };

  const handleUpdate = () => {
    if (selectedAccount) {
      updateMutation.mutate({ id: selectedAccount.id, data: editForm });
    }
  };

  const handleDelete = () => {
    if (selectedAccount) {
      deleteMutation.mutate(selectedAccount.id);
    }
  };

  const handleEdit = (account: BankAccount) => {
    setSelectedAccount(account);
    setEditForm({
      name: account.name,
      external_number: account.external_number,
      account_number: account.account_number,
      gl_account_id: account.gl_account_id,
      account_type: account.account_type,
      balance: account.balance,
      branch_id: account.branch_id,
      customer_id: account.customer_id,
      is_active: account.is_active,
    });
    setEditDialogOpen(true);
  };

  const handleView = (account: BankAccount) => {
    setSelectedAccount(account);
    setViewDialogOpen(true);
  };

  const handleDeleteClick = (account: BankAccount) => {
    setSelectedAccount(account);
    setDeleteDialogOpen(true);
  };

  const getStatusColor = (isActive: boolean) => {
    return isActive ? 'success' : 'error';
  };

  const getTypeColor = (type: string) => {
    const colors: { [key: string]: string } = {
      'CHECKING': 'primary',
      'SAVINGS': 'secondary',
      'CREDIT': 'warning',
      'LOAN': 'info',
    };
    return colors[type] || 'default';
  };

  const columns: GridColDef[] = [
    { field: 'account_number', headerName: 'Account Number', width: 150 },
    { field: 'name', headerName: 'Account Name', width: 200 },
    {
      field: 'glAccount',
      headerName: 'GL Number',
      width: 120,
      valueGetter: (params) => params?.row?.glAccount?.code || '',
    },
    {
      field: 'account_type',
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
      field: 'balance',
      headerName: 'Balance',
      width: 120,
      align: 'right',
      valueFormatter: (params) => `$${params.value?.toFixed(2) || '0.00'}`,
    },
    {
      field: 'is_active',
      headerName: 'Status',
      width: 100,
      renderCell: (params) => (
        <Chip
          label={params.value ? 'Active' : 'Inactive'}
          color={getStatusColor(params.value)}
          size="small"
        />
      ),
    },
    {
      field: 'branch',
      headerName: 'Branch',
      width: 150,
      valueGetter: (params) => params?.row?.branch?.name || '',
    },
    {
      field: 'created_at',
      headerName: 'Created',
      width: 120,
      valueFormatter: (params) => new Date(params.value).toLocaleDateString(),
    },
    {
      field: 'updated_at',
      headerName: 'Updated',
      width: 120,
      valueFormatter: (params) => new Date(params.value).toLocaleDateString(),
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 200,
      renderCell: (params) => {
        const account = params.row as BankAccount;
        return (
          <Box>
            <Tooltip title="View Details">
              <IconButton size="small" onClick={() => handleView(account)}>
                <ViewIcon />
              </IconButton>
            </Tooltip>
            <Tooltip title="Edit">
              <IconButton size="small" onClick={() => handleEdit(account)}>
                <EditIcon />
              </IconButton>
            </Tooltip>
            <Tooltip title="Delete">
              <IconButton size="small" color="error" onClick={() => handleDeleteClick(account)}>
                <DeleteIcon />
              </IconButton>
            </Tooltip>
          </Box>
        );
      },
    },
  ];

  const summary = data?.summary;
  const accounts = data?.data?.data || [];
  const totalRows = data?.data?.total || 0;

  if (isLoading && !data) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="400px">
        <CircularProgress />
      </Box>
    );
  }

  if (queryError) {
    return (
      <Alert severity="error">
        Failed to load bank accounts. Please try again.
      </Alert>
    );
  }

  return (
    <LocalizationProvider dateAdapter={AdapterDateFns}>
      <Box>
        <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
          <Typography variant="h4" gutterBottom>
            Bank Account Management
          </Typography>
          <Box>
            <Tooltip title="Help & Tips">
              <IconButton onClick={() => setHelpOpen(!helpOpen)}>
                <HelpIcon />
              </IconButton>
            </Tooltip>
            <Tooltip title="Refresh">
              <IconButton onClick={() => refetch()}>
                <RefreshIcon />
              </IconButton>
            </Tooltip>
            <Button
              variant="contained"
              startIcon={<AddIcon />}
              onClick={() => setCreateDialogOpen(true)}
            >
              Add Account
            </Button>
          </Box>
        </Box>

        {/* Help Section */}
        {helpOpen && (
          <Accordion expanded={helpOpen} onChange={() => setHelpOpen(!helpOpen)} sx={{ mb: 3 }}>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
              <Typography variant="h6">Help & Best Practices</Typography>
            </AccordionSummary>
            <AccordionDetails>
              <Typography variant="body2" paragraph>
                <strong>Creating Accounts:</strong> Ensure GL number matches existing chart of accounts. Use descriptive names for easy identification.
              </Typography>
              <Typography variant="body2" paragraph>
                <strong>Balance Management:</strong> Accounts with non-zero balances cannot be deleted. Always verify balances before deletion.
              </Typography>
              <Typography variant="body2" paragraph>
                <strong>Search & Filter:</strong> Use advanced filters to quickly find specific accounts. Save common filter combinations for reuse.
              </Typography>
              <Typography variant="body2" paragraph>
                <strong>Security:</strong> Only authorized users can modify account details. All changes are logged for audit purposes.
              </Typography>
            </AccordionDetails>
          </Accordion>
        )}

        {/* Dashboard Summary */}
        <Grid container spacing={3} sx={{ mb: 3 }}>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Typography color="textSecondary" gutterBottom>
                  Total Accounts
                </Typography>
                <Typography variant="h4">
                  {summary?.total_accounts || 0}
                </Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Typography color="textSecondary" gutterBottom>
                  Active Accounts
                </Typography>
                <Typography variant="h4" color="success.main">
                  {summary?.active_accounts || 0}
                </Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Typography color="textSecondary" gutterBottom>
                  Total Balance
                </Typography>
                <Typography variant="h4" color="primary.main">
                  ${(Number(summary?.total_balance) || 0).toFixed(2)}
                </Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Typography color="textSecondary" gutterBottom>
                  Recent Transactions
                </Typography>
                <Typography variant="h4" color="info.main">
                  {summary?.recent_transactions?.length || 0}
                </Typography>
              </CardContent>
            </Card>
          </Grid>
        </Grid>

        {/* Tabs for different views */}
        <Tabs value={activeTab} onChange={(e, newValue) => setActiveTab(newValue)} sx={{ mb: 3 }}>
          <Tab label="All Accounts" />
          <Tab label="Active Only" />
          <Tab label="Inactive Only" />
          <Tab label="Audit Logs" />
        </Tabs>

        {/* Filters */}
        <Paper sx={{ p: 2, mb: 3 }}>
          <Typography variant="h6" gutterBottom>
            Filters & Search
          </Typography>
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={3}>
              <TextField
                fullWidth
                label="Account Name"
                value={filters.name || ''}
                onChange={(e) => setFilters({ ...filters, name: e.target.value })}
                InputProps={{
                  startAdornment: (
                    <InputAdornment position="start">
                      <SearchIcon />
                    </InputAdornment>
                  ),
                }}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <TextField
                fullWidth
                label="GL Number"
                value={filters.gl_number || ''}
                onChange={(e) => setFilters({ ...filters, gl_number: e.target.value })}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <FormControl fullWidth>
                <InputLabel>Account Type</InputLabel>
                <Select
                  value={filters.account_type || ''}
                  label="Account Type"
                  onChange={(e) => setFilters({ ...filters, account_type: e.target.value })}
                >
                  <MenuItem value="">All Types</MenuItem>
                  <MenuItem value="CHECKING">Checking</MenuItem>
                  <MenuItem value="SAVINGS">Savings</MenuItem>
                  <MenuItem value="CREDIT">Credit</MenuItem>
                  <MenuItem value="LOAN">Loan</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <FormControl fullWidth>
                <InputLabel>Status</InputLabel>
                <Select
                  value={filters.status || ''}
                  label="Status"
                  onChange={(e) => setFilters({ ...filters, status: e.target.value })}
                >
                  <MenuItem value="">All Status</MenuItem>
                  <MenuItem value="active">Active</MenuItem>
                  <MenuItem value="inactive">Inactive</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <TextField
                fullWidth
                label="Min Balance"
                type="number"
                value={filters.balance_min || ''}
                onChange={(e) => setFilters({ ...filters, balance_min: parseFloat(e.target.value) || undefined })}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <TextField
                fullWidth
                label="Max Balance"
                type="number"
                value={filters.balance_max || ''}
                onChange={(e) => setFilters({ ...filters, balance_max: parseFloat(e.target.value) || undefined })}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <DatePicker
                label="Created From"
                value={filters.created_from ? new Date(filters.created_from) : null}
                onChange={(date) => setFilters({ ...filters, created_from: date?.toISOString().split('T')[0] })}
                slotProps={{ textField: { fullWidth: true } }}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <DatePicker
                label="Created To"
                value={filters.created_to ? new Date(filters.created_to) : null}
                onChange={(date) => setFilters({ ...filters, created_to: date?.toISOString().split('T')[0] })}
                slotProps={{ textField: { fullWidth: true } }}
              />
            </Grid>
          </Grid>
        </Paper>

        {/* Data Table */}
        {activeTab === 0 && (
          <div style={{ height: 600, width: '100%' }}>
            <DataGrid
              rows={accounts}
              columns={columns}
              rowCount={totalRows}
              loading={isLoading}
              pageSizeOptions={[10, 25, 50]}
              paginationModel={paginationModel}
              paginationMode="server"
              onPaginationModelChange={setPaginationModel}
              components={{ Toolbar: GridToolbar }}
              disableSelectionOnClick
              getRowId={(row) => row.id}
            />
          </div>
        )}

        {/* Audit Logs Table */}
        {activeTab === 3 && (
          <div style={{ height: 600, width: '100%' }}>
            <DataGrid
              rows={auditLogs}
              columns={[
                { field: 'action', headerName: 'Action', width: 150 },
                { field: 'user', headerName: 'User', width: 150, valueGetter: (params) => params?.row?.actor?.name || 'System' },
                { field: 'created_at', headerName: 'Date', width: 180, valueFormatter: (params) => new Date(params.value).toLocaleString() },
                { field: 'old_values', headerName: 'Previous Values', width: 200, valueFormatter: (params) => JSON.stringify(params.value) },
                { field: 'new_values', headerName: 'New Values', width: 200, valueFormatter: (params) => JSON.stringify(params.value) },
              ]}
              loading={auditLoading}
              components={{ Toolbar: GridToolbar }}
              disableSelectionOnClick
              getRowId={(row) => row.id}
            />
          </div>
        )}

        {/* Create Account Dialog */}
        <Dialog open={createDialogOpen} onClose={() => setCreateDialogOpen(false)} maxWidth="md" fullWidth>
          <DialogTitle>Create Bank Account</DialogTitle>
          <DialogContent>
            <Grid container spacing={2} sx={{ mt: 1 }}>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Account Name"
                  value={createForm.name}
                  onChange={(e) => setCreateForm({ ...createForm, name: e.target.value })}
                  required
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="External Number"
                  value={createForm.external_number}
                  onChange={(e) => setCreateForm({ ...createForm, external_number: e.target.value })}
                  required
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Account Number"
                  value={createForm.account_number}
                  onChange={(e) => setCreateForm({ ...createForm, account_number: e.target.value })}
                  required
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="GL Account ID"
                  value={createForm.gl_account_id}
                  onChange={(e) => setCreateForm({ ...createForm, gl_account_id: e.target.value })}
                  required
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <FormControl fullWidth>
                  <InputLabel>Account Type</InputLabel>
                  <Select
                    value={createForm.account_type}
                    label="Account Type"
                    onChange={(e) => setCreateForm({ ...createForm, account_type: e.target.value })}
                    required
                  >
                    <MenuItem value="CHECKING">Checking</MenuItem>
                    <MenuItem value="SAVINGS">Savings</MenuItem>
                    <MenuItem value="CREDIT">Credit</MenuItem>
                    <MenuItem value="LOAN">Loan</MenuItem>
                  </Select>
                </FormControl>
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Initial Balance"
                  type="number"
                  value={createForm.balance}
                  onChange={(e) => setCreateForm({ ...createForm, balance: parseFloat(e.target.value) || 0 })}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Branch ID"
                  value={createForm.branch_id}
                  onChange={(e) => setCreateForm({ ...createForm, branch_id: e.target.value })}
                  required
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Customer ID"
                  value={createForm.customer_id}
                  onChange={(e) => setCreateForm({ ...createForm, customer_id: e.target.value })}
                  required
                />
              </Grid>
            </Grid>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setCreateDialogOpen(false)}>Cancel</Button>
            <Button onClick={handleCreate} variant="contained" disabled={createMutation.isPending}>
              {createMutation.isPending ? <CircularProgress size={20} /> : 'Create'}
            </Button>
          </DialogActions>
        </Dialog>

        {/* Edit Account Dialog */}
        <Dialog open={editDialogOpen} onClose={() => setEditDialogOpen(false)} maxWidth="md" fullWidth>
          <DialogTitle>Edit Bank Account</DialogTitle>
          <DialogContent>
            <Grid container spacing={2} sx={{ mt: 1 }}>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Account Name"
                  value={editForm.name || ''}
                  onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="External Number"
                  value={editForm.external_number || ''}
                  onChange={(e) => setEditForm({ ...editForm, external_number: e.target.value })}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Account Number"
                  value={editForm.account_number || ''}
                  onChange={(e) => setEditForm({ ...editForm, account_number: e.target.value })}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="GL Account ID"
                  value={editForm.gl_account_id || ''}
                  onChange={(e) => setEditForm({ ...editForm, gl_account_id: e.target.value })}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <FormControl fullWidth>
                  <InputLabel>Account Type</InputLabel>
                  <Select
                    value={editForm.account_type || ''}
                    label="Account Type"
                    onChange={(e) => setEditForm({ ...editForm, account_type: e.target.value })}
                  >
                    <MenuItem value="CHECKING">Checking</MenuItem>
                    <MenuItem value="SAVINGS">Savings</MenuItem>
                    <MenuItem value="CREDIT">Credit</MenuItem>
                    <MenuItem value="LOAN">Loan</MenuItem>
                  </Select>
                </FormControl>
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  fullWidth
                  label="Balance"
                  type="number"
                  value={editForm.balance || 0}
                  onChange={(e) => setEditForm({ ...editForm, balance: parseFloat(e.target.value) || 0 })}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <FormControlLabel
                  control={
                    <Switch
                      checked={editForm.is_active ?? true}
                      onChange={(e) => setEditForm({ ...editForm, is_active: e.target.checked })}
                    />
                  }
                  label="Active"
                />
              </Grid>
            </Grid>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setEditDialogOpen(false)}>Cancel</Button>
            <Button onClick={handleUpdate} variant="contained" disabled={updateMutation.isPending}>
              {updateMutation.isPending ? <CircularProgress size={20} /> : 'Update'}
            </Button>
          </DialogActions>
        </Dialog>

        {/* View Account Dialog */}
        <Dialog open={viewDialogOpen} onClose={() => setViewDialogOpen(false)} maxWidth="md" fullWidth>
          <DialogTitle>Account Details</DialogTitle>
          <DialogContent>
            {selectedAccount && (
              <Grid container spacing={2} sx={{ mt: 1 }}>
                <Grid item xs={12} sm={6}>
                  <TextField fullWidth label="Account Name" value={selectedAccount.name} InputProps={{ readOnly: true }} />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField fullWidth label="Account Number" value={selectedAccount.account_number} InputProps={{ readOnly: true }} />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField fullWidth label="External Number" value={selectedAccount.external_number} InputProps={{ readOnly: true }} />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField fullWidth label="GL Account" value={selectedAccount.glAccount?.code || ''} InputProps={{ readOnly: true }} />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField fullWidth label="Type" value={selectedAccount.account_type} InputProps={{ readOnly: true }} />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField fullWidth label="Balance" value={`$${selectedAccount.balance.toFixed(2)}`} InputProps={{ readOnly: true }} />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField fullWidth label="Branch" value={selectedAccount.branch?.name || ''} InputProps={{ readOnly: true }} />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField fullWidth label="Status" value={selectedAccount.is_active ? 'Active' : 'Inactive'} InputProps={{ readOnly: true }} />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField fullWidth label="Created" value={new Date(selectedAccount.created_at).toLocaleString()} InputProps={{ readOnly: true }} />
                </Grid>
                <Grid item xs={12} sm={6}>
                  <TextField fullWidth label="Updated" value={new Date(selectedAccount.updated_at).toLocaleString()} InputProps={{ readOnly: true }} />
                </Grid>
              </Grid>
            )}
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setViewDialogOpen(false)}>Close</Button>
          </DialogActions>
        </Dialog>

        {/* Delete Confirmation Dialog */}
        <Dialog open={deleteDialogOpen} onClose={() => setDeleteDialogOpen(false)}>
          <DialogTitle>Confirm Delete</DialogTitle>
          <DialogContent>
            <Typography>
              Are you sure you want to delete the account "{selectedAccount?.name}"?
              This action cannot be undone.
            </Typography>
            {selectedAccount && selectedAccount.balance > 0 && (
              <Alert severity="warning" sx={{ mt: 2 }}>
                This account has a balance of ${selectedAccount.balance.toFixed(2)} and cannot be deleted.
              </Alert>
            )}
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDeleteDialogOpen(false)}>Cancel</Button>
            <Button
              onClick={handleDelete}
              color="error"
              variant="contained"
              disabled={deleteMutation.isPending || (selectedAccount?.balance || 0) > 0}
            >
              {deleteMutation.isPending ? <CircularProgress size={20} /> : 'Delete'}
            </Button>
          </DialogActions>
        </Dialog>

        {/* Snackbar for notifications */}
        <Snackbar
          open={snackbar.open}
          autoHideDuration={6000}
          onClose={() => setSnackbar({ ...snackbar, open: false })}
        >
          <Alert onClose={() => setSnackbar({ ...snackbar, open: false })} severity={snackbar.severity}>
            {snackbar.message}
          </Alert>
        </Snackbar>
      </Box>
    </LocalizationProvider>
  );
};

export default BankAccounts;