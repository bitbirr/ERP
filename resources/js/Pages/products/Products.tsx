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
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  TextField,
  Grid,
} from '@mui/material';
import { Add as AddIcon, Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { DataGrid, GridColDef, GridToolbar } from '@mui/x-data-grid';
import { productService, Product, ProductCategory } from '../../services/productService';

const Products: React.FC = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState({
    category_id: '',
    type: '',
    is_active: '',
    q: '',
  });

  // Fetch products
  const { data: productsData, isLoading: productsLoading, error: productsError } = useQuery({
    queryKey: ['products', filters],
    queryFn: () => {
      const params: any = {};
      if (filters.category_id) params.category_id = filters.category_id;
      if (filters.type) params.type = filters.type;
      if (filters.is_active) params.is_active = filters.is_active === 'true';
      if (filters.q) params.q = filters.q;
      return productService.getProducts(params);
    },
  });

  // Fetch categories for filter dropdown
  const { data: categoriesData } = useQuery({
    queryKey: ['product-categories'],
    queryFn: () => productService.getCategories({ per_page: 100 }),
  });

  // Delete product mutation
  const deleteMutation = useMutation({
    mutationFn: (productId: string) => productService.deleteProduct(productId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] });
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to delete product');
    },
  });

  const handleCreateProduct = () => {
    navigate('/products/new');
  };

  const handleEditProduct = (productId: string) => {
    navigate(`/products/${productId}/edit`);
  };

  const handleDeleteProduct = (productId: string) => {
    if (window.confirm('Are you sure you want to delete this product?')) {
      deleteMutation.mutate(productId);
    }
  };

  const handleFilterChange = (field: string, value: string) => {
    setFilters(prev => ({ ...prev, [field]: value }));
  };

  const columns: GridColDef[] = [
    { field: 'code', headerName: 'Code', width: 120 },
    { field: 'name', headerName: 'Name', width: 200 },
    {
      field: 'category',
      headerName: 'Category',
      width: 150,
      valueGetter: (params) => params?.row?.category?.name || 'No Category',
    },
    {
      field: 'type',
      headerName: 'Type',
      width: 100,
      renderCell: (params) => (
        <Chip
          label={params.value}
          size="small"
          color={params.value === 'YIMULU' ? 'primary' : params.value === 'SERVICE' ? 'secondary' : 'default'}
        />
      ),
    },
    { field: 'uom', headerName: 'UOM', width: 80 },
    {
      field: 'price',
      headerName: 'Price',
      width: 100,
      valueFormatter: (params) => params?.value ? `$${params.value}` : '-',
    },
    {
      field: 'cost',
      headerName: 'Cost',
      width: 100,
      valueFormatter: (params) => params?.value ? `$${params.value}` : '-',
    },
    {
      field: 'is_active',
      headerName: 'Active',
      width: 80,
      renderCell: (params) => (
        <Chip
          label={params.value ? 'Yes' : 'No'}
          size="small"
          color={params.value ? 'success' : 'error'}
        />
      ),
    },
    {
      field: 'created_at',
      headerName: 'Created',
      width: 120,
      valueFormatter: (params) => params?.value ? new Date(params.value).toLocaleDateString() : '-',
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 150,
      renderCell: (params) => (
        <Box>
          <Button
            size="small"
            startIcon={<EditIcon />}
            onClick={() => handleEditProduct(params.row.id)}
            sx={{ mr: 1 }}
          >
            Edit
          </Button>
          <Button
            size="small"
            color="error"
            startIcon={<DeleteIcon />}
            onClick={() => handleDeleteProduct(params.row.id)}
          >
            Delete
          </Button>
        </Box>
      ),
    },
  ];

  if (productsLoading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="200px">
        <CircularProgress />
      </Box>
    );
  }

  if (productsError) {
    return (
      <Alert severity="error">
        Failed to load products. Please try again.
      </Alert>
    );
  }

  const products = productsData?.data || [];
  const categories = categoriesData?.data || [];

  return (
    <Box>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">Products</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={handleCreateProduct}
        >
          Add Product
        </Button>
      </Box>

      {/* Filters */}
      <Grid container spacing={2} mb={3}>
        <Grid item xs={12} sm={6} md={3}>
          <FormControl fullWidth size="small">
            <InputLabel>Category</InputLabel>
            <Select
              value={filters.category_id}
              label="Category"
              onChange={(e) => handleFilterChange('category_id', e.target.value)}
            >
              <MenuItem value="">All Categories</MenuItem>
              {categories.map((category: ProductCategory) => (
                <MenuItem key={category.id} value={category.id}>
                  {category.name}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <FormControl fullWidth size="small">
            <InputLabel>Type</InputLabel>
            <Select
              value={filters.type}
              label="Type"
              onChange={(e) => handleFilterChange('type', e.target.value)}
            >
              <MenuItem value="">All Types</MenuItem>
              <MenuItem value="YIMULU">YIMULU</MenuItem>
              <MenuItem value="SERVICE">SERVICE</MenuItem>
              <MenuItem value="OTHER">OTHER</MenuItem>
            </Select>
          </FormControl>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <FormControl fullWidth size="small">
            <InputLabel>Status</InputLabel>
            <Select
              value={filters.is_active}
              label="Status"
              onChange={(e) => handleFilterChange('is_active', e.target.value)}
            >
              <MenuItem value="">All</MenuItem>
              <MenuItem value="true">Active</MenuItem>
              <MenuItem value="false">Inactive</MenuItem>
            </Select>
          </FormControl>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <TextField
            fullWidth
            size="small"
            label="Search"
            value={filters.q}
            onChange={(e) => handleFilterChange('q', e.target.value)}
            placeholder="Search by name or code"
          />
        </Grid>
      </Grid>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      <div style={{ height: 600, width: '100%' }}>
        <DataGrid
          rows={products}
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

export default Products;