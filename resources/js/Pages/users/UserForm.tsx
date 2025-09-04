import React, { useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
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
} from '@mui/material';
import { userService, CreateUserData, User } from '../../services/userService';

const schema = yup.object({
  name: yup.string().required('Name is required'),
  email: yup.string().email('Invalid email').required('Email is required'),
  password: yup.string().min(12, 'Password must be at least 12 characters').required('Password is required'),
});

interface UserFormData extends CreateUserData {}

const UserForm: React.FC = () => {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const isEditing = Boolean(id);
  const queryClient = useQueryClient();

  const { register, handleSubmit, formState: { errors }, reset } = useForm<UserFormData>({
    resolver: yupResolver(schema),
  });

  // Fetch user data if editing
  const { data: user, isLoading: isLoadingUser } = useQuery({
    queryKey: ['user', id],
    queryFn: () => userService.getUser(id!),
    enabled: isEditing,
  });

  // Create user mutation
  const createMutation = useMutation({
    mutationFn: (data: CreateUserData) => userService.createUser(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      navigate('/users');
    },
  });

  // Update user mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<CreateUserData> }) =>
      userService.updateUser(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      navigate('/users');
    },
  });

  useEffect(() => {
    if (user && isEditing) {
      reset({
        name: user.name,
        email: user.email,
        password: '', // Don't populate password for security
      });
    }
  }, [user, isEditing, reset]);

  const onSubmit = (data: UserFormData) => {
    if (isEditing && id) {
      updateMutation.mutate({ id, data });
    } else {
      createMutation.mutate(data);
    }
  };

  const handleCancel = () => {
    navigate('/users');
  };

  const isLoading = createMutation.isPending || updateMutation.isPending;
  const error = createMutation.error || updateMutation.error;

  if (isLoadingUser && isEditing) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="200px">
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        {isEditing ? 'Edit User' : 'Create User'}
      </Typography>

      <Paper sx={{ p: 3, maxWidth: 600 }}>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error.message || 'An error occurred'}
          </Alert>
        )}

        <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
          <Grid container spacing={3}>
            <Grid item xs={12}>
              <TextField
                {...register('name')}
                required
                fullWidth
                id="name"
                label="Full Name"
                error={!!errors.name}
                helperText={errors.name?.message}
                disabled={isLoading}
              />
            </Grid>

            <Grid item xs={12}>
              <TextField
                {...register('email')}
                required
                fullWidth
                id="email"
                label="Email Address"
                type="email"
                error={!!errors.email}
                helperText={errors.email?.message}
                disabled={isLoading}
              />
            </Grid>

            <Grid item xs={12}>
              <TextField
                {...register('password')}
                required
                fullWidth
                id="password"
                label="Password"
                type="password"
                error={!!errors.password}
                helperText={errors.password?.message || (isEditing ? 'Leave blank to keep current password' : '')}
                disabled={isLoading}
              />
            </Grid>
          </Grid>

          <Box sx={{ mt: 3, display: 'flex', gap: 2 }}>
            <Button
              type="submit"
              variant="contained"
              disabled={isLoading}
            >
              {isLoading ? <CircularProgress size={20} /> : (isEditing ? 'Update User' : 'Create User')}
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

export default UserForm;