import React, { useState } from 'react';
import {
  Box,
  Card,
  CardContent,
  Typography,
  TextField,
  Button,
  Chip,
  Alert,
  CircularProgress,
  MenuItem,
} from '@mui/material';
import { DatePicker } from '@mui/x-date-pickers';
import { DataGrid, GridColDef, GridToolbar } from '@mui/x-data-grid';
import { useQuery } from '@tanstack/react-query';
import { inventoryService, StockMovementFilters } from '../../services/inventoryService';

const StockMovements: React.FC = () => {
  const [filters, setFilters] = useState<StockMovementFilters>({
    from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 30 days ago
    to: new Date().toISOString().split('T')[0],
  });

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['stock-movements', filters],
    queryFn: () => inventoryService.getStockMovementsReport(filters),
  });

  const handleFilterChange = (field: keyof StockMovementFilters, value: any) => {
    setFilters(prev => ({ ...prev, [field]: value }));
  };

  const handleSearch = () => {
    refetch();
  };

  const getMovementTypeColor = (type: string) => {
    switch (type.toUpperCase()) {
      case 'RECEIVE':
        return 'success';
      case 'ISSUE':
        return 'error';
      case 'RESERVE':
        return 'warning';
      case 'UNRESERVE':
        return 'info';
      case 'TRANSFER':
        return 'secondary';
      case 'ADJUST':
        return 'default';
      default:
        return 'default';
    }
  };

  const columns: GridColDef[] = [
    { field: 'product_name', headerName: 'Product', width: 200, valueGetter: (params) => params.row.product?.name },
    { field: 'product_code', headerName: 'Code', width: 120, valueGetter: (params) => params.row.product?.code },
    { field: 'branch_name', headerName: 'Branch', width: 150, valueGetter: (params) => params.row.branch?.name },
    {
      field: 'type',
      headerName: 'Movement Type',
      width: 140,
      renderCell: (params) => (
        <Chip
          label={params.value}
          color={getMovementTypeColor(params.value)}
          size="small"
        />
      ),
    },
    {
      field: 'qty',
      headerName: 'Quantity',
      width: 100,
      type: 'number',
      renderCell: (params) => (
        <Typography
          color={params.row.type === 'ISSUE' || params.row.type === 'RESERVE' ? 'error.main' : 'success.main'}
        >
          {params.row.type === 'ISSUE' || params.row.type === 'RESERVE' ? '-' : '+'}
          {Math.abs(params.value)}
        </Typography>
      ),
    },
    { field: 'ref', headerName: 'Reference', width: 150 },
    { field: 'created_by', headerName: 'User', width: 120 },
    {
      field: 'created_at',
      headerName: 'Date & Time',
      width: 180,
      valueFormatter: (params) => new Date(params.value).toLocaleString(),
    },
  ];

  const movements = data?.data || [];
  const totals = data?.totals || {};

  if (error) {
    return (
      <Alert severity="error">
        Failed to load stock movements. Please try again.
      </Alert>
    );
  }

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Stock Movement History
      </Typography>

      {/* Filters */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Filters
          </Typography>
          <Box display="flex" gap={2} alignItems="center" flexWrap="wrap">
            <TextField
              label="From Date"
              type="date"
              value={filters.from || ''}
              onChange={(e) => handleFilterChange('from', e.target.value)}
              InputLabelProps={{ shrink: true }}
              sx={{ minWidth: 150 }}
            />
            <TextField
              label="To Date"
              type="date"
              value={filters.to || ''}
              onChange={(e) => handleFilterChange('to', e.target.value)}
              InputLabelProps={{ shrink: true }}
              sx={{ minWidth: 150 }}
            />
            <TextField
              label="Movement Type"
              select
              value={filters.type || ''}
              onChange={(e) => handleFilterChange('type', e.target.value)}
              sx={{ minWidth: 150 }}
            >
              <MenuItem value="">All Types</MenuItem>
              <MenuItem value="RECEIVE">Receive</MenuItem>
              <MenuItem value="ISSUE">Issue</MenuItem>
              <MenuItem value="RESERVE">Reserve</MenuItem>
              <MenuItem value="UNRESERVE">Unreserve</MenuItem>
              <MenuItem value="TRANSFER">Transfer</MenuItem>
              <MenuItem value="ADJUST">Adjust</MenuItem>
            </TextField>
            <TextField
              label="Branch"
              value={filters.branch || ''}
              onChange={(e) => handleFilterChange('branch', e.target.value)}
              sx={{ minWidth: 150 }}
            />
            <Button variant="contained" onClick={handleSearch}>
              Search
            </Button>
          </Box>
        </CardContent>
      </Card>

      {/* Summary */}
      <Box display="flex" gap={2} mb={3} flexWrap="wrap">
        {Object.entries(totals).map(([type, data]: [string, any]) => (
          <Card key={type} sx={{ minWidth: 150 }}>
            <CardContent>
              <Typography variant="h6" color="textSecondary">
                {type.replace('_', ' ').toUpperCase()}
              </Typography>
              <Typography variant="h4">
                {data?.count || 0}
              </Typography>
              <Typography variant="body2" color="textSecondary">
                Total Qty: {data?.total_qty || 0}
              </Typography>
            </CardContent>
          </Card>
        ))}
      </Box>

      {/* Movements Table */}
      <Card>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Movement Details
          </Typography>
          {isLoading ? (
            <Box display="flex" justifyContent="center" alignItems="center" minHeight="400px">
              <CircularProgress />
            </Box>
          ) : (
            <div style={{ height: 600, width: '100%' }}>
              <DataGrid
                rows={movements}
                columns={columns}
                pageSize={15}
                rowsPerPageOptions={[15, 25, 50]}
                components={{ Toolbar: GridToolbar }}
                disableSelectionOnClick
                getRowId={(row) => row.id}
              />
            </div>
          )}
        </CardContent>
      </Card>
    </Box>
  );
};

export default StockMovements;