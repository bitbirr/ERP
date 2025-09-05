import React, { useState, useMemo } from 'react';
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
  Fab,
  Snackbar,
  Alert as MuiAlert,
  Tabs,
  Tab,
  Accordion,
  AccordionSummary,
  AccordionDetails,
  List,
  ListItem,
  ListItemText,
  Divider,
  Switch,
  FormControlLabel,
  InputAdornment,
  TablePagination,
} from '@mui/material';
import {
  AccountBalance as BalanceIcon,
  Add as AddIcon,
  Edit as EditIcon,
  Delete as DeleteIcon,
  Search as SearchIcon,
  FilterList as FilterIcon,
  Refresh as RefreshIcon,
  Help as HelpIcon,
  ExpandMore as ExpandMoreIcon,
  Save as SaveIcon,
  Cancel as CancelIcon,
  ViewList as ViewListIcon,
  Dashboard as DashboardIcon,
} from '@mui/icons-material';
import { DataGrid, GridColDef, GridToolbar, GridRenderCellParams } from '@mui/x-data-grid';
import { financeService, GlAccount, AccountBalance, CreateAccountData, UpdateAccountData, AccountSummary } from '../../services/financeService';

const GLAccounts: React.FC = () => {
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState(0);
  const [filters, setFilters] = useState({
    type: '',
    status: '',
    is_postable: '',
    search: '',
    branch_id: '',
  });
  const [selectedAccount, setSelectedAccount] = useState<GlAccount | null>(null);
  const [balanceDialogOpen, setBalanceDialogOpen] = useState(false);
  const [createDialogOpen, setCreateDialogOpen] = useState(false);
  const [editDialogOpen, setEditDialogOpen] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [viewDialogOpen, setViewDialogOpen] = useState(false);
  const [helpDialogOpen, setHelpDialogOpen] = useState(false);
  const [snackbar, setSnackbar] = useState({ open: false, message: '', severity: 'success' as 'success' | 'error' | 'info' });
  const [page, setPage] = useState(0);
  const [pageSize, setPageSize] = useState(25);
  const [sortModel, setSortModel] = useState([{ field: 'code', sort: 'asc' as 'asc' | 'desc' }]);

  const [createForm, setCreateForm] = useState<CreateAccountData>({
    name: '',
    type: 'ASSET',
    normal_balance: 'DEBIT',
    is_postable: true,
  });

  const [editForm, setEditForm] = useState<UpdateAccountData>({});

  // Fetch accounts summary
  const { data: summaryData, isLoading: isLoadingSummary } = useQuery({
    queryKey: ['accounts-summary'],
    queryFn: () => financeService.getAccountSummary(),
  });

  // Fetch accounts
  const { data, isLoading, error: queryError } = useQuery({
    queryKey: ['accounts', filters, page, pageSize, sortModel],
    queryFn: () => financeService.getAccounts({
      type: filters.type || undefined,
      status: filters.status || undefined,
      is_postable: filters.is_postable ? filters.is_postable === 'true' : undefined,
      search: filters.search || undefined,
      branch_id: filters.branch_id || undefined,
      page: page + 1,
      per_page: pageSize,
      sort: sortModel[0]?.field || 'code',
      order: sortModel[0]?.sort || 'asc',
    }),
  });

  // Fetch account balance
  const { data: balanceData, isLoading: isLoadingBalance } = useQuery({
    queryKey: ['account-balance', selectedAccount?.id],
    queryFn: () => selectedAccount ? financeService.getAccountBalance(selectedAccount.id) : null,
    enabled: !!selectedAccount && balanceDialogOpen,
  });

  // Mutations
  const createMutation = useMutation({
    mutationFn: (data: CreateAccountData) => financeService.createAccount(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['accounts'] });
      queryClient.invalidateQueries({ queryKey: ['accounts-summary'] });
      setCreateDialogOpen(false);
      setCreateForm({ name: '', type: 'ASSET', normal_balance: 'DEBIT', is_postable: true });
      setSnackbar({ open: true, message: 'Account created successfully', severity: 'success' });
    },
    onError: (error: any) => {
      setSnackbar({ open: true, message: error.response?.data?.message || 'Failed to create account', severity: 'error' });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: UpdateAccountData }) => financeService.updateAccount(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['accounts'] });
      setEditDialogOpen(false);
      setEditForm({});
      setSelectedAccount(null);
      setSnackbar({ open: true, message: 'Account updated successfully', severity: 'success' });
    },
    onError: (error: any) => {
      setSnackbar({ open: true, message: error.response?.data?.message || 'Failed to update account', severity: 'error' });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => financeService.deleteAccount(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['accounts'] });
      queryClient.invalidateQueries({ queryKey: ['accounts-summary'] });
      setDeleteDialogOpen(false);
      setSelectedAccount(null);
      setSnackbar({ open: true, message: 'Account deleted successfully', severity: 'success' });
    },
    onError: (error: any) => {
      setSnackbar({ open: true, message: error.response?.data?.message || 'Failed to delete account', severity: 'error' });
    },
  });

  const handleViewBalance = (account: GlAccount) => {
    setSelectedAccount(account);
    setBalanceDialogOpen(true);
  };

  const handleCloseBalanceDialog = () => {
    setBalanceDialogOpen(false);
    setSelectedAccount(null);
  };

  const handleCreateAccount = () => {
    setCreateDialogOpen(true);
  };

  const handleEditAccount = (account: GlAccount) => {
    setSelectedAccount(account);
    setEditForm({
      name: account.name,
      type: account.type,
      normal_balance: account.normal_balance,
      is_postable: account.is_postable,
      status: account.status,
      branch_id: account.branch_id,
    });
    setEditDialogOpen(true);
  };

  const handleDeleteAccount = (account: GlAccount) => {
    setSelectedAccount(account);
    setDeleteDialogOpen(true);
  };

  const handleViewAccount = (account: GlAccount) => {
    setSelectedAccount(account);
    setViewDialogOpen(true);
  };

  const handleCreateSubmit = () => {
    createMutation.mutate(createForm);
  };

  const handleEditSubmit = () => {
    if (selectedAccount) {
      updateMutation.mutate({ id: selectedAccount.id, data: editForm });
    }
  };

  const handleDeleteConfirm = () => {
    if (selectedAccount) {
      deleteMutation.mutate(selectedAccount.id);
    }
  };

  const handleCloseDialogs = () => {
    setCreateDialogOpen(false);
    setEditDialogOpen(false);
    setDeleteDialogOpen(false);
    setViewDialogOpen(false);
    setHelpDialogOpen(false);
    setSelectedAccount(null);
    setCreateForm({ name: '', type: 'ASSET', normal_balance: 'DEBIT', is_postable: true });
    setEditForm({});
  };

  const handleTabChange = (event: React.SyntheticEvent, newValue: number) => {
    setActiveTab(newValue);
  };

  const handlePageChange = (event: unknown, newPage: number) => {
    setPage(newPage);
  };

  const handlePageSizeChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    setPageSize(parseInt(event.target.value, 10));
    setPage(0);
  };

  const handleSortModelChange = (newSortModel: any) => {
    setSortModel(newSortModel);
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
      case 'ARCHIVED': return 'error';
      default: return 'default';
    }
  };

  const columns: GridColDef[] = [
    { field: 'code', headerName: 'Code', width: 120, sortable: true },
    { field: 'name', headerName: 'Name', width: 250, sortable: true },
    {
      field: 'type',
      headerName: 'Type',
      width: 120,
      sortable: true,
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
      sortable: true,
      renderCell: (params) => (
        <Chip
          label={params.value}
          variant="outlined"
          size="small"
        />
      ),
    },
    { field: 'level', headerName: 'Level', width: 80, align: 'center', sortable: true },
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
      sortable: true,
      renderCell: (params) => (
        <Chip
          label={params.value}
          color={getStatusColor(params.value)}
          size="small"
        />
      ),
    },
    {
      field: 'branch',
      headerName: 'Branch',
      width: 120,
      valueGetter: (params) => params?.row?.branch?.name || 'N/A',
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
      renderCell: (params: GridRenderCellParams) => {
        const account = params.row as GlAccount;
        return (
          <Box>
            <Tooltip title="View Details">
              <IconButton size="small" onClick={() => handleViewAccount(account)}>
                <ViewListIcon />
              </IconButton>
            </Tooltip>
            <Tooltip title="View Balance">
              <IconButton size="small" onClick={() => handleViewBalance(account)}>
                <BalanceIcon />
              </IconButton>
            </Tooltip>
            <Tooltip title="Edit Account">
              <IconButton size="small" onClick={() => handleEditAccount(account)}>
                <EditIcon />
              </IconButton>
            </Tooltip>
            <Tooltip title="Delete Account">
              <IconButton size="small" color="error" onClick={() => handleDeleteAccount(account)}>
                <DeleteIcon />
              </IconButton>
            </Tooltip>
          </Box>
        );
      },
    },
  ];

  const accounts = data?.data || [];
  const totalCount = typeof data?.meta?.total === 'number' ? data.meta.total : 0;
  const summary = summaryData as AccountSummary;

  return (
    <Box sx={{ p: 3 }}>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4" component="h1">
          General Ledger Accounts Management
        </Typography>
        <Box>
          <Tooltip title="Help & Tips">
            <IconButton onClick={() => setHelpDialogOpen(true)}>
              <HelpIcon />
            </IconButton>
          </Tooltip>
          <Tooltip title="Refresh Data">
            <IconButton onClick={() => queryClient.invalidateQueries({ queryKey: ['accounts'] })}>
              <RefreshIcon />
            </IconButton>
          </Tooltip>
        </Box>
      </Box>

      <Tabs value={activeTab} onChange={handleTabChange} sx={{ mb: 3 }}>
        <Tab icon={<DashboardIcon />} label="Dashboard" />
        <Tab icon={<ViewListIcon />} label="Accounts List" />
      </Tabs>

      {activeTab === 0 && (
        <Grid container spacing={3} mb={3}>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Typography color="textSecondary" gutterBottom>
                  Total Accounts
                </Typography>
                <Typography variant="h4">
                  {isLoadingSummary ? <CircularProgress size={20} /> : summary?.total_accounts || 0}
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
                <Typography variant="h4">
                  {isLoadingSummary ? <CircularProgress size={20} /> : summary?.active_accounts || 0}
                </Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Typography color="textSecondary" gutterBottom>
                  Postable Accounts
                </Typography>
                <Typography variant="h4">
                  {isLoadingSummary ? <CircularProgress size={20} /> : summary?.postable_accounts || 0}
                </Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={3}>
            <Card>
              <CardContent>
                <Typography color="textSecondary" gutterBottom>
                  Accounts by Type
                </Typography>
                {summary?.accounts_by_type && Object.entries(summary.accounts_by_type).map(([type, count]) => (
                  <Typography key={type} variant="body2">
                    {type}: {count}
                  </Typography>
                ))}
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      )}

      {activeTab === 1 && (
        <>
          {/* Advanced Filters */}
          <Paper sx={{ p: 2, mb: 3 }}>
            <Typography variant="h6" gutterBottom>
              Advanced Search & Filters
            </Typography>
            <Grid container spacing={2}>
              <Grid item xs={12} sm={6} md={3}>
                <TextField
                  fullWidth
                  size="small"
                  label="Search"
                  value={filters.search}
                  onChange={(e) => setFilters({ ...filters, search: e.target.value })}
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
                    <MenuItem value="ARCHIVED">Archived</MenuItem>
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

          {/* Data Table */}
          <Paper sx={{ height: 600, width: '100%' }}>
            <DataGrid
              rows={accounts}
              columns={columns}
              loading={isLoading}
              pageSize={pageSize}
              page={page}
              onPageChange={handlePageChange}
              onPageSizeChange={handlePageSizeChange}
              rowsPerPageOptions={[10, 25, 50, 100]}
              sortModel={sortModel}
              onSortModelChange={handleSortModelChange}
              components={{ Toolbar: GridToolbar }}
              disableSelectionOnClick
              getRowId={(row) => row.id}
              rowCount={totalCount}
              paginationMode="server"
              sortingMode="server"
            />
          </Paper>

          {/* Floating Action Button */}
          <Fab
            color="primary"
            aria-label="add"
            sx={{ position: 'fixed', bottom: 16, right: 16 }}
            onClick={handleCreateAccount}
          >
            <AddIcon />
          </Fab>
        </>
      )}

      {queryError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          Failed to load GL accounts. Please try again.
        </Alert>
      )}

      {/* Create Account Dialog */}
      <Dialog open={createDialogOpen} onClose={handleCloseDialogs} maxWidth="md" fullWidth>
        <DialogTitle>Create New GL Account</DialogTitle>
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
              <FormControl fullWidth>
                <InputLabel>Type</InputLabel>
                <Select
                  value={createForm.type}
                  label="Type"
                  onChange={(e) => setCreateForm({ ...createForm, type: e.target.value })}
                >
                  <MenuItem value="ASSET">Asset</MenuItem>
                  <MenuItem value="LIABILITY">Liability</MenuItem>
                  <MenuItem value="EQUITY">Equity</MenuItem>
                  <MenuItem value="REVENUE">Revenue</MenuItem>
                  <MenuItem value="EXPENSE">Expense</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6}>
              <FormControl fullWidth>
                <InputLabel>Normal Balance</InputLabel>
                <Select
                  value={createForm.normal_balance}
                  label="Normal Balance"
                  onChange={(e) => setCreateForm({ ...createForm, normal_balance: e.target.value })}
                >
                  <MenuItem value="DEBIT">Debit</MenuItem>
                  <MenuItem value="CREDIT">Credit</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6}>
              <FormControlLabel
                control={
                  <Switch
                    checked={createForm.is_postable}
                    onChange={(e) => setCreateForm({ ...createForm, is_postable: e.target.checked })}
                  />
                }
                label="Postable Account"
              />
            </Grid>
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleCloseDialogs}>Cancel</Button>
          <Button onClick={handleCreateSubmit} variant="contained" disabled={createMutation.isPending}>
            {createMutation.isPending ? <CircularProgress size={20} /> : <SaveIcon sx={{ mr: 1 }} />}
            Create
          </Button>
        </DialogActions>
      </Dialog>

      {/* Edit Account Dialog */}
      <Dialog open={editDialogOpen} onClose={handleCloseDialogs} maxWidth="md" fullWidth>
        <DialogTitle>Edit GL Account</DialogTitle>
        <DialogContent>
          <Grid container spacing={2} sx={{ mt: 1 }}>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                label="Account Name"
                value={editForm.name || ''}
                onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                required
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <FormControl fullWidth>
                <InputLabel>Type</InputLabel>
                <Select
                  value={editForm.type || ''}
                  label="Type"
                  onChange={(e) => setEditForm({ ...editForm, type: e.target.value })}
                >
                  <MenuItem value="ASSET">Asset</MenuItem>
                  <MenuItem value="LIABILITY">Liability</MenuItem>
                  <MenuItem value="EQUITY">Equity</MenuItem>
                  <MenuItem value="REVENUE">Revenue</MenuItem>
                  <MenuItem value="EXPENSE">Expense</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6}>
              <FormControl fullWidth>
                <InputLabel>Normal Balance</InputLabel>
                <Select
                  value={editForm.normal_balance || ''}
                  label="Normal Balance"
                  onChange={(e) => setEditForm({ ...editForm, normal_balance: e.target.value })}
                >
                  <MenuItem value="DEBIT">Debit</MenuItem>
                  <MenuItem value="CREDIT">Credit</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6}>
              <FormControl fullWidth>
                <InputLabel>Status</InputLabel>
                <Select
                  value={editForm.status || ''}
                  label="Status"
                  onChange={(e) => setEditForm({ ...editForm, status: e.target.value })}
                >
                  <MenuItem value="ACTIVE">Active</MenuItem>
                  <MenuItem value="ARCHIVED">Archived</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12}>
              <FormControlLabel
                control={
                  <Switch
                    checked={editForm.is_postable || false}
                    onChange={(e) => setEditForm({ ...editForm, is_postable: e.target.checked })}
                  />
                }
                label="Postable Account"
              />
            </Grid>
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleCloseDialogs}>Cancel</Button>
          <Button onClick={handleEditSubmit} variant="contained" disabled={updateMutation.isPending}>
            {updateMutation.isPending ? <CircularProgress size={20} /> : <SaveIcon sx={{ mr: 1 }} />}
            Update
          </Button>
        </DialogActions>
      </Dialog>

      {/* View Account Dialog */}
      <Dialog open={viewDialogOpen} onClose={handleCloseDialogs} maxWidth="md" fullWidth>
        <DialogTitle>Account Details</DialogTitle>
        <DialogContent>
          {selectedAccount && (
            <Grid container spacing={2} sx={{ mt: 1 }}>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Code" value={selectedAccount.code} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Name" value={selectedAccount.name} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Type" value={selectedAccount.type} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Normal Balance" value={selectedAccount.normal_balance} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Level" value={selectedAccount.level} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Status" value={selectedAccount.status} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Postable" value={selectedAccount.is_postable ? 'Yes' : 'No'} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Branch" value={selectedAccount.branch?.name || 'N/A'} InputProps={{ readOnly: true }} />
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
          <Button onClick={handleCloseDialogs}>Close</Button>
        </DialogActions>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog open={deleteDialogOpen} onClose={handleCloseDialogs}>
        <DialogTitle>Confirm Delete</DialogTitle>
        <DialogContent>
          <Typography>
            Are you sure you want to delete the account "{selectedAccount?.name}"?
            This action cannot be undone.
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleCloseDialogs}>Cancel</Button>
          <Button onClick={handleDeleteConfirm} color="error" variant="contained" disabled={deleteMutation.isPending}>
            {deleteMutation.isPending ? <CircularProgress size={20} /> : 'Delete'}
          </Button>
        </DialogActions>
      </Dialog>

      {/* Account Balance Dialog */}
      <Dialog open={balanceDialogOpen} onClose={handleCloseBalanceDialog} maxWidth="md" fullWidth>
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
                <TextField fullWidth label="Account Code" value={balanceData.account_code} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Account Name" value={balanceData.account_name} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Current Balance" value={Number(balanceData.balance || 0).toFixed(2)} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="As of Date" value={new Date(balanceData.as_of_date).toLocaleDateString()} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Total Debit" value={Number(balanceData.debit_total || 0).toFixed(2)} InputProps={{ readOnly: true }} />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField fullWidth label="Total Credit" value={Number(balanceData.credit_total || 0).toFixed(2)} InputProps={{ readOnly: true }} />
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

      {/* Help Dialog */}
      <Dialog open={helpDialogOpen} onClose={handleCloseDialogs} maxWidth="md" fullWidth>
        <DialogTitle>GL Accounts Management Help</DialogTitle>
        <DialogContent>
          <Accordion>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
              <Typography>Getting Started</Typography>
            </AccordionSummary>
            <AccordionDetails>
              <List>
                <ListItem>
                  <ListItemText primary="Dashboard: View summary metrics and account statistics" />
                </ListItem>
                <ListItem>
                  <ListItemText primary="Accounts List: Manage individual GL accounts with full CRUD operations" />
                </ListItem>
              </List>
            </AccordionDetails>
          </Accordion>
          <Accordion>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
              <Typography>CRUD Operations</Typography>
            </AccordionSummary>
            <AccordionDetails>
              <List>
                <ListItem>
                  <ListItemText primary="Create: Click the + button to add new accounts" />
                </ListItem>
                <ListItem>
                  <ListItemText primary="Read: Click the view icon to see account details" />
                </ListItem>
                <ListItem>
                  <ListItemText primary="Update: Click the edit icon to modify accounts" />
                </ListItem>
                <ListItem>
                  <ListItemText primary="Delete: Click the delete icon (with confirmation)" />
                </ListItem>
              </List>
            </AccordionDetails>
          </Accordion>
          <Accordion>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
              <Typography>Search & Filtering</Typography>
            </AccordionSummary>
            <AccordionDetails>
              <List>
                <ListItem>
                  <ListItemText primary="Use the search bar for account name/code lookup" />
                </ListItem>
                <ListItem>
                  <ListItemText primary="Filter by type, status, and postable status" />
                </ListItem>
                <ListItem>
                  <ListItemText primary="Real-time results as you type" />
                </ListItem>
              </List>
            </AccordionDetails>
          </Accordion>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleCloseDialogs}>Close</Button>
        </DialogActions>
      </Dialog>

      {/* Snackbar for notifications */}
      <Snackbar
        open={snackbar.open}
        autoHideDuration={6000}
        onClose={() => setSnackbar({ ...snackbar, open: false })}
      >
        <MuiAlert
          onClose={() => setSnackbar({ ...snackbar, open: false })}
          severity={snackbar.severity}
          sx={{ width: '100%' }}
        >
          {snackbar.message}
        </MuiAlert>
      </Snackbar>
    </Box>
  );
};

export default GLAccounts;