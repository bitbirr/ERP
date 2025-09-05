import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Box,
  Grid,
  Card,
  CardContent,
  Typography,
  Button,
  Chip,
  Alert,
  CircularProgress,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  MenuItem,
  FormControl,
  InputLabel,
  Select,
  Tabs,
  Tab,
} from '@mui/material';
import {
  Add as AddIcon,
  Remove as RemoveIcon,
  SwapHoriz as TransferIcon,
  Assessment as ReportIcon,
  Inventory as InventoryIcon,
  Warning as WarningIcon,
  History as HistoryIcon,
} from '@mui/icons-material';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { DataGrid, GridColDef, GridToolbar } from '@mui/x-data-grid';
import { inventoryService, InventoryItem, StockOperationData } from '../../services/inventoryService';

const Inventory: React.FC = () => {
  const [selectedOperation, setSelectedOperation] = useState<string | null>(null);
  const [operationDialog, setOperationDialog] = useState(false);
  const [operationData, setOperationData] = useState<Partial<StockOperationData>>({});
  const [error, setError] = useState<string | null>(null);

  const navigate = useNavigate();
  const queryClient = useQueryClient();

  // Fetch inventory data
  const { data: inventoryData, isLoading, error: queryError } = useQuery({
    queryKey: ['inventory'],
    queryFn: () => inventoryService.getInventory({ per_page: 50 }),
  });

  // Fetch stock movements
  const { data: movementsData } = useQuery({
    queryKey: ['stock-movements'],
    queryFn: () => inventoryService.getStockMovements({ per_page: 10 }),
  });

  // Mutations for stock operations
  const receiveMutation = useMutation({
    mutationFn: inventoryService.receiveStock,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory'] });
      setOperationDialog(false);
      setOperationData({});
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to receive stock');
    },
  });

  const issueMutation = useMutation({
    mutationFn: inventoryService.issueStock,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory'] });
      setOperationDialog(false);
      setOperationData({});
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to issue stock');
    },
  });

  const adjustMutation = useMutation({
    mutationFn: (data: StockOperationData & { reason?: string }) => inventoryService.adjustStock(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['inventory'] });
      setOperationDialog(false);
      setOperationData({});
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to adjust stock');
    },
  });

  const handleOperation = (operation: string) => {
    setSelectedOperation(operation);
    setOperationDialog(true);
    setError(null);
  };

  const handleOperationSubmit = () => {
    if (!operationData.product_id || !operationData.branch_id || !operationData.qty) {
      setError('Please fill in all required fields');
      return;
    }

    const data = operationData as StockOperationData;

    switch (selectedOperation) {
      case 'receive':
        receiveMutation.mutate(data);
        break;
      case 'issue':
        issueMutation.mutate(data);
        break;
      case 'adjust':
        adjustMutation.mutate({ ...data, reason: operationData.reason });
        break;
    }
  };

  const columns: GridColDef[] = [
    { field: 'product_name', headerName: 'Product', width: 200, valueGetter: (params) => params?.row?.product?.name },
    { field: 'product_code', headerName: 'Code', width: 120, valueGetter: (params) => params?.row?.product?.code },
    { field: 'branch_name', headerName: 'Branch', width: 150, valueGetter: (params) => params?.row?.branch?.name },
    { field: 'on_hand', headerName: 'On Hand', width: 100, type: 'number' },
    { field: 'reserved', headerName: 'Reserved', width: 100, type: 'number' },
    {
      field: 'available',
      headerName: 'Available',
      width: 100,
      type: 'number',
      valueGetter: (params) => (params?.row?.on_hand || 0) - (params?.row?.reserved || 0),
    },
    {
      field: 'status',
      headerName: 'Status',
      width: 120,
      renderCell: (params) => {
        if (!params?.row) return null;
        const available = params.row.on_hand - params.row.reserved;
        const onHand = params.row.on_hand;

        if (onHand === 0) {
          return <Chip label="Out of Stock" color="error" size="small" />;
        } else if (available === 0) {
          return <Chip label="Fully Reserved" color="warning" size="small" />;
        } else if (available < 10) {
          return <Chip label="Low Stock" color="warning" size="small" icon={<WarningIcon />} />;
        } else {
          return <Chip label="In Stock" color="success" size="small" />;
        }
      },
    },
  ];

  const inventory = inventoryData?.data || [];
  const movements = movementsData?.data || [];

  // Calculate summary stats
  const totalItems = inventory.length;
  const lowStockItems = inventory.filter(item => (item.on_hand - item.reserved) < 10 && item.on_hand > 0).length;
  const outOfStockItems = inventory.filter(item => item.on_hand === 0).length;
  const totalValue = inventory.reduce((sum, item) => sum + (item.on_hand * (item.product?.cost || 0)), 0);

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
        Failed to load inventory data. Please try again.
      </Alert>
    );
  }

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">Inventory Management</Typography>
        <Box display="flex" gap={1} flexWrap="wrap">
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => handleOperation('receive')}
          >
            Receive Stock
          </Button>
          <Button
            variant="outlined"
            startIcon={<RemoveIcon />}
            onClick={() => handleOperation('issue')}
          >
            Issue Stock
          </Button>
          <Button
            variant="outlined"
            startIcon={<TransferIcon />}
            onClick={() => navigate('/inventory/transfer')}
          >
            Transfer
          </Button>
          <Button
            variant="outlined"
            startIcon={<HistoryIcon />}
            onClick={() => navigate('/inventory/movements')}
          >
            Movement History
          </Button>
          <Button
            variant="outlined"
            startIcon={<ReportIcon />}
            onClick={() => navigate('/reports')}
          >
            Reports
          </Button>
        </Box>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      {/* Summary Cards */}
      <Grid container spacing={3} mb={3}>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="textSecondary" gutterBottom>
                Total Items
              </Typography>
              <Typography variant="h4">{totalItems}</Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="textSecondary" gutterBottom>
                Low Stock Items
              </Typography>
              <Typography variant="h4" color="warning.main">{lowStockItems}</Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="textSecondary" gutterBottom>
                Out of Stock
              </Typography>
              <Typography variant="h4" color="error.main">{outOfStockItems}</Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="textSecondary" gutterBottom>
                Total Value
              </Typography>
              <Typography variant="h4">${totalValue.toFixed(2)}</Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Inventory Table */}
      <Card>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Current Stock Levels
          </Typography>
          <div style={{ height: 400, width: '100%' }}>
            <DataGrid
              rows={inventory}
              columns={columns}
              pageSize={10}
              rowsPerPageOptions={[10, 25, 50]}
              components={{ Toolbar: GridToolbar }}
              disableSelectionOnClick
              getRowId={(row) => `${row.product_id}-${row.branch_id}`}
            />
          </div>
        </CardContent>
      </Card>

      {/* Recent Movements */}
      <Card sx={{ mt: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Recent Stock Movements
          </Typography>
          <div style={{ height: 300, width: '100%' }}>
            <DataGrid
              rows={movements}
              columns={[
                { field: 'product_name', headerName: 'Product', width: 200, valueGetter: (params) => params?.row?.product?.name },
                { field: 'branch_name', headerName: 'Branch', width: 150, valueGetter: (params) => params?.row?.branch?.name },
                { field: 'type', headerName: 'Type', width: 120 },
                { field: 'qty', headerName: 'Quantity', width: 100, type: 'number' },
                { field: 'ref', headerName: 'Reference', width: 150 },
                { field: 'created_at', headerName: 'Date', width: 180, valueFormatter: (params) => params?.value ? new Date(params.value).toLocaleString() : '' },
              ]}
              pageSize={5}
              rowsPerPageOptions={[5]}
              disableSelectionOnClick
              getRowId={(row) => row.id}
            />
          </div>
        </CardContent>
      </Card>

      {/* Operation Dialog */}
      <Dialog open={operationDialog} onClose={() => setOperationDialog(false)} maxWidth="sm" fullWidth>
        <DialogTitle>
          {selectedOperation === 'receive' && 'Receive Stock'}
          {selectedOperation === 'issue' && 'Issue Stock'}
          {selectedOperation === 'adjust' && 'Adjust Stock'}
          {selectedOperation === 'transfer' && 'Transfer Stock'}
        </DialogTitle>
        <DialogContent>
          <Box component="form" sx={{ mt: 2 }}>
            <Grid container spacing={2}>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="Product ID"
                  value={operationData.product_id || ''}
                  onChange={(e) => setOperationData({ ...operationData, product_id: e.target.value })}
                />
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="Branch ID"
                  value={operationData.branch_id || ''}
                  onChange={(e) => setOperationData({ ...operationData, branch_id: e.target.value })}
                />
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="Quantity"
                  type="number"
                  value={operationData.qty || ''}
                  onChange={(e) => setOperationData({ ...operationData, qty: parseFloat(e.target.value) })}
                />
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="Reference"
                  value={operationData.ref || ''}
                  onChange={(e) => setOperationData({ ...operationData, ref: e.target.value })}
                />
              </Grid>
              {selectedOperation === 'adjust' && (
                <Grid item xs={12}>
                  <TextField
                    fullWidth
                    label="Reason"
                    value={operationData.reason || ''}
                    onChange={(e) => setOperationData({ ...operationData, reason: e.target.value })}
                  />
                </Grid>
              )}
            </Grid>
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOperationDialog(false)}>Cancel</Button>
          <Button
            onClick={handleOperationSubmit}
            variant="contained"
            disabled={receiveMutation.isPending || issueMutation.isPending || adjustMutation.isPending}
          >
            {(receiveMutation.isPending || issueMutation.isPending || adjustMutation.isPending) ? 'Processing...' : 'Submit'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default Inventory;