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
} from '@mui/material';
import { telebirrService, CreateAgentData, TelebirrAgent } from '../../services/telebirrService';

const schema = yup.object({
  name: yup.string().required('Name is required'),
  short_code: yup.string().required('Short code is required').min(3, 'Short code must be at least 3 characters'),
  phone: yup.string().required('Phone is required'),
  location: yup.string(),
  status: yup.string().required('Status is required'),
  notes: yup.string(),
});

interface AgentFormData extends CreateAgentData {}

const AgentForm: React.FC = () => {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const isEditing = Boolean(id);
  const queryClient = useQueryClient();

  const { register, handleSubmit, formState: { errors }, reset, setValue } = useForm<AgentFormData>({
    resolver: yupResolver(schema),
    defaultValues: {
      status: 'Active',
    },
  });

  // Fetch agent data if editing
  const { data: agent, isLoading: isLoadingAgent } = useQuery({
    queryKey: ['telebirr-agent', id],
    queryFn: () => telebirrService.getAgent(id!),
    enabled: isEditing,
  });

  // Create agent mutation
  const createMutation = useMutation({
    mutationFn: (data: CreateAgentData) => telebirrService.createAgent(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telebirr-agents'] });
      navigate('/telebirr/agents');
    },
  });

  // Update agent mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<CreateAgentData> }) =>
      telebirrService.updateAgent(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telebirr-agents'] });
      navigate('/telebirr/agents');
    },
  });

  useEffect(() => {
    if (agent && isEditing) {
      reset({
        name: agent.name,
        short_code: agent.short_code,
        phone: agent.phone,
        location: agent.location || '',
        status: agent.status,
        notes: agent.notes || '',
      });
    }
  }, [agent, isEditing, reset]);

  const onSubmit = (data: AgentFormData) => {
    if (isEditing && id) {
      updateMutation.mutate({ id, data });
    } else {
      createMutation.mutate(data);
    }
  };

  const handleCancel = () => {
    navigate('/telebirr/agents');
  };

  const isLoading = createMutation.isPending || updateMutation.isPending;
  const error = createMutation.error || updateMutation.error;

  if (isLoadingAgent && isEditing) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="200px">
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        {isEditing ? 'Edit Agent' : 'Create Agent'}
      </Typography>

      <Paper sx={{ p: 3, maxWidth: 800 }}>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error.message || 'An error occurred'}
          </Alert>
        )}

        <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
          <Grid container spacing={3}>
            <Grid item xs={12} md={6}>
              <TextField
                {...register('name')}
                required
                fullWidth
                id="name"
                label="Agent Name"
                error={!!errors.name}
                helperText={errors.name?.message}
                disabled={isLoading}
              />
            </Grid>

            <Grid item xs={12} md={6}>
              <TextField
                {...register('short_code')}
                required
                fullWidth
                id="short_code"
                label="Short Code"
                error={!!errors.short_code}
                helperText={errors.short_code?.message}
                disabled={isLoading}
              />
            </Grid>

            <Grid item xs={12} md={6}>
              <TextField
                {...register('phone')}
                required
                fullWidth
                id="phone"
                label="Phone Number"
                error={!!errors.phone}
                helperText={errors.phone?.message}
                disabled={isLoading}
              />
            </Grid>

            <Grid item xs={12} md={6}>
              <TextField
                {...register('location')}
                fullWidth
                id="location"
                label="Location"
                error={!!errors.location}
                helperText={errors.location?.message}
                disabled={isLoading}
              />
            </Grid>

            <Grid item xs={12} md={6}>
              <FormControl fullWidth error={!!errors.status} disabled={isLoading}>
                <InputLabel>Status</InputLabel>
                <Select
                  {...register('status')}
                  label="Status"
                  onChange={(e) => setValue('status', e.target.value)}
                  defaultValue="Active"
                >
                  <MenuItem value="Active">Active</MenuItem>
                  <MenuItem value="Inactive">Inactive</MenuItem>
                </Select>
                {errors.status && (
                  <Typography variant="caption" color="error" sx={{ mt: 1, ml: 2 }}>
                    {errors.status.message}
                  </Typography>
                )}
              </FormControl>
            </Grid>

            <Grid item xs={12}>
              <TextField
                {...register('notes')}
                fullWidth
                id="notes"
                label="Notes"
                multiline
                rows={3}
                error={!!errors.notes}
                helperText={errors.notes?.message}
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
              {isLoading ? <CircularProgress size={20} /> : (isEditing ? 'Update Agent' : 'Create Agent')}
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

export default AgentForm;