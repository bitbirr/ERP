import React, { useState, useMemo } from 'react';
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
  Grid,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TablePagination,
  useMediaQuery,
  useTheme,
} from '@mui/material';
import {
  Add as AddIcon,
  Search as SearchIcon,
  Edit as EditIcon,
  Delete as DeleteIcon,
  Visibility as ViewIcon,
  Sort as SortIcon
} from '@mui/icons-material';
import { DataGrid, GridColDef, GridSortModel, GridPaginationModel } from '@mui/x-data-grid';
import { userService, User, Role } from '../../services/userService';
import HelpIcon from '../../components/HelpIcon';
import QuickHowToTutorial from '../../components/QuickHowToTutorial';

const Users: React.FC = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('md'));

  const [error, setError] = useState<string | null>(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedRoles, setSelectedRoles] = useState<string[]>([]);
  const [emailVerifiedFilter, setEmailVerifiedFilter] = useState<string>('');
  const [sortModel, setSortModel] = useState<GridSortModel>([{ field: 'created_at', sort: 'desc' }]);
  const [paginationModel, setPaginationModel] = useState<GridPaginationModel>({ page: 0, pageSize: 10 });
  const [viewDialogOpen, setViewDialogOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);

  // Fetch users with server-side features
  const { data, isLoading, error: queryError } = useQuery({
    queryKey: ['users', paginationModel, sortModel, searchTerm, selectedRoles, emailVerifiedFilter],
    queryFn: () => userService.getUsers({
      page: paginationModel.page + 1,
      per_page: paginationModel.pageSize,
      search: searchTerm || undefined,
      sort_by: sortModel[0]?.field,
      sort_order: sortModel[0]?.sort || 'asc',
      roles: selectedRoles.length > 0 ? selectedRoles : undefined,
      email_verified: emailVerifiedFilter || undefined,
    }),
  });

  // Fetch roles for filter
  const { data: rolesData } = useQuery({
    queryKey: ['roles'],
    queryFn: () => userService.getRoles(),
  });

  // Delete user mutation
  const deleteMutation = useMutation({
    mutationFn: (userId: string) => userService.deleteUser(userId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to delete user');
    },
  });

  const handleCreateUser = () => {
    navigate('/users/new');
  };

  const handleEditUser = (userId: string) => {
    navigate(`/users/${userId}/edit`);
  };

  const handleViewUser = (user: User) => {
    setSelectedUser(user);
    setViewDialogOpen(true);
  };

  const handleDeleteUser = (userId: string) => {
    if (window.confirm('Are you sure you want to delete this user?')) {
      deleteMutation.mutate(userId);
    }
  };

  const handleSortModelChange = (newSortModel: GridSortModel) => {
    setSortModel(newSortModel);
  };

  const handlePaginationModelChange = (newPaginationModel: GridPaginationModel) => {
    setPaginationModel(newPaginationModel);
  };

  const handleRoleFilterChange = (event: any) => {
    const value = event.target.value;
    setSelectedRoles(typeof value === 'string' ? value.split(',') : value);
  };

  const columns: GridColDef[] = [
    { field: 'id', headerName: 'ID', width: 100, hideable: true },
    { field: 'name', headerName: 'Name', width: 200, sortable: true },
    { field: 'email', headerName: 'Email', width: 250, sortable: true },
    {
      field: 'email_verified_at',
      headerName: 'Email Verified',
      width: 150,
      sortable: false,
      renderCell: (params) => (
        <Chip
          label={params.value ? 'Verified' : 'Not Verified'}
          color={params.value ? 'success' : 'warning'}
          size="small"
        />
      ),
    },
    {
      field: 'roles',
      headerName: 'Roles',
      width: 200,
      sortable: false,
      renderCell: (params) => (
        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.5 }}>
          {params.row.roles?.map((role: any) => (
            <Chip key={role.id} label={role.name} size="small" variant="outlined" />
          )) || <Typography variant="body2" color="text.secondary">No roles</Typography>}
        </Box>
      ),
    },
    {
      field: 'created_at',
      headerName: 'Created At',
      width: 180,
      sortable: true,
      valueFormatter: (params) => new Date(params.value).toLocaleDateString(),
    },
    {
      field: 'updated_at',
      headerName: 'Updated At',
      width: 180,
      sortable: true,
      valueFormatter: (params) => new Date(params.value).toLocaleDateString(),
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 200,
      sortable: false,
      renderCell: (params) => (
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Button
            size="small"
            startIcon={<ViewIcon />}
            onClick={() => handleViewUser(params.row)}
            variant="outlined"
          >
            View
          </Button>
          <Button
            size="small"
            startIcon={<EditIcon />}
            onClick={() => handleEditUser(params.row.id)}
            variant="outlined"
            color="primary"
          >
            Edit
          </Button>
          <Button
            size="small"
            startIcon={<DeleteIcon />}
            onClick={() => handleDeleteUser(params.row.id)}
            variant="outlined"
            color="error"
          >
            Delete
          </Button>
        </Box>
      ),
    },
  ];

  if (isLoading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="400px">
        <CircularProgress />
      </Box>
    );
  }

  if (queryError) {
    return (
      <Alert severity="error">
        Failed to load users. Please try again.
      </Alert>
    );
  }

  const users = data?.data || [];
  const totalCount = data?.total || 0;

  const tutorialSteps = [
    {
      title: 'Creating Users',
      description: 'Click "Add User" to create a new account. Fill in name, email, and password. Assign appropriate roles for access control.',
      tips: ['Use strong passwords (12+ characters)', 'Assign minimal necessary roles', 'Verify email addresses for security']
    },
    {
      title: 'Viewing and Editing Users',
      description: 'Use the View button to see user details, or Edit to modify information. Changes are saved automatically.',
      tips: ['Check role assignments regularly', 'Update contact information as needed', 'Monitor email verification status']
    },
    {
      title: 'Searching and Filtering',
      description: 'Use the search bar to find users by name or email. Apply filters for roles or verification status.',
      tips: ['Use partial names for broader results', 'Combine filters for precise searches', 'Clear filters to see all users']
    },
    {
      title: 'Managing Large Datasets',
      description: 'Navigate through user lists using pagination. Adjust page size and sort columns for better organization.',
      tips: ['Sort by creation date to see recent users', 'Use larger page sizes for bulk operations', 'Jump to specific pages for quick access']
    },
    {
      title: 'Security Best Practices',
      description: 'Always assign appropriate roles, ensure email verification, and regularly review user access.',
      tips: ['Follow principle of least privilege', 'Remove inactive users', 'Audit role changes periodically']
    }
  ];

  return (
    <Box sx={{ p: isMobile ? 1 : 3 }}>
      <Box display="flex" flexDirection={isMobile ? 'column' : 'row'} justifyContent="space-between" alignItems="center" mb={3}>
        <Box display="flex" alignItems="center" sx={{ mb: isMobile ? 2 : 0 }}>
          <Typography variant="h4">Users</Typography>
          <HelpIcon title="Manage user accounts: Create, view, edit, and delete users. Assign roles for access control and ensure email verification for security." />
        </Box>
        <Box display="flex" alignItems="center">
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={handleCreateUser}
            fullWidth={isMobile}
          >
            Add User
          </Button>
          <HelpIcon title="Create a new user account. Fill in name, email, password, and assign a role for permissions." />
        </Box>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      {/* Search and Filter Controls */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid item xs={12} md={4}>
          <Box display="flex" alignItems="center">
            <TextField
              fullWidth
              label="Search users"
              variant="outlined"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              InputProps={{
                startAdornment: <SearchIcon sx={{ mr: 1, color: 'action.active' }} />,
              }}
              placeholder="Search by name or email..."
            />
            <HelpIcon title="Search users by name or email. Use partial keywords for flexible matching. Results update automatically." />
          </Box>
        </Grid>
        <Grid item xs={12} md={4}>
          <Box display="flex" alignItems="center">
            <FormControl fullWidth>
              <InputLabel>Filter by Roles</InputLabel>
              <Select
                multiple
                value={selectedRoles}
                label="Filter by Roles"
                onChange={handleRoleFilterChange}
                renderValue={(selected) => (
                  <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.5 }}>
                    {selected.map((value) => (
                      <Chip key={value} label={value} size="small" />
                    ))}
                  </Box>
                )}
              >
                {rolesData?.map((role: Role) => (
                  <MenuItem key={role.id} value={role.name}>
                    {role.name}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
            <HelpIcon title="Filter users by assigned roles. Select multiple roles to see users with any of those roles." />
          </Box>
        </Grid>
        <Grid item xs={12} md={4}>
          <Box display="flex" alignItems="center">
            <FormControl fullWidth>
              <InputLabel>Email Verified Status</InputLabel>
              <Select
                value={emailVerifiedFilter}
                label="Email Verified Status"
                onChange={(e) => setEmailVerifiedFilter(e.target.value)}
              >
                <MenuItem value="">
                  <em>All Users</em>
                </MenuItem>
                <MenuItem value="verified">Verified</MenuItem>
                <MenuItem value="not_verified">Not Verified</MenuItem>
              </Select>
            </FormControl>
            <HelpIcon title="Filter users by email verification status. Verified users have confirmed their email addresses." />
          </Box>
        </Grid>
      </Grid>

      <Box sx={{ height: 600, width: '100%', overflow: 'auto' }}>
        <DataGrid
          rows={users}
          columns={columns}
          rowCount={totalCount}
          loading={isLoading}
          paginationMode="server"
          sortingMode="server"
          sortModel={sortModel}
          onSortModelChange={handleSortModelChange}
          paginationModel={paginationModel}
          onPaginationModelChange={handlePaginationModelChange}
          pageSizeOptions={[10, 25, 50]}
          disableRowSelectionOnClick
          getRowId={(row) => row.id}
          sx={{
            '& .MuiDataGrid-cell': {
              py: 1,
            },
            '& .MuiDataGrid-columnHeader': {
              backgroundColor: theme.palette.grey[50],
            },
          }}
        />
      </Box>
      <Box sx={{ display: 'flex', alignItems: 'center', mt: 1 }}>
        <Typography variant="body2" color="text.secondary">
          Use pagination controls to navigate through user records. Adjust page size for better viewing.
        </Typography>
        <HelpIcon title="Navigate large datasets: Use page numbers to jump to specific pages, change page size (10, 25, 50) for optimal display, and sort columns for organized viewing." />
      </Box>

      {/* View User Details Dialog */}
      <Dialog open={viewDialogOpen} onClose={() => setViewDialogOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>User Details</DialogTitle>
        <DialogContent>
          {selectedUser && (
            <Box sx={{ pt: 2 }}>
              <Typography variant="h6" gutterBottom>Basic Information</Typography>
              <Grid container spacing={2} sx={{ mb: 3 }}>
                <Grid item xs={12} md={6}>
                  <TextField
                    fullWidth
                    label="Name"
                    value={selectedUser.name}
                    InputProps={{ readOnly: true }}
                  />
                </Grid>
                <Grid item xs={12} md={6}>
                  <TextField
                    fullWidth
                    label="Email"
                    value={selectedUser.email}
                    InputProps={{ readOnly: true }}
                  />
                </Grid>
                <Grid item xs={12} md={6}>
                  <TextField
                    fullWidth
                    label="Email Verified"
                    value={selectedUser.email_verified_at ? 'Yes' : 'No'}
                    InputProps={{ readOnly: true }}
                  />
                </Grid>
                <Grid item xs={12} md={6}>
                  <TextField
                    fullWidth
                    label="Created At"
                    value={new Date(selectedUser.created_at).toLocaleString()}
                    InputProps={{ readOnly: true }}
                  />
                </Grid>
              </Grid>

              <Typography variant="h6" gutterBottom>Roles</Typography>
              <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                {selectedUser.roles?.map((role) => (
                  <Chip key={role.id} label={role.name} variant="outlined" />
                )) || <Typography>No roles assigned</Typography>}
              </Box>
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setViewDialogOpen(false)}>Close</Button>
          {selectedUser && (
            <Button onClick={() => handleEditUser(selectedUser.id)} variant="contained">
              Edit User
            </Button>
          )}
        </DialogActions>
      </Dialog>
      <QuickHowToTutorial module="Users" steps={tutorialSteps} />
    </Box>
  );
};

export default Users;