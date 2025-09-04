import React, { useState } from 'react';
import {
  Box,
  Card,
  CardContent,
  Typography,
  TextField,
  Button,
  Alert,
  CircularProgress,
  Grid,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
} from '@mui/material';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { inventoryService, TransferData } from '../../services/inventoryService';

const StockTransfer: React.FC = () => {
  const [transferData, setTransferData] = useState<Partial<TransferData>>({});
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const queryClient = useQueryClient();

  const transferMutation = useMutation({
    mutationFn: inventoryService.transferStock,
    onSuccess: () => {
      setSuccess('Stock transferred successfully!');
      setError(null);
      setTransferData({});
      queryClient.invalidateQueries({ queryKey: ['inventory'] });
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to transfer stock');
      setSuccess(null);
    },
  });

  const handleInputChange = (field: keyof TransferData, value: any) => {
    setTransferData(prev => ({ ...prev, [field]: value }));
  };

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();

    if (!transferData.product_id || !transferData.from_branch_id ||
        !transferData.to_branch_id || !transferData.qty) {
      setError('Please fill in all required fields');
      return;
    }

    if (transferData.from_branch_id === transferData.to_branch_id) {
      setError('Source and destination branches cannot be the same');
      return;
    }

    if (transferData.qty <= 0) {
      setError('Quantity must be greater than 0');
      return;
    }

    setError(null);
    setSuccess(null);
    transferMutation.mutate(transferData as TransferData);
  };

  const handleReset = () => {
    setTransferData({});
    setError(null);
    setSuccess(null);
  };

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Stock Transfer Between Branches
      </Typography>

      <Grid container spacing={3}>
        <Grid item xs={12} md={8}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Transfer Details
              </Typography>

              {error && (
                <Alert severity="error" sx={{ mb: 2 }}>
                  {error}
                </Alert>
              )}

              {success && (
                <Alert severity="success" sx={{ mb: 2 }}>
                  {success}
                </Alert>
              )}

              <Box component="form" onSubmit={handleSubmit}>
                <Grid container spacing={3}>
                  <Grid item xs={12}>
                    <TextField
                      fullWidth
                      label="Product ID"
                      value={transferData.product_id || ''}
                      onChange={(e) => handleInputChange('product_id', e.target.value)}
                      required
                      disabled={transferMutation.isPending}
                    />
                  </Grid>

                  <Grid item xs={12} md={6}>
                    <TextField
                      fullWidth
                      label="From Branch ID"
                      value={transferData.from_branch_id || ''}
                      onChange={(e) => handleInputChange('from_branch_id', e.target.value)}
                      required
                      disabled={transferMutation.isPending}
                      helperText="Source branch"
                    />
                  </Grid>

                  <Grid item xs={12} md={6}>
                    <TextField
                      fullWidth
                      label="To Branch ID"
                      value={transferData.to_branch_id || ''}
                      onChange={(e) => handleInputChange('to_branch_id', e.target.value)}
                      required
                      disabled={transferMutation.isPending}
                      helperText="Destination branch"
                    />
                  </Grid>

                  <Grid item xs={12} md={6}>
                    <TextField
                      fullWidth
                      label="Quantity"
                      type="number"
                      value={transferData.qty || ''}
                      onChange={(e) => handleInputChange('qty', parseFloat(e.target.value) || 0)}
                      required
                      disabled={transferMutation.isPending}
                      inputProps={{ min: 0, step: 0.01 }}
                    />
                  </Grid>

                  <Grid item xs={12} md={6}>
                    <TextField
                      fullWidth
                      label="Reference"
                      value={transferData.ref || ''}
                      onChange={(e) => handleInputChange('ref', e.target.value)}
                      disabled={transferMutation.isPending}
                      helperText="Optional reference number"
                    />
                  </Grid>

                  <Grid item xs={12}>
                    <Box display="flex" gap={2}>
                      <Button
                        type="submit"
                        variant="contained"
                        disabled={transferMutation.isPending}
                        sx={{ minWidth: 120 }}
                      >
                        {transferMutation.isPending ? (
                          <CircularProgress size={20} />
                        ) : (
                          'Transfer Stock'
                        )}
                      </Button>

                      <Button
                        type="button"
                        variant="outlined"
                        onClick={handleReset}
                        disabled={transferMutation.isPending}
                      >
                        Reset
                      </Button>
                    </Box>
                  </Grid>
                </Grid>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} md={4}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Transfer Guidelines
              </Typography>
              <Box component="ul" sx={{ pl: 2, '& li': { mb: 1 } }}>
                <li>Ensure sufficient stock exists in the source branch</li>
                <li>Source and destination branches must be different</li>
                <li>Quantity must be greater than zero</li>
                <li>Use reference field for tracking purposes</li>
                <li>Transfer will create stock movements in both branches</li>
                <li>GL journal entries will be created automatically</li>
              </Box>
            </CardContent>
          </Card>

          <Card sx={{ mt: 2 }}>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Recent Transfers
              </Typography>
              <Typography variant="body2" color="textSecondary">
                Recent transfer history will be displayed here...
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
};

export default StockTransfer;