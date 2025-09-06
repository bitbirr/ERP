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
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Chip,
} from '@mui/material';
import { userService, CreateUserData, User, Role } from '../../services/userService';
import HelpIcon from '../../components/HelpIcon';

const schema = yup.object({
  name: yup.string().required('Name is required'),
  email: yup.string().email('Invalid email').required('Email is required'),
  password: yup.string().min(12, 'Password must be at least 12 characters').required('Password is required'),
  role_id: yup.number().nullable(),
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

  // Fetch roles
  const { data: roles, isLoading: isLoadingRoles } = useQuery({
    queryKey: ['roles'],
    queryFn: () => userService.getRoles(),
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

  // Assign role mutation
  const assignRoleMutation = useMutation({
    mutationFn: ({ userId, roleId }: { userId: string; roleId: number }) =>
      userService.assignRole(userId, roleId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
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

  const onSubmit = async (data: UserFormData) => {
    try {
      let userId: string;

      if (isEditing && id) {
        await updateMutation.mutateAsync({ id, data });
        userId = id;
      } else {
        const newUser = await createMutation.mutateAsync(data);
        userId = newUser.id;
      }

      // Assign role if selected
      if (data.role_id && userId) {
        await assignRoleMutation.mutateAsync({ userId, roleId: data.role_id });
      }

      navigate('/users');
    } catch (error) {
      // Error handling is done by the mutations
    }
  };

  const handleCancel = () => {
    navigate('/users');
  };

  const isLoading = createMutation.isPending || updateMutation.isPending || assignRoleMutation.isPending;
  const error = createMutation.error || updateMutation.error || assignRoleMutation.error;

  if (isLoadingUser && isEditing) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="200px">
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box>
      <Box display="flex" alignItems="center" mb={2}>
        <Typography variant="h4" sx={{ mr: 1 }}>
          {isEditing ? 'Edit User' : 'Create User'}
        </Typography>
        <HelpIcon title="Fill in user details. Password must be at least 12 characters. Assign appropriate roles for security. Email verification is recommended." />
      </Box>

      <Paper sx={{ p: 3, maxWidth: 600 }}>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error.message || 'An error occurred'}
          </Alert>
        )}

        <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
          <Grid container spacing={3}>
            <Grid item xs={12}>
              <Box display="flex" alignItems="center">
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
                <HelpIcon title="Enter the user's full name as it will appear in the system." />
              </Box>
            </Grid>

            <Grid item xs={12}>
              <Box display="flex" alignItems="center">
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
                <HelpIcon title="Provide a valid email address. Users will need to verify this for account activation." />
              </Box>
            </Grid>

            <Grid item xs={12}>
              <Box display="flex" alignItems="center">
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
                <HelpIcon title="Create a strong password with at least 12 characters, including uppercase, lowercase, numbers, and symbols." />
              </Box>
            </Grid>

            <Grid item xs={12}>
              <Box display="flex" alignItems="center">
                <FormControl fullWidth disabled={isLoading || isLoadingRoles}>
                  <InputLabel id="role-label">Role (Optional)</InputLabel>
                  <Select
                    {...register('role_id')}
                    labelId="role-label"
                    id="role_id"
                    label="Role (Optional)"
                    value={undefined} // Controlled by react-hook-form
                  >
                    <MenuItem value="">
                      <em>No Role</em>
                    </MenuItem>
                    {roles?.map((role: Role) => (
                      <MenuItem key={role.id} value={role.id}>
                        <Box>
                          <Typography variant="body1">{role.name}</Typography>
                          <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.5, mt: 0.5 }}>
                            {role.capabilities.slice(0, 3).map((cap: string) => (
                              <Chip key={cap} label={cap} size="small" variant="outlined" />
                            ))}
                            {role.capabilities.length > 3 && (
                              <Chip label={`+${role.capabilities.length - 3} more`} size="small" variant="outlined" />
                            )}
                          </Box>
                        </Box>
                      </MenuItem>
                    ))}
                  </Select>
                </FormControl>
                <HelpIcon title="Assign a role to define user permissions and access levels. Roles determine what actions the user can perform in the system." />
              </Box>
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