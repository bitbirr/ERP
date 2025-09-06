import React, { useState, useEffect, useMemo } from 'react';
import {
  Box,
  Card,
  CardContent,
  Typography,
  Grid,
  Button,
  TextField,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Chip,
  Alert,
  Snackbar,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  TablePagination,
  IconButton,
  Tooltip,
  Fab,
  Collapse,
  Accordion,
  AccordionSummary,
  AccordionDetails,
  Switch,
  FormControlLabel,
  InputAdornment,
} from '@mui/material';
import {
  Add as AddIcon,
  Edit as EditIcon,
  Delete as DeleteIcon,
  Search as SearchIcon,
  FilterList as FilterIcon,
  Business as BusinessIcon,
  LocationOn as LocationIcon,
  Phone as PhoneIcon,
  Person as PersonIcon,
  ExpandMore as ExpandMoreIcon,
  Help as HelpIcon,
  Refresh as RefreshIcon,
  ViewColumn as ViewColumnIcon,
} from '@mui/icons-material';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';
import { branchService, Branch, BranchStats, BranchFilters, PaginatedBranches } from '../../services/branchService';

const BranchManagement: React.FC = () => {
  // State management
  const [branches, setBranches] = useState<PaginatedBranches | null>(null);
  const [stats, setStats] = useState<BranchStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState<BranchFilters>({
    page: 0,
    per_page: 10,
    sort: 'name',
    direction: 'asc',
  });

  // Dialog states
  const [createDialogOpen, setCreateDialogOpen] = useState(false);
  const [editDialogOpen, setEditDialogOpen] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [selectedBranch, setSelectedBranch] = useState<Branch | null>(null);

  // Form states
  const [formData, setFormData] = useState<Partial<Branch>>({
    name: '',
    code: '',
    address: '',
    phone: '',
    manager: '',
    location: '',
    status: 'active',
  });
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  // UI states
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [snackbar, setSnackbar] = useState<{ open: boolean; message: string; severity: 'success' | 'error' }>({
    open: false,
    message: '',
    severity: 'success',
  });
  const [helpExpanded, setHelpExpanded] = useState(false);
  const [columnVisibility, setColumnVisibility] = useState({
    code: true,
    address: true,
    phone: true,
    manager: true,
    location: true,
    status: true,
    created_at: true,
    updated_at: true,
  });

  // Fetch data
  const fetchBranches = async () => {
    try {
      setLoading(true);
      const [branchesData, statsData] = await Promise.all([
        branchService.getBranches(filters),
        branchService.getStats(),
      ]);
      setBranches(branchesData);
      setStats(statsData);
    } catch (error) {
      console.error('Error fetching branches:', error);
      setError('Failed to load branches');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchBranches();
  }, [filters]);

  // Filtered branches based on search and status
  const filteredBranches = useMemo(() => {
    if (!branches?.data) return [];
    return branches.data.filter(branch => {
      const matchesSearch = !searchTerm ||
        branch.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        branch.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (branch.location && branch.location.toLowerCase().includes(searchTerm.toLowerCase()));

      const matchesStatus = !statusFilter || branch.status === statusFilter;

      return matchesSearch && matchesStatus;
    });
  }, [branches?.data, searchTerm, statusFilter]);

  // Handle form submission
  const handleCreate = async () => {
    try {
      await branchService.createBranch(formData as Omit<Branch, 'id' | 'created_at' | 'updated_at' | 'deleted_at'>);
      setCreateDialogOpen(false);
      resetForm();
      fetchBranches();
      setSnackbar({ open: true, message: 'Branch created successfully', severity: 'success' });
    } catch (error: any) {
      if (error.response?.data?.errors) {
        setFormErrors(error.response.data.errors);
      } else {
        setSnackbar({ open: true, message: 'Failed to create branch', severity: 'error' });
      }
    }
  };

  const handleUpdate = async () => {
    if (!selectedBranch) return;
    try {
      await branchService.updateBranch(selectedBranch.id, formData);
      setEditDialogOpen(false);
      resetForm();
      fetchBranches();
      setSnackbar({ open: true, message: 'Branch updated successfully', severity: 'success' });
    } catch (error: any) {
      if (error.response?.data?.errors) {
        setFormErrors(error.response.data.errors);
      } else {
        setSnackbar({ open: true, message: 'Failed to update branch', severity: 'error' });
      }
    }
  };

  const handleDelete = async () => {
    if (!selectedBranch) return;
    try {
      await branchService.deleteBranch(selectedBranch.id);
      setDeleteDialogOpen(false);
      fetchBranches();
      setSnackbar({ open: true, message: 'Branch deleted successfully', severity: 'success' });
    } catch (error) {
      setSnackbar({ open: true, message: 'Failed to delete branch', severity: 'error' });
    }
  };

  // Helper functions
  const resetForm = () => {
    setFormData({
      name: '',
      code: '',
      address: '',
      phone: '',
      manager: '',
      location: '',
      status: 'active',
    });
    setFormErrors({});
  };

  const openEditDialog = (branch: Branch) => {
    setSelectedBranch(branch);
    setFormData(branch);
    setEditDialogOpen(true);
  };

  const openDeleteDialog = (branch: Branch) => {
    setSelectedBranch(branch);
    setDeleteDialogOpen(true);
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

  const handlePageChange = (event: unknown, newPage: number) => {
    setFilters(prev => ({ ...prev, page: newPage }));
  };

  const handleRowsPerPageChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    setFilters(prev => ({
      ...prev,
      per_page: parseInt(event.target.value, 10),
      page: 0
    }));
  };

  const handleSort = (column: string) => {
    setFilters(prev => ({
      ...prev,
      sort: column,
      direction: prev.sort === column && prev.direction === 'asc' ? 'desc' : 'asc',
    }));
  };

  if (loading && !branches) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="400px">
        <Typography variant="h6">Loading branches...</Typography>
      </Box>
    );
  }

  return (
    <LocalizationProvider dateAdapter={AdapterDateFns}>
      <Box>
        <Typography variant="h4" gutterBottom>
          Branch Management
        </Typography>

        {/* Summary Cards */}
        <Grid container spacing={3} mb={4}>
          <Grid item xs={12} sm={6} md={4}>
            <Card>
              <CardContent>
                <Box display="flex" alignItems="center" justifyContent="space-between">
                  <Box>
                    <Typography color="textSecondary" gutterBottom>
                      Total Branches
                    </Typography>
                    <Typography variant="h5" component="div">
                      {stats?.total_branches || 0}
                    </Typography>
                  </Box>
                  <BusinessIcon sx={{ fontSize: 40, color: 'primary.main' }} />
                </Box>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={4}>
            <Card>
              <CardContent>
                <Box display="flex" alignItems="center" justifyContent="space-between">
                  <Box>
                    <Typography color="textSecondary" gutterBottom>
                      Active Branches
                    </Typography>
                    <Typography variant="h5" component="div">
                      {stats?.active_branches || 0}
                    </Typography>
                  </Box>
                  <BusinessIcon sx={{ fontSize: 40, color: 'success.main' }} />
                </Box>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} sm={6} md={4}>
            <Card>
              <CardContent>
                <Box display="flex" alignItems="center" justifyContent="space-between">
                  <Box>
                    <Typography color="textSecondary" gutterBottom>
                      Inactive Branches
                    </Typography>
                    <Typography variant="h5" component="div">
                      {stats?.inactive_branches || 0}
                    </Typography>
                  </Box>
                  <BusinessIcon sx={{ fontSize: 40, color: 'error.main' }} />
                </Box>
              </CardContent>
            </Card>
          </Grid>
        </Grid>

        {/* Search and Filters */}
        <Card mb={3}>
          <CardContent>
            <Grid container spacing={2} alignItems="center">
              <Grid item xs={12} md={4}>
                <TextField
                  fullWidth
                  placeholder="Search branches..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  InputProps={{
                    startAdornment: (
                      <InputAdornment position="start">
                        <SearchIcon />
                      </InputAdornment>
                    ),
                  }}
                />
              </Grid>
              <Grid item xs={12} md={3}>
                <FormControl fullWidth>
                  <InputLabel>Status Filter</InputLabel>
                  <Select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    label="Status Filter"
                  >
                    <MenuItem value="">All Status</MenuItem>
                    <MenuItem value="active">Active</MenuItem>
                    <MenuItem value="inactive">Inactive</MenuItem>
                  </Select>
                </FormControl>
              </Grid>
              <Grid item xs={12} md={5}>
                <Box display="flex" gap={1}>
                  <Button
                    variant="outlined"
                    startIcon={<RefreshIcon />}
                    onClick={fetchBranches}
                  >
                    Refresh
                  </Button>
                  <Button
                    variant="outlined"
                    startIcon={<ViewColumnIcon />}
                    onClick={() => {/* Column visibility dialog */}}
                  >
                    Columns
                  </Button>
                  <Button
                    variant="contained"
                    startIcon={<AddIcon />}
                    onClick={() => setCreateDialogOpen(true)}
                  >
                    Add Branch
                  </Button>
                </Box>
              </Grid>
            </Grid>
          </CardContent>
        </Card>

        {/* Data Table */}
        <Card>
          <TableContainer component={Paper}>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell
                    onClick={() => handleSort('name')}
                    style={{ cursor: 'pointer' }}
                  >
                    Branch Name
                    {filters.sort === 'name' && (filters.direction === 'asc' ? ' ↑' : ' ↓')}
                  </TableCell>
                  {columnVisibility.code && (
                    <TableCell
                      onClick={() => handleSort('code')}
                      style={{ cursor: 'pointer' }}
                    >
                      Code
                      {filters.sort === 'code' && (filters.direction === 'asc' ? ' ↑' : ' ↓')}
                    </TableCell>
                  )}
                  {columnVisibility.location && (
                    <TableCell>Location</TableCell>
                  )}
                  {columnVisibility.status && (
                    <TableCell>Status</TableCell>
                  )}
                  {columnVisibility.created_at && (
                    <TableCell
                      onClick={() => handleSort('created_at')}
                      style={{ cursor: 'pointer' }}
                    >
                      Created
                      {filters.sort === 'created_at' && (filters.direction === 'asc' ? ' ↑' : ' ↓')}
                    </TableCell>
                  )}
                  <TableCell align="right">Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {filteredBranches.map((branch) => (
                  <TableRow key={branch.id}>
                    <TableCell>{branch.name}</TableCell>
                    {columnVisibility.code && <TableCell>{branch.code}</TableCell>}
                    {columnVisibility.location && <TableCell>{branch.location || '-'}</TableCell>}
                    {columnVisibility.status && (
                      <TableCell>
                        <Chip
                          label={branch.status}
                          color={branch.status === 'active' ? 'success' : 'error'}
                          size="small"
                        />
                      </TableCell>
                    )}
                    {columnVisibility.created_at && (
                      <TableCell>{formatDate(branch.created_at)}</TableCell>
                    )}
                    <TableCell align="right">
                      <Tooltip title="Edit">
                        <IconButton onClick={() => openEditDialog(branch)}>
                          <EditIcon />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title="Delete">
                        <IconButton onClick={() => openDeleteDialog(branch)}>
                          <DeleteIcon />
                        </IconButton>
                      </Tooltip>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
          <TablePagination
            component="div"
            count={branches?.total || 0}
            page={branches?.current_page ? branches.current_page - 1 : 0}
            onPageChange={handlePageChange}
            rowsPerPage={branches?.per_page || 10}
            onRowsPerPageChange={handleRowsPerPageChange}
          />
        </Card>

        {/* Help Section */}
        <Accordion expanded={helpExpanded} onChange={() => setHelpExpanded(!helpExpanded)} sx={{ mt: 3 }}>
          <AccordionSummary expandIcon={<ExpandMoreIcon />}>
            <Typography variant="h6" sx={{ display: 'flex', alignItems: 'center' }}>
              <HelpIcon sx={{ mr: 1 }} />
              Help & Tips
            </Typography>
          </AccordionSummary>
          <AccordionDetails>
            <Typography variant="body2" paragraph>
              <strong>Creating a Branch:</strong> Click "Add Branch" and fill in the required fields (Name and Code).
              The branch will be created with Active status by default.
            </Typography>
            <Typography variant="body2" paragraph>
              <strong>Editing:</strong> Click the edit icon next to any branch to modify its details.
            </Typography>
            <Typography variant="body2" paragraph>
              <strong>Deleting:</strong> Use the delete icon. If the branch has associated accounts, it will be soft-deleted.
            </Typography>
            <Typography variant="body2" paragraph>
              <strong>Search:</strong> Use the search box to find branches by name, code, or location.
            </Typography>
            <Typography variant="body2" paragraph>
              <strong>Filtering:</strong> Filter branches by status using the dropdown.
            </Typography>
          </AccordionDetails>
        </Accordion>

        {/* Create Dialog */}
        <Dialog open={createDialogOpen} onClose={() => setCreateDialogOpen(false)} maxWidth="md" fullWidth>
          <DialogTitle>Add New Branch</DialogTitle>
          <DialogContent>
            <Grid container spacing={2} sx={{ mt: 1 }}>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Branch Name"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  error={!!formErrors.name}
                  helperText={formErrors.name}
                  required
                />
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Branch Code"
                  value={formData.code}
                  onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                  error={!!formErrors.code}
                  helperText={formErrors.code}
                  required
                />
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Location"
                  value={formData.location}
                  onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                  error={!!formErrors.location}
                  helperText={formErrors.location}
                />
              </Grid>
              <Grid item xs={12} md={6}>
                <FormControl fullWidth>
                  <InputLabel>Status</InputLabel>
                  <Select
                    value={formData.status}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value as 'active' | 'inactive' })}
                    label="Status"
                  >
                    <MenuItem value="active">Active</MenuItem>
                    <MenuItem value="inactive">Inactive</MenuItem>
                  </Select>
                </FormControl>
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Address"
                  value={formData.address}
                  onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                  error={!!formErrors.address}
                  helperText={formErrors.address}
                />
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Phone"
                  value={formData.phone}
                  onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                  error={!!formErrors.phone}
                  helperText={formErrors.phone}
                />
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Manager"
                  value={formData.manager}
                  onChange={(e) => setFormData({ ...formData, manager: e.target.value })}
                  error={!!formErrors.manager}
                  helperText={formErrors.manager}
                />
              </Grid>
            </Grid>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setCreateDialogOpen(false)}>Cancel</Button>
            <Button onClick={handleCreate} variant="contained">Create</Button>
          </DialogActions>
        </Dialog>

        {/* Edit Dialog */}
        <Dialog open={editDialogOpen} onClose={() => setEditDialogOpen(false)} maxWidth="md" fullWidth>
          <DialogTitle>Edit Branch</DialogTitle>
          <DialogContent>
            <Grid container spacing={2} sx={{ mt: 1 }}>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Branch Name"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  error={!!formErrors.name}
                  helperText={formErrors.name}
                  required
                />
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Branch Code"
                  value={formData.code}
                  onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                  error={!!formErrors.code}
                  helperText={formErrors.code}
                  required
                />
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Location"
                  value={formData.location}
                  onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                  error={!!formErrors.location}
                  helperText={formErrors.location}
                />
              </Grid>
              <Grid item xs={12} md={6}>
                <FormControl fullWidth>
                  <InputLabel>Status</InputLabel>
                  <Select
                    value={formData.status}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value as 'active' | 'inactive' })}
                    label="Status"
                  >
                    <MenuItem value="active">Active</MenuItem>
                    <MenuItem value="inactive">Inactive</MenuItem>
                  </Select>
                </FormControl>
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Address"
                  value={formData.address}
                  onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                  error={!!formErrors.address}
                  helperText={formErrors.address}
                />
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Phone"
                  value={formData.phone}
                  onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                  error={!!formErrors.phone}
                  helperText={formErrors.phone}
                />
              </Grid>
              <Grid item xs={12} md={6}>
                <TextField
                  fullWidth
                  label="Manager"
                  value={formData.manager}
                  onChange={(e) => setFormData({ ...formData, manager: e.target.value })}
                  error={!!formErrors.manager}
                  helperText={formErrors.manager}
                />
              </Grid>
            </Grid>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setEditDialogOpen(false)}>Cancel</Button>
            <Button onClick={handleUpdate} variant="contained">Update</Button>
          </DialogActions>
        </Dialog>

        {/* Delete Confirmation Dialog */}
        <Dialog open={deleteDialogOpen} onClose={() => setDeleteDialogOpen(false)}>
          <DialogTitle>Confirm Delete</DialogTitle>
          <DialogContent>
            <Typography>
              Are you sure you want to delete the branch "{selectedBranch?.name}"?
              This action cannot be undone.
            </Typography>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDeleteDialogOpen(false)}>Cancel</Button>
            <Button onClick={handleDelete} color="error" variant="contained">Delete</Button>
          </DialogActions>
        </Dialog>

        {/* Snackbar for notifications */}
        <Snackbar
          open={snackbar.open}
          autoHideDuration={6000}
          onClose={() => setSnackbar({ ...snackbar, open: false })}
        >
          <Alert
            onClose={() => setSnackbar({ ...snackbar, open: false })}
            severity={snackbar.severity}
            sx={{ width: '100%' }}
          >
            {snackbar.message}
          </Alert>
        </Snackbar>
      </Box>
    </LocalizationProvider>
  );
};

export default BranchManagement;