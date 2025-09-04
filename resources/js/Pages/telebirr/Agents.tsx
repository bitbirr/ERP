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
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { DataGrid, GridColDef, GridToolbar } from '@mui/x-data-grid';
import { telebirrService, TelebirrAgent } from '../../services/telebirrService';

const Agents: React.FC = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState({
    status: '',
    search: '',
  });

  // Fetch agents
  const { data, isLoading, error: queryError } = useQuery({
    queryKey: ['telebirr-agents', filters],
    queryFn: () => telebirrService.getAgents({
      per_page: 50,
      status: filters.status || undefined,
      search: filters.search || undefined,
    }),
  });

  // Delete agent mutation
  const deleteMutation = useMutation({
    mutationFn: (agentId: string) => telebirrService.updateAgent(agentId, { status: 'Inactive' }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telebirr-agents'] });
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to deactivate agent');
    },
  });

  const handleCreateAgent = () => {
    navigate('/telebirr/agents/new');
  };

  const handleEditAgent = (agentId: string) => {
    navigate(`/telebirr/agents/${agentId}/edit`);
  };

  const handleDeactivateAgent = (agentId: string) => {
    if (window.confirm('Are you sure you want to deactivate this agent?')) {
      deleteMutation.mutate(agentId);
    }
  };

  const handleFilterChange = (field: string, value: string) => {
    setFilters(prev => ({ ...prev, [field]: value }));
  };

  const columns: GridColDef[] = [
    { field: 'id', headerName: 'ID', width: 100 },
    { field: 'name', headerName: 'Name', width: 200 },
    { field: 'short_code', headerName: 'Short Code', width: 120 },
    { field: 'phone', headerName: 'Phone', width: 150 },
    { field: 'location', headerName: 'Location', width: 150 },
    {
      field: 'status',
      headerName: 'Status',
      width: 100,
      renderCell: (params) => (
        <Chip
          label={params.value}
          color={params.value === 'Active' ? 'success' : 'default'}
          size="small"
        />
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
            onClick={() => handleEditAgent(params.row.id)}
            sx={{ mr: 1 }}
          >
            <EditIcon fontSize="small" />
          </Button>
          <Button
            size="small"
            color="error"
            onClick={() => handleDeactivateAgent(params.row.id)}
          >
            <DeleteIcon fontSize="small" />
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
        Failed to load agents. Please try again.
      </Alert>
    );
  }

  const agents = data?.data || [];

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">Telebirr Agents</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={handleCreateAgent}
        >
          Add Agent
        </Button>
      </Box>

      {/* Filters */}
      <Box display="flex" gap={2} mb={3}>
        <TextField
          label="Search"
          variant="outlined"
          size="small"
          value={filters.search}
          onChange={(e) => handleFilterChange('search', e.target.value)}
          placeholder="Search by name, short code, or phone"
          sx={{ minWidth: 300 }}
        />
        <FormControl size="small" sx={{ minWidth: 120 }}>
          <InputLabel>Status</InputLabel>
          <Select
            value={filters.status}
            label="Status"
            onChange={(e) => handleFilterChange('status', e.target.value)}
          >
            <MenuItem value="">All</MenuItem>
            <MenuItem value="Active">Active</MenuItem>
            <MenuItem value="Inactive">Inactive</MenuItem>
          </Select>
        </FormControl>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      <div style={{ height: 600, width: '100%' }}>
        <DataGrid
          rows={agents}
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

export default Agents;