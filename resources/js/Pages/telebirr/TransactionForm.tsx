import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { useMutation, useQueryClient } from '@tanstack/react-query';
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
  Card,
  CardContent,
  Stepper,
  Step,
  StepLabel,
} from '@mui/material';
import { telebirrService, CreateTransactionData } from '../../services/telebirrService';

const schema = yup.object({
  tx_type: yup.string().required('Transaction type is required'),
  agent_short_code: yup.string().when('tx_type', {
    is: (val: string) => ['ISSUE', 'REPAY', 'LOAN'].includes(val),
    then: (schema) => schema.required('Agent short code is required'),
  }),
  bank_external_number: yup.string().when('tx_type', {
    is: (val: string) => ['TOPUP', 'REPAY'].includes(val),
    then: (schema) => schema.required('Bank external number is required'),
  }),
  amount: yup.number().positive('Amount must be positive').required('Amount is required'),
  currency: yup.string().default('ETB'),
  idempotency_key: yup.string().required('Idempotency key is required'),
  remarks: yup.string().when('tx_type', {
    is: (val: string) => ['ISSUE', 'LOAN'].includes(val),
    then: (schema) => schema.required('Remarks are required'),
  }),
  external_ref: yup.string(),
});

interface TransactionFormData extends CreateTransactionData {}

const TransactionForm: React.FC = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [activeStep, setActiveStep] = useState(0);
  const [transactionType, setTransactionType] = useState<string>('');

  const { register, handleSubmit, formState: { errors }, reset, watch, setValue } = useForm<TransactionFormData>({
    resolver: yupResolver(schema),
    defaultValues: {
      currency: 'ETB',
      idempotency_key: crypto.randomUUID(),
    },
  });

  const watchedTxType = watch('tx_type');

  // Mutations for different transaction types
  const topupMutation = useMutation({
    mutationFn: (data: CreateTransactionData) => telebirrService.postTopup(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telebirr-transactions'] });
      navigate('/telebirr/transactions');
    },
  });

  const issueMutation = useMutation({
    mutationFn: (data: CreateTransactionData) => telebirrService.postIssue(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telebirr-transactions'] });
      navigate('/telebirr/transactions');
    },
  });

  const repayMutation = useMutation({
    mutationFn: (data: CreateTransactionData) => telebirrService.postRepay(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telebirr-transactions'] });
      navigate('/telebirr/transactions');
    },
  });

  const loanMutation = useMutation({
    mutationFn: (data: CreateTransactionData) => telebirrService.postLoan(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['telebirr-transactions'] });
      navigate('/telebirr/transactions');
    },
  });

  const onSubmit = (data: TransactionFormData) => {
    switch (data.tx_type) {
      case 'TOPUP':
        topupMutation.mutate(data);
        break;
      case 'ISSUE':
        issueMutation.mutate(data);
        break;
      case 'REPAY':
        repayMutation.mutate(data);
        break;
      case 'LOAN':
        loanMutation.mutate(data);
        break;
      default:
        break;
    }
  };

  const handleCancel = () => {
    navigate('/telebirr/transactions');
  };

  const handleTransactionTypeChange = (type: string) => {
    setTransactionType(type);
    setValue('tx_type', type);
    setActiveStep(1);
  };

  const isLoading = topupMutation.isPending || issueMutation.isPending ||
                   repayMutation.isPending || loanMutation.isPending;
  const error = topupMutation.error || issueMutation.error ||
                repayMutation.error || loanMutation.error;

  const steps = ['Select Type', 'Enter Details', 'Confirm'];

  const transactionTypes = [
    { value: 'TOPUP', label: 'Topup', description: 'Add funds to the system from bank' },
    { value: 'ISSUE', label: 'Issue E-float', description: 'Issue electronic float to an agent' },
    { value: 'REPAY', label: 'Repayment', description: 'Process repayment from agent to bank' },
    { value: 'LOAN', label: 'Loan E-float', description: 'Issue loan electronic float to agent' },
  ];

  if (activeStep === 0) {
    return (
      <Box>
        <Typography variant="h4" gutterBottom>
          Create Transaction
        </Typography>

        <Stepper activeStep={activeStep} sx={{ mb: 4 }}>
          {steps.map((label) => (
            <Step key={label}>
              <StepLabel>{label}</StepLabel>
            </Step>
          ))}
        </Stepper>

        <Grid container spacing={3}>
          {transactionTypes.map((type) => (
            <Grid item xs={12} md={6} key={type.value}>
              <Card
                sx={{
                  cursor: 'pointer',
                  '&:hover': { boxShadow: 3 },
                  height: '100%',
                }}
                onClick={() => handleTransactionTypeChange(type.value)}
              >
                <CardContent>
                  <Typography variant="h6" gutterBottom>
                    {type.label}
                  </Typography>
                  <Typography variant="body2" color="text.secondary">
                    {type.description}
                  </Typography>
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>

        <Box sx={{ mt: 3 }}>
          <Button variant="outlined" onClick={handleCancel}>
            Cancel
          </Button>
        </Box>
      </Box>
    );
  }

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Create {transactionType} Transaction
      </Typography>

      <Stepper activeStep={activeStep} sx={{ mb: 4 }}>
        {steps.map((label) => (
          <Step key={label}>
            <StepLabel>{label}</StepLabel>
          </Step>
        ))}
      </Stepper>

      <Paper sx={{ p: 3, maxWidth: 800 }}>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error.message || 'An error occurred'}
          </Alert>
        )}

        <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
          <Grid container spacing={3}>
            {/* Common Fields */}
            <Grid item xs={12} md={6}>
              <TextField
                {...register('amount')}
                required
                fullWidth
                id="amount"
                label="Amount"
                type="number"
                error={!!errors.amount}
                helperText={errors.amount?.message}
                disabled={isLoading}
              />
            </Grid>

            <Grid item xs={12} md={6}>
              <TextField
                {...register('currency')}
                fullWidth
                id="currency"
                label="Currency"
                defaultValue="ETB"
                disabled={isLoading}
              />
            </Grid>

            <Grid item xs={12}>
              <TextField
                {...register('idempotency_key')}
                required
                fullWidth
                id="idempotency_key"
                label="Idempotency Key"
                error={!!errors.idempotency_key}
                helperText={errors.idempotency_key?.message}
                disabled={isLoading}
              />
            </Grid>

            {/* Conditional Fields based on transaction type */}
            {watchedTxType && ['ISSUE', 'REPAY', 'LOAN'].includes(watchedTxType) && (
              <Grid item xs={12} md={6}>
                <TextField
                  {...register('agent_short_code')}
                  required
                  fullWidth
                  id="agent_short_code"
                  label="Agent Short Code"
                  error={!!errors.agent_short_code}
                  helperText={errors.agent_short_code?.message}
                  disabled={isLoading}
                />
              </Grid>
            )}

            {watchedTxType && ['TOPUP', 'REPAY'].includes(watchedTxType) && (
              <Grid item xs={12} md={6}>
                <TextField
                  {...register('bank_external_number')}
                  required
                  fullWidth
                  id="bank_external_number"
                  label="Bank External Number"
                  error={!!errors.bank_external_number}
                  helperText={errors.bank_external_number?.message}
                  disabled={isLoading}
                />
              </Grid>
            )}

            <Grid item xs={12}>
              <TextField
                {...register('external_ref')}
                fullWidth
                id="external_ref"
                label="External Reference"
                error={!!errors.external_ref}
                helperText={errors.external_ref?.message}
                disabled={isLoading}
              />
            </Grid>

            {watchedTxType && ['ISSUE', 'LOAN'].includes(watchedTxType) && (
              <Grid item xs={12}>
                <TextField
                  {...register('remarks')}
                  required
                  fullWidth
                  id="remarks"
                  label="Remarks"
                  multiline
                  rows={3}
                  error={!!errors.remarks}
                  helperText={errors.remarks?.message}
                  disabled={isLoading}
                />
              </Grid>
            )}
          </Grid>

          <Box sx={{ mt: 3, display: 'flex', gap: 2 }}>
            <Button
              type="submit"
              variant="contained"
              disabled={isLoading}
            >
              {isLoading ? <CircularProgress size={20} /> : 'Create Transaction'}
            </Button>

            <Button
              type="button"
              variant="outlined"
              onClick={() => setActiveStep(0)}
              disabled={isLoading}
            >
              Back
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

export default TransactionForm;