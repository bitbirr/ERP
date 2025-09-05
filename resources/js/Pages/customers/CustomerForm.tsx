import React, { useState, useEffect } from 'react';
import {
  Box,
  TextField,
  Button,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  FormHelperText,
  Alert,
  Grid,
  Typography,
  Chip,
  Autocomplete,
} from '@mui/material';
import axios from 'axios';

interface Customer {
  id: string;
  type: 'individual' | 'organization';
  name: string;
  email?: string;
  phone: string;
  tax_id?: string;
  description?: string;
  is_active: boolean;
  category_id?: string;
  segments?: Array<{ id: string; name: string }>;
}

interface Category {
  id: string;
  name: string;
}

interface Segment {
  id: string;
  name: string;
}

interface CustomerFormProps {
  customer?: Customer | null;
  onClose: (refresh?: boolean) => void;
}

const CustomerForm: React.FC<CustomerFormProps> = ({ customer, onClose }) => {
  const isEditing = !!customer;

  const [formData, setFormData] = useState({
    type: customer?.type || 'individual',
    name: customer?.name || '',
    email: customer?.email || '',
    phone: customer?.phone || '',
    tax_id: customer?.tax_id || '',
    description: customer?.description || '',
    is_active: customer?.is_active ?? true,
    category_id: customer?.category_id || '',
    segment_ids: customer?.segments?.map(s => s.id) || [],
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const [categories, setCategories] = useState<Category[]>([]);
  const [segments, setSegments] = useState<Segment[]>([]);
  const [duplicateCheck, setDuplicateCheck] = useState<{
    found: boolean;
    customer?: Customer;
  } | null>(null);
  const [checkingDuplicate, setCheckingDuplicate] = useState(false);

  useEffect(() => {
    fetchCategories();
    fetchSegments();
  }, []);

  const fetchCategories = async () => {
    try {
      const response = await axios.get('/api/categories');
      setCategories(response.data.data || []);
    } catch (err) {
      console.error('Error fetching categories:', err);
    }
  };

  const fetchSegments = async () => {
    try {
      const response = await axios.get('/api/segments');
      setSegments(response.data.data || []);
    } catch (err) {
      console.error('Error fetching segments:', err);
    }
  };

  const checkForDuplicates = async () => {
    if (!formData.phone && !formData.email) return;

    setCheckingDuplicate(true);
    try {
      const params = new URLSearchParams();
      if (formData.phone) params.append('phone', formData.phone);
      if (formData.email) params.append('email', formData.email);
      if (isEditing && customer?.id) params.append('exclude_id', customer.id);

      const response = await axios.get(`/api/customers/check-duplicate?${params}`);
      setDuplicateCheck(response.data);
    } catch (err) {
      console.error('Error checking duplicates:', err);
    } finally {
      setCheckingDuplicate(false);
    }
  };

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      checkForDuplicates();
    }, 500);

    return () => clearTimeout(timeoutId);
  }, [formData.phone, formData.email]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (duplicateCheck?.found) {
      // Show existing customer profile instead of creating new
      return;
    }

    setLoading(true);
    setErrors({});

    try {
      const submitData = {
        ...formData,
        category_id: formData.category_id || null,
      };

      if (isEditing && customer) {
        await axios.patch(`/api/customers/${customer.id}`, submitData);
      } else {
        await axios.post('/api/customers', submitData);
      }

      onClose(true);
    } catch (err: any) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleSegmentChange = (event: any, newValue: Segment[]) => {
    setFormData(prev => ({ ...prev, segment_ids: newValue.map(s => s.id) }));
  };

  if (duplicateCheck?.found && duplicateCheck.customer) {
    return (
      <Box>
        <Alert severity="warning" sx={{ mb: 2 }}>
          A customer with this phone number or email already exists!
        </Alert>

        <Typography variant="h6" gutterBottom>
          Existing Customer Profile
        </Typography>

        <Box sx={{ p: 2, border: '1px solid #ddd', borderRadius: 1, mb: 2 }}>
          <Typography><strong>Name:</strong> {duplicateCheck.customer.name}</Typography>
          <Typography><strong>Phone:</strong> {duplicateCheck.customer.phone}</Typography>
          {duplicateCheck.customer.email && (
            <Typography><strong>Email:</strong> {duplicateCheck.customer.email}</Typography>
          )}
          <Typography><strong>Type:</strong> {duplicateCheck.customer.type}</Typography>
          <Typography><strong>Status:</strong> {duplicateCheck.customer.is_active ? 'Active' : 'Inactive'}</Typography>
        </Box>

        <Box sx={{ display: 'flex', gap: 2, justifyContent: 'flex-end' }}>
          <Button onClick={() => onClose()}>
            Cancel
          </Button>
          <Button
            variant="contained"
            onClick={() => {
              // Navigate to customer profile
              window.location.href = `/customers/${duplicateCheck.customer!.id}`;
            }}
          >
            View Profile
          </Button>
        </Box>
      </Box>
    );
  }

  return (
    <Box component="form" onSubmit={handleSubmit} sx={{ mt: 1 }}>
      {checkingDuplicate && (
        <Alert severity="info" sx={{ mb: 2 }}>
          Checking for duplicate customers...
        </Alert>
      )}

      <Grid container spacing={3}>
        <Grid item xs={12} md={6}>
          <FormControl fullWidth required>
            <InputLabel>Customer Type</InputLabel>
            <Select
              value={formData.type}
              label="Customer Type"
              onChange={(e) => setFormData(prev => ({ ...prev, type: e.target.value }))}
            >
              <MenuItem value="individual">Individual</MenuItem>
              <MenuItem value="organization">Organization</MenuItem>
            </Select>
            {errors.type && <FormHelperText error>{errors.type}</FormHelperText>}
          </FormControl>
        </Grid>

        <Grid item xs={12} md={6}>
          <TextField
            fullWidth
            required
            label="Name"
            value={formData.name}
            onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
            error={!!errors.name}
            helperText={errors.name}
          />
        </Grid>

        <Grid item xs={12} md={6}>
          <TextField
            fullWidth
            required
            label="Phone Number"
            value={formData.phone}
            onChange={(e) => setFormData(prev => ({ ...prev, phone: e.target.value }))}
            error={!!errors.phone}
            helperText={errors.phone}
            placeholder="+251911123456"
          />
        </Grid>

        <Grid item xs={12} md={6}>
          <TextField
            fullWidth
            label="Email"
            type="email"
            value={formData.email}
            onChange={(e) => setFormData(prev => ({ ...prev, email: e.target.value }))}
            error={!!errors.email}
            helperText={errors.email}
          />
        </Grid>

        <Grid item xs={12} md={6}>
          <TextField
            fullWidth
            label="Tax ID"
            value={formData.tax_id}
            onChange={(e) => setFormData(prev => ({ ...prev, tax_id: e.target.value }))}
            error={!!errors.tax_id}
            helperText={errors.tax_id}
          />
        </Grid>

        <Grid item xs={12} md={6}>
          <FormControl fullWidth>
            <InputLabel>Category</InputLabel>
            <Select
              value={formData.category_id}
              label="Category"
              onChange={(e) => setFormData(prev => ({ ...prev, category_id: e.target.value }))}
            >
              <MenuItem value="">
                <em>None</em>
              </MenuItem>
              {categories.map((category) => (
                <MenuItem key={category.id} value={category.id}>
                  {category.name}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </Grid>

        <Grid item xs={12}>
          <Autocomplete
            multiple
            options={segments}
            getOptionLabel={(option) => option.name}
            value={segments.filter(s => formData.segment_ids.includes(s.id))}
            onChange={handleSegmentChange}
            renderTags={(value, getTagProps) =>
              value.map((option, index) => (
                <Chip
                  variant="outlined"
                  label={option.name}
                  {...getTagProps({ index })}
                  key={option.id}
                />
              ))
            }
            renderInput={(params) => (
              <TextField
                {...params}
                label="Customer Segments"
                placeholder="Select segments"
              />
            )}
          />
        </Grid>

        <Grid item xs={12}>
          <TextField
            fullWidth
            multiline
            rows={3}
            label="Description"
            value={formData.description}
            onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
            error={!!errors.description}
            helperText={errors.description}
          />
        </Grid>

        <Grid item xs={12}>
          <FormControl fullWidth>
            <InputLabel>Status</InputLabel>
            <Select
              value={formData.is_active ? '1' : '0'}
              label="Status"
              onChange={(e) => setFormData(prev => ({ ...prev, is_active: e.target.value === '1' }))}
            >
              <MenuItem value="1">Active</MenuItem>
              <MenuItem value="0">Inactive</MenuItem>
            </Select>
          </FormControl>
        </Grid>
      </Grid>

      <Box sx={{ display: 'flex', gap: 2, justifyContent: 'flex-end', mt: 3 }}>
        <Button onClick={() => onClose()}>
          Cancel
        </Button>
        <Button
          type="submit"
          variant="contained"
          disabled={loading}
        >
          {isEditing ? 'Update Customer' : 'Create Customer'}
        </Button>
      </Box>
    </Box>
  );
};

export default CustomerForm;