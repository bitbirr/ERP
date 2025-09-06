import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import {
  Box,
  TextField,
  Button,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Alert,
  Grid,
  Typography,
  IconButton,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Autocomplete,
} from '@mui/material';
import { Add as AddIcon, Delete as DeleteIcon } from '@mui/icons-material';
import axios from 'axios';
import { orderService, CreateOrderData } from '../../services/orderService';
import { Product, productService } from '../../services/productService';

interface Customer {
  id: string;
  name: string;
  phone: string;
  email?: string;
}

interface Branch {
  id: string;
  name: string;
  code: string;
}

interface OrderLine {
  product_id: string;
  uom: string;
  qty: number;
  price: number;
  discount?: number;
  tax_rate?: number;
  notes?: string;
  product?: Product;
}

const OrderForm: React.FC = () => {
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [products, setProducts] = useState<Product[]>([]);

  const [formData, setFormData] = useState({
    branch_id: '',
    customer_id: '',
    currency: 'ETB',
    notes: '',
  });

  const [orderLines, setOrderLines] = useState<OrderLine[]>([
    {
      product_id: '',
      uom: '',
      qty: 1,
      price: 0,
      discount: 0,
      tax_rate: 0,
    }
  ]);

  // Fetch customers
  const { data: customersData } = useQuery({
    queryKey: ['customers'],
    queryFn: () => axios.get('/api/customers?per_page=100'),
  });

  // Fetch branches
  const { data: branchesData } = useQuery({
    queryKey: ['branches'],
    queryFn: () => axios.get('/api/branches?per_page=100'),
  });

  // Fetch products
  const { data: productsData } = useQuery({
    queryKey: ['products'],
    queryFn: () => productService.getProducts({}, 1, 100),
  });

  useEffect(() => {
    if (customersData?.data?.data) {
      setCustomers(customersData.data.data);
    }
  }, [customersData]);

  useEffect(() => {
    if (branchesData?.data?.data) {
      setBranches(branchesData.data.data);
    }
  }, [branchesData]);

  useEffect(() => {
    if (productsData?.data) {
      setProducts(productsData.data);
    }
  }, [productsData]);

  // Create order mutation
  const createMutation = useMutation({
    mutationFn: (orderData: CreateOrderData) => orderService.createOrder(orderData),
    onSuccess: () => {
      navigate('/sales/orders');
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to create order');
    },
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!formData.branch_id) {
      setError('Branch is required');
      return;
    }

    if (orderLines.length === 0 || orderLines.some(line => !line.product_id || !line.qty)) {
      setError('At least one order line with product and quantity is required');
      return;
    }

    setError(null);

    const orderData: CreateOrderData = {
      branch_id: formData.branch_id,
      customer_id: formData.customer_id || undefined,
      currency: formData.currency,
      notes: formData.notes || undefined,
      line_items: orderLines.map(line => ({
        product_id: line.product_id,
        uom: line.uom,
        qty: line.qty,
        price: line.price,
        discount: line.discount,
        tax_rate: line.tax_rate,
        notes: line.notes,
      })),
    };

    createMutation.mutate(orderData);
  };

  const handleAddLine = () => {
    setOrderLines([...orderLines, {
      product_id: '',
      uom: '',
      qty: 1,
      price: 0,
      discount: 0,
      tax_rate: 0,
    }]);
  };

  const handleRemoveLine = (index: number) => {
    setOrderLines(orderLines.filter((_, i) => i !== index));
  };

  const handleLineChange = (index: number, field: keyof OrderLine, value: any) => {
    const updatedLines = [...orderLines];
    updatedLines[index] = { ...updatedLines[index], [field]: value };

    // Auto-fill UOM and price when product is selected
    if (field === 'product_id' && value) {
      const product = products.find(p => p.id === value);
      if (product) {
        updatedLines[index].uom = product.uom;
        updatedLines[index].price = product.price || 0;
        updatedLines[index].product = product;
      }
    }

    setOrderLines(updatedLines);
  };

  const calculateLineTotal = (line: OrderLine) => {
    const subtotal = line.qty * line.price;
    const discount = (subtotal * (line.discount || 0)) / 100;
    const taxable = subtotal - discount;
    const tax = (taxable * (line.tax_rate || 0)) / 100;
    return taxable + tax;
  };

  const calculateGrandTotal = () => {
    return orderLines.reduce((total, line) => total + calculateLineTotal(line), 0);
  };

  return (
    <Box component="form" onSubmit={handleSubmit} sx={{ mt: 1 }}>
      <Typography variant="h4" gutterBottom>
        Create New Order
      </Typography>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      <Grid container spacing={3}>
        {/* Branch Selection */}
        <Grid item xs={12} md={6}>
          <FormControl fullWidth required>
            <InputLabel>Branch</InputLabel>
            <Select
              value={formData.branch_id}
              label="Branch"
              onChange={(e) => setFormData(prev => ({ ...prev, branch_id: e.target.value }))}
            >
              {branches.map((branch) => (
                <MenuItem key={branch.id} value={branch.id}>
                  {branch.name} ({branch.code})
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </Grid>

        {/* Customer Selection */}
        <Grid item xs={12} md={6}>
          <Autocomplete
            options={customers}
            getOptionLabel={(option) => `${option.name} - ${option.phone}`}
            value={customers.find(c => c.id === formData.customer_id) || null}
            onChange={(_, newValue) => setFormData(prev => ({
              ...prev,
              customer_id: newValue?.id || ''
            }))}
            renderInput={(params) => (
              <TextField
                {...params}
                label="Customer (Optional)"
                placeholder="Search for customer..."
              />
            )}
          />
        </Grid>

        {/* Currency */}
        <Grid item xs={12} md={6}>
          <FormControl fullWidth required>
            <InputLabel>Currency</InputLabel>
            <Select
              value={formData.currency}
              label="Currency"
              onChange={(e) => setFormData(prev => ({ ...prev, currency: e.target.value }))}
            >
              <MenuItem value="ETB">ETB (Ethiopian Birr)</MenuItem>
              <MenuItem value="USD">USD (US Dollar)</MenuItem>
              <MenuItem value="EUR">EUR (Euro)</MenuItem>
            </Select>
          </FormControl>
        </Grid>

        {/* Notes */}
        <Grid item xs={12}>
          <TextField
            fullWidth
            multiline
            rows={3}
            label="Notes"
            value={formData.notes}
            onChange={(e) => setFormData(prev => ({ ...prev, notes: e.target.value }))}
          />
        </Grid>
      </Grid>

      {/* Order Lines */}
      <Box sx={{ mt: 4 }}>
        <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
          <Typography variant="h6">Order Lines</Typography>
          <Button
            variant="outlined"
            startIcon={<AddIcon />}
            onClick={handleAddLine}
          >
            Add Line
          </Button>
        </Box>

        <TableContainer component={Paper}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>Product</TableCell>
                <TableCell>UOM</TableCell>
                <TableCell>Quantity</TableCell>
                <TableCell>Price</TableCell>
                <TableCell>Discount (%)</TableCell>
                <TableCell>Tax Rate (%)</TableCell>
                <TableCell>Total</TableCell>
                <TableCell>Actions</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {orderLines.map((line, index) => (
                <TableRow key={index}>
                  <TableCell>
                    <FormControl fullWidth required>
                      <InputLabel>Product</InputLabel>
                      <Select
                        value={line.product_id}
                        label="Product"
                        onChange={(e) => handleLineChange(index, 'product_id', e.target.value)}
                      >
                        {products.map((product) => (
                          <MenuItem key={product.id} value={product.id}>
                            {product.name} ({product.code})
                          </MenuItem>
                        ))}
                      </Select>
                    </FormControl>
                  </TableCell>
                  <TableCell>
                    <TextField
                      fullWidth
                      value={line.uom}
                      onChange={(e) => handleLineChange(index, 'uom', e.target.value)}
                      placeholder="UOM"
                    />
                  </TableCell>
                  <TableCell>
                    <TextField
                      fullWidth
                      type="number"
                      value={line.qty}
                      onChange={(e) => handleLineChange(index, 'qty', parseFloat(e.target.value) || 0)}
                      inputProps={{ min: 0, step: 0.01 }}
                    />
                  </TableCell>
                  <TableCell>
                    <TextField
                      fullWidth
                      type="number"
                      value={line.price}
                      onChange={(e) => handleLineChange(index, 'price', parseFloat(e.target.value) || 0)}
                      inputProps={{ min: 0, step: 0.01 }}
                    />
                  </TableCell>
                  <TableCell>
                    <TextField
                      fullWidth
                      type="number"
                      value={line.discount || 0}
                      onChange={(e) => handleLineChange(index, 'discount', parseFloat(e.target.value) || 0)}
                      inputProps={{ min: 0, max: 100, step: 0.01 }}
                    />
                  </TableCell>
                  <TableCell>
                    <TextField
                      fullWidth
                      type="number"
                      value={line.tax_rate || 0}
                      onChange={(e) => handleLineChange(index, 'tax_rate', parseFloat(e.target.value) || 0)}
                      inputProps={{ min: 0, step: 0.01 }}
                    />
                  </TableCell>
                  <TableCell>
                    {calculateLineTotal(line).toFixed(2)} {formData.currency}
                  </TableCell>
                  <TableCell>
                    <IconButton
                      color="error"
                      onClick={() => handleRemoveLine(index)}
                      disabled={orderLines.length === 1}
                    >
                      <DeleteIcon />
                    </IconButton>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>

        <Box sx={{ mt: 2, p: 2, bgcolor: 'grey.100', borderRadius: 1 }}>
          <Typography variant="h6" align="right">
            Grand Total: {calculateGrandTotal().toFixed(2)} {formData.currency}
          </Typography>
        </Box>
      </Box>

      <Box sx={{ display: 'flex', gap: 2, justifyContent: 'flex-end', mt: 3 }}>
        <Button onClick={() => navigate('/sales/orders')}>
          Cancel
        </Button>
        <Button
          type="submit"
          variant="contained"
          disabled={createMutation.isPending}
        >
          {createMutation.isPending ? 'Creating...' : 'Create Order'}
        </Button>
      </Box>
    </Box>
  );
};

export default OrderForm;