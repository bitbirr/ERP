import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Box,
  Button,
  Typography,
  Alert,
  CircularProgress,
} from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';
import { DataGrid, GridColDef, GridToolbar } from '@mui/x-data-grid';
import { userService, User } from '../../services/userService';

const Users: React.FC = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);

  // Fetch users
  const { data, isLoading, error: queryError } = useQuery({
    queryKey: ['users'],
    queryFn: () => userService.getUsers({ per_page: 50 }),
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

  const handleDeleteUser = (userId: string) => {
    if (window.confirm('Are you sure you want to delete this user?')) {
      deleteMutation.mutate(userId);
    }
  };

  const columns: GridColDef[] = [
    { field: 'id', headerName: 'ID', width: 100 },
    { field: 'name', headerName: 'Name', width: 200 },
    { field: 'email', headerName: 'Email', width: 250 },
    {
      field: 'email_verified_at',
      headerName: 'Verified',
      width: 120,
      renderCell: (params) => (
        <span>{params.value ? 'Yes' : 'No'}</span>
      ),
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
      width: 150,
      renderCell: (params) => (
        <Box>
          <Button
            size="small"
            onClick={() => handleEditUser(params.row.id)}
            sx={{ mr: 1 }}
          >
            Edit
          </Button>
          <Button
            size="small"
            color="error"
            onClick={() => handleDeleteUser(params.row.id)}
          >
            Delete
          </Button>
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
        Failed to load users. Please try again.
      </Alert>
    );
  }

  const users = data?.data || [];

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">Users</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={handleCreateUser}
        >
          Add User
        </Button>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      <div style={{ height: 600, width: '100%' }}>
        <DataGrid
          rows={users}
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

export default Users;