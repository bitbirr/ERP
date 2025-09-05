import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
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
  Card,
  CardContent,
  Stepper,
  Step,
  StepLabel,
} from '@mui/material';
import { telebirrService, CreateTransactionData, BankAccount } from '../../services/telebirrService';

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
    is: (val: string) => ['ISSUE', 'LOAN', 'TOPUP'].includes(val),
    then: (schema) => schema.required('Remarks are required'),
  }),
  external_ref: yup.string(),
  payment_method: yup.string().when('tx_type', {
    is: 'TOPUP',
    then: (schema) => schema.required('Payment method is required').oneOf(['CASH', 'BANK_TRANSFER', 'MOBILE'], 'Payment method must be CASH, BANK_TRANSFER, or MOBILE'),
  }),
});

interface TransactionFormData extends CreateTransactionData {}

const TransactionForm: React.FC = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [activeStep, setActiveStep] = useState(0);
  const [transactionType, setTransactionType] = useState<string>('');
  const [transactionRef, setTransactionRef] = useState<string | null>(null);
  const [transactionResponse, setTransactionResponse] = useState<any>(null);

  const { register, handleSubmit, formState: { errors }, reset, watch, setValue } = useForm<TransactionFormData>({
    resolver: yupResolver(schema),
    defaultValues: {
      currency: 'ETB',
      idempotency_key: '',
    },
  });

  const watchedTxType = watch('tx_type');
  const watchedBankExternalNumber = watch('bank_external_number');
  const watchedPaymentMethod = watch('payment_method');

  // Fetch bank accounts
  const { data: bankAccounts, isLoading: bankAccountsLoading } = useQuery({
    queryKey: ['bank-accounts'],
    queryFn: telebirrService.getBankAccounts,
  });

  // Mutations for different transaction types
  const topupMutation = useMutation({
    mutationFn: (data: CreateTransactionData) => telebirrService.postTopup(data),
    onSuccess: (response) => {
      setTransactionRef(response.data.id);
      setTransactionResponse(response);
      queryClient.invalidateQueries({ queryKey: ['telebirr-transactions'] });
      setActiveStep(3); // Go to receipt step
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
    if (activeStep === 1) {
      // Go to confirm step
      setActiveStep(2);
    } else if (activeStep === 2) {
      // Submit the transaction
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
    }
  };

  const handleCancel = () => {
    navigate('/telebirr/transactions');
  };

  const handleTransactionTypeChange = (type: string) => {
    setTransactionType(type);
    setValue('tx_type', type);
    if (type === 'TOPUP') {
      setValue('idempotency_key', `topup-${Date.now()}`);
    } else {
      setValue('idempotency_key', crypto.randomUUID());
    }
    // Reset conditional fields
    setValue('agent_short_code', '');
    setValue('bank_external_number', '');
    setValue('payment_method', '');
    setValue('remarks', '');
    setValue('external_ref', '');
    setActiveStep(1);
  };

  const isLoading = topupMutation.isPending || issueMutation.isPending ||
                   repayMutation.isPending || loanMutation.isPending;
  const error = topupMutation.error || issueMutation.error ||
                repayMutation.error || loanMutation.error;

  const steps = ['Select Type', 'Enter Details', 'Confirm', 'Receipt'];

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

  if (activeStep === 2) {
    return (
      <Box>
        <Typography variant="h4" gutterBottom>
          Confirm {transactionType} Transaction
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

          <Typography variant="h6" gutterBottom>
            Transaction Summary
          </Typography>
          <Grid container spacing={2}>
            <Grid item xs={12} md={6}>
              <Typography><strong>Type:</strong> {transactionType}</Typography>
            </Grid>
            <Grid item xs={12} md={6}>
              <Typography><strong>Amount:</strong> {watch('amount')} {watch('currency')}</Typography>
            </Grid>
            <Grid item xs={12} md={6}>
              <Typography><strong>Idempotency Key:</strong> {watch('idempotency_key')}</Typography>
            </Grid>
            {watch('agent_short_code') && (
              <Grid item xs={12} md={6}>
                <Typography><strong>Agent Short Code:</strong> {watch('agent_short_code')}</Typography>
              </Grid>
            )}
            {watch('bank_external_number') && (
              <Grid item xs={12} md={6}>
                <Typography><strong>Bank External Number:</strong> {watch('bank_external_number')}</Typography>
              </Grid>
            )}
            {watch('external_ref') && (
              <Grid item xs={12} md={6}>
                <Typography><strong>External Reference:</strong> {watch('external_ref')}</Typography>
              </Grid>
            )}
            {watch('payment_method') && (
              <Grid item xs={12} md={6}>
                <Typography><strong>Payment Method:</strong> {watch('payment_method')}</Typography>
              </Grid>
            )}
            {watch('remarks') && (
              <Grid item xs={12}>
                <Typography><strong>Remarks:</strong> {watch('remarks')}</Typography>
              </Grid>
            )}
          </Grid>

          <Box sx={{ mt: 3, display: 'flex', gap: 2 }}>
            <Button
              type="submit"
              variant="contained"
              disabled={isLoading}
              onClick={handleSubmit(onSubmit)}
            >
              {isLoading ? <CircularProgress size={20} /> : 'Create Transaction'}
            </Button>

            <Button
              type="button"
              variant="outlined"
              onClick={() => setActiveStep(1)}
              disabled={isLoading}
            >
              Back
            </Button>

            <Button
              type="button"
              variant="outlined"
              onClick={() => setActiveStep(0)}
              disabled={isLoading}
            >
              Back to Type Selection
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
        </Paper>
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
                <FormControl fullWidth error={!!errors.bank_external_number} disabled={isLoading}>
                  <InputLabel id="bank-external-number-label">Bank External Number</InputLabel>
                  <Select
                    value={watchedBankExternalNumber || ''}
                    onChange={(e) => setValue('bank_external_number', e.target.value)}
                    labelId="bank-external-number-label"
                    id="bank_external_number"
                    label="Bank External Number"
                    disabled={isLoading || bankAccountsLoading}
                  >
                    {bankAccounts?.data?.data?.map((account: BankAccount) => (
                      <MenuItem key={account.id} value={account.external_number}>
                        {account.external_number} - {account.name}
                      </MenuItem>
                    ))}
                  </Select>
                  {errors.bank_external_number && (
                    <Typography variant="caption" color="error" sx={{ mt: 1, ml: 1 }}>
                      {errors.bank_external_number.message}
                    </Typography>
                  )}
                </FormControl>
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

            {watchedTxType === 'TOPUP' && (
              <Grid item xs={12} md={6}>
                <FormControl fullWidth error={!!errors.payment_method} disabled={isLoading}>
                  <InputLabel id="payment-method-label">Payment Method</InputLabel>
                  <Select
                    value={watchedPaymentMethod || ''}
                    onChange={(e) => setValue('payment_method', e.target.value)}
                    labelId="payment-method-label"
                    id="payment_method"
                    label="Payment Method"
                    disabled={isLoading}
                  >
                    <MenuItem value="CASH">CASH</MenuItem>
                    <MenuItem value="BANK_TRANSFER">BANK_TRANSFER</MenuItem>
                    <MenuItem value="MOBILE">MOBILE</MenuItem>
                  </Select>
                  {errors.payment_method && (
                    <Typography variant="caption" color="error" sx={{ mt: 1, ml: 1 }}>
                      {errors.payment_method.message}
                    </Typography>
                  )}
                </FormControl>
              </Grid>
            )}

            {watchedTxType && ['ISSUE', 'LOAN', 'TOPUP'].includes(watchedTxType) && (
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
              {isLoading ? <CircularProgress size={20} /> : activeStep === 1 ? 'Next' : 'Create Transaction'}
            </Button>

            {activeStep === 2 && (
              <Button
                type="button"
                variant="outlined"
                onClick={() => setActiveStep(1)}
                disabled={isLoading}
              >
                Back
              </Button>
            )}

            <Button
              type="button"
              variant="outlined"
              onClick={() => setActiveStep(0)}
              disabled={isLoading}
            >
              Back to Type Selection
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

      {transactionRef && (
        <Box sx={{ mt: 2 }}>
          <Alert severity="success">
            Transaction created successfully. Reference: {transactionRef}
          </Alert>
          <Box sx={{ mt: 2 }}>
            <Button variant="contained" onClick={() => navigate('/telebirr/transactions')}>
              View Transactions
            </Button>
          </Box>
        </Box>
      )}
    </Box>
  );

  if (activeStep === 3 && transactionResponse) {
    return (
      <Box>
        <Typography variant="h4" gutterBottom>
          Transaction Receipt
        </Typography>

        <Stepper activeStep={activeStep} sx={{ mb: 4 }}>
          {steps.map((label) => (
            <Step key={label}>
              <StepLabel>{label}</StepLabel>
            </Step>
          ))}
        </Stepper>

        <Paper sx={{ p: 3, maxWidth: 800 }}>
          <Alert severity="success" sx={{ mb: 2 }}>
            {transactionResponse.message}
          </Alert>

          <Typography variant="h6" gutterBottom>
            Transaction Details
          </Typography>
          <Grid container spacing={2}>
            <Grid item xs={12} md={6}>
              <Typography><strong>Transaction Type:</strong> {transactionResponse.data.tx_type}</Typography>
            </Grid>
            <Grid item xs={12} md={6}>
              <Typography><strong>Amount:</strong> {transactionResponse.data.amount} {transactionResponse.data.currency}</Typography>
            </Grid>
            <Grid item xs={12} md={6}>
              <Typography><strong>Status:</strong> {transactionResponse.data.status}</Typography>
            </Grid>
            <Grid item xs={12} md={6}>
              <Typography><strong>Posted At:</strong> {new Date(transactionResponse.data.posted_at).toLocaleString()}</Typography>
            </Grid>
            <Grid item xs={12} md={6}>
              <Typography><strong>Idempotency Key:</strong> {transactionResponse.data.idempotency_key}</Typography>
            </Grid>
            <Grid item xs={12} md={6}>
              <Typography><strong>External Reference:</strong> {transactionResponse.data.external_ref}</Typography>
            </Grid>
            <Grid item xs={12}>
              <Typography><strong>Remarks:</strong> {transactionResponse.data.remarks}</Typography>
            </Grid>
          </Grid>

          {transactionResponse.data.bank_account && (
            <>
              <Typography variant="h6" gutterBottom sx={{ mt: 3 }}>
                Bank Account Details
              </Typography>
              <Grid container spacing={2}>
                <Grid item xs={12} md={6}>
                  <Typography><strong>Name:</strong> {transactionResponse.data.bank_account.name}</Typography>
                </Grid>
                <Grid item xs={12} md={6}>
                  <Typography><strong>Account Number:</strong> {transactionResponse.data.bank_account.account_number}</Typography>
                </Grid>
                <Grid item xs={12} md={6}>
                  <Typography><strong>Balance:</strong> {transactionResponse.data.bank_account.balance} {transactionResponse.data.currency}</Typography>
                </Grid>
              </Grid>
            </>
          )}

          {transactionResponse.data.gl_journal && (
            <>
              <Typography variant="h6" gutterBottom sx={{ mt: 3 }}>
                Journal Details
              </Typography>
              <Grid container spacing={2}>
                <Grid item xs={12} md={6}>
                  <Typography><strong>Journal No:</strong> {transactionResponse.data.gl_journal.journal_no}</Typography>
                </Grid>
                <Grid item xs={12} md={6}>
                  <Typography><strong>Status:</strong> {transactionResponse.data.gl_journal.status}</Typography>
                </Grid>
                <Grid item xs={12} md={6}>
                  <Typography><strong>Memo:</strong> {transactionResponse.data.gl_journal.memo}</Typography>
                </Grid>
                <Grid item xs={12} md={6}>
                  <Typography><strong>Posted At:</strong> {new Date(transactionResponse.data.gl_journal.posted_at).toLocaleString()}</Typography>
                </Grid>
              </Grid>
            </>
          )}

          <Box sx={{ mt: 3, display: 'flex', gap: 2 }}>
            <Button variant="contained" onClick={() => navigate('/telebirr/transactions')}>
              View Transactions
            </Button>
            <Button variant="outlined" onClick={() => {
              setActiveStep(0);
              setTransactionType('');
              setTransactionRef(null);
              setTransactionResponse(null);
              reset();
            }}>
              Create Another Transaction
            </Button>
          </Box>
        </Paper>
      </Box>
    );
  }
};

export default TransactionForm;