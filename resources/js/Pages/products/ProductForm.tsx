import React, { useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useForm, Controller } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Box,
  Button,
  TextField,
  Typography,
  Paper,
  Alert,
  CircularProgress,
  Grid,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  FormControlLabel,
  Switch,
} from '@mui/material';
import { productService, Product, ProductCategory } from '../../services/productService';

const schema = yup.object({
  category_id: yup.string().nullable(),
  code: yup.string().required('Code is required').max(50, 'Code must be at most 50 characters'),
  name: yup.string().required('Name is required').max(255, 'Name must be at most 255 characters'),
  type: yup.string().required('Type is required').oneOf(['YIMULU', 'SERVICE', 'OTHER']),
  uom: yup.string().required('UOM is required').max(10, 'UOM must be at most 10 characters'),
  price: yup.number().nullable().min(0, 'Price must be positive'),
  cost: yup.number().nullable().min(0, 'Cost must be positive'),
  discount_percent: yup.number().nullable().min(0, 'Discount must be positive').max(100, 'Discount cannot exceed 100%'),
  pricing_strategy: yup.string().nullable().oneOf(['FIXED', 'PERCENTAGE', 'MARGIN']),
  is_active: yup.boolean(),
});

interface ProductFormData {
  category_id?: string;
  code: string;
  name: string;
  type: 'YIMULU' | 'SERVICE' | 'OTHER';
  uom: string;
  price?: number;
  cost?: number;
  discount_percent?: number;
  pricing_strategy?: 'FIXED' | 'PERCENTAGE' | 'MARGIN';
  is_active: boolean;
}

const ProductForm: React.FC = () => {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const isEditing = Boolean(id);
  const queryClient = useQueryClient();

  const { control, handleSubmit, formState: { errors }, reset, watch } = useForm<ProductFormData>({
    resolver: yupResolver(schema),
    defaultValues: {
      category_id: '',
      code: '',
      name: '',
      type: 'OTHER',
      uom: 'PCS',
      price: undefined,
      cost: undefined,
      discount_percent: undefined,
      pricing_strategy: undefined,
      is_active: true,
    },
  });

  // Fetch product data if editing
  const { data: product, isLoading: isLoadingProduct } = useQuery({
    queryKey: ['product', id],
    queryFn: () => productService.getProduct(id!),
    enabled: isEditing,
  });

  // Fetch categories for dropdown
  const { data: categoriesData } = useQuery({
    queryKey: ['product-categories'],
    queryFn: () => productService.getCategories({ per_page: 100 }),
  });

  // Create product mutation
  const createMutation = useMutation({
    mutationFn: (data: ProductFormData) => productService.createProduct(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] });
      navigate('/products');
    },
  });

  // Update product mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<ProductFormData> }) =>
      productService.updateProduct(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] });
      navigate('/products');
    },
  });

  useEffect(() => {
    if (product && isEditing) {
      reset({
        category_id: product.category_id || '',
        code: product.code,
        name: product.name,
        type: product.type,
        uom: product.uom,
        price: product.price || undefined,
        cost: product.cost || undefined,
        discount_percent: product.discount_percent || undefined,
        pricing_strategy: product.pricing_strategy || undefined,
        is_active: product.is_active,
      });
    }
  }, [product, isEditing, reset]);

  const onSubmit = (data: ProductFormData) => {
    // Convert empty strings to undefined for optional fields
    const submitData = {
      ...data,
      category_id: data.category_id || undefined,
      price: data.price || undefined,
      cost: data.cost || undefined,
      discount_percent: data.discount_percent || undefined,
      pricing_strategy: data.pricing_strategy || undefined,
    };

    if (isEditing && id) {
      updateMutation.mutate({ id, data: submitData });
    } else {
      createMutation.mutate(submitData);
    }
  };

  const handleCancel = () => {
    navigate('/products');
  };

  const isLoading = createMutation.isPending || updateMutation.isPending;
  const error = createMutation.error || updateMutation.error;
  const categories = categoriesData?.data || [];

  if (isLoadingProduct && isEditing) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="200px">
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        {isEditing ? 'Edit Product' : 'Create Product'}
      </Typography>

      <Paper sx={{ p: 3, maxWidth: 800 }}>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error.message || 'An error occurred'}
          </Alert>
        )}

        <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
          <Grid container spacing={3}>
            <Grid item xs={12} sm={6}>
              <Controller
                name="category_id"
                control={control}
                render={({ field }) => (
                  <FormControl fullWidth error={!!errors.category_id}>
                    <InputLabel>Category</InputLabel>
                    <Select {...field} label="Category" disabled={isLoading}>
                      <MenuItem value="">
                        <em>No Category</em>
                      </MenuItem>
                      {categories.map((category: ProductCategory) => (
                        <MenuItem key={category.id} value={category.id}>
                          {category.name}
                        </MenuItem>
                      ))}
                    </Select>
                  </FormControl>
                )}
              />
            </Grid>

            <Grid item xs={12} sm={6}>
              <Controller
                name="code"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    required
                    fullWidth
                    label="Product Code"
                    error={!!errors.code}
                    helperText={errors.code?.message}
                    disabled={isLoading}
                  />
                )}
              />
            </Grid>

            <Grid item xs={12}>
              <Controller
                name="name"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    required
                    fullWidth
                    label="Product Name"
                    error={!!errors.name}
                    helperText={errors.name?.message}
                    disabled={isLoading}
                  />
                )}
              />
            </Grid>

            <Grid item xs={12} sm={6}>
              <Controller
                name="type"
                control={control}
                render={({ field }) => (
                  <FormControl fullWidth error={!!errors.type} required>
                    <InputLabel>Type</InputLabel>
                    <Select {...field} label="Type" disabled={isLoading}>
                      <MenuItem value="YIMULU">YIMULU</MenuItem>
                      <MenuItem value="SERVICE">SERVICE</MenuItem>
                      <MenuItem value="OTHER">OTHER</MenuItem>
                    </Select>
                  </FormControl>
                )}
              />
            </Grid>

            <Grid item xs={12} sm={6}>
              <Controller
                name="uom"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    required
                    fullWidth
                    label="Unit of Measure"
                    error={!!errors.uom}
                    helperText={errors.uom?.message}
                    disabled={isLoading}
                  />
                )}
              />
            </Grid>

            <Grid item xs={12} sm={6}>
              <Controller
                name="price"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    fullWidth
                    label="Selling Price"
                    type="number"
                    inputProps={{ step: '0.01', min: '0' }}
                    error={!!errors.price}
                    helperText={errors.price?.message}
                    disabled={isLoading}
                  />
                )}
              />
            </Grid>

            <Grid item xs={12} sm={6}>
              <Controller
                name="cost"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    fullWidth
                    label="Purchase Cost"
                    type="number"
                    inputProps={{ step: '0.01', min: '0' }}
                    error={!!errors.cost}
                    helperText={errors.cost?.message}
                    disabled={isLoading}
                  />
                )}
              />
            </Grid>

            <Grid item xs={12} sm={6}>
              <Controller
                name="discount_percent"
                control={control}
                render={({ field }) => (
                  <TextField
                    {...field}
                    fullWidth
                    label="Discount Percent"
                    type="number"
                    inputProps={{ step: '0.01', min: '0', max: '100' }}
                    error={!!errors.discount_percent}
                    helperText={errors.discount_percent?.message}
                    disabled={isLoading}
                  />
                )}
              />
            </Grid>

            <Grid item xs={12} sm={6}>
              <Controller
                name="pricing_strategy"
                control={control}
                render={({ field }) => (
                  <FormControl fullWidth error={!!errors.pricing_strategy}>
                    <InputLabel>Pricing Strategy</InputLabel>
                    <Select {...field} label="Pricing Strategy" disabled={isLoading}>
                      <MenuItem value="">
                        <em>None</em>
                      </MenuItem>
                      <MenuItem value="FIXED">Fixed Price</MenuItem>
                      <MenuItem value="PERCENTAGE">Percentage Markup</MenuItem>
                      <MenuItem value="MARGIN">Margin Based</MenuItem>
                    </Select>
                  </FormControl>
                )}
              />
            </Grid>

            <Grid item xs={12}>
              <Controller
                name="is_active"
                control={control}
                render={({ field }) => (
                  <FormControlLabel
                    control={
                      <Switch
                        {...field}
                        checked={field.value}
                        disabled={isLoading}
                      />
                    }
                    label="Active"
                  />
                )}
              />
            </Grid>
          </Grid>

          <Box sx={{ mt: 3, display: 'flex', gap: 2 }}>
            <Button
              type="submit"
              variant="contained"
              disabled={isLoading}
            >
              {isLoading ? <CircularProgress size={20} /> : (isEditing ? 'Update Product' : 'Create Product')}
            </Button>

            <Button
              type="button"
              variant="outlined"
              onClick={handleCancel}
              disabled={isLoading}
            >
              Cancel
            </Button>
          </Box>
        </Box>
      </Paper>
    </Box>
  );
};

export default ProductForm;