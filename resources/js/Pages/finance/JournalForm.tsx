import React, { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useForm, useFieldArray } from 'react-hook-form';
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
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  IconButton,
  Autocomplete,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
} from '@mui/material';
import { Add as AddIcon, Delete as DeleteIcon, Save as SaveIcon } from '@mui/icons-material';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';
import {
  financeService,
  CreateJournalData,
  CreateJournalLineData,
  GlJournal,
  GlAccount
} from '../../services/financeService';

const schema = yup.object({
  journal_date: yup.date().required('Journal date is required'),
  currency: yup.string().required('Currency is required'),
  fx_rate: yup.number().min(0, 'FX rate must be positive').required('FX rate is required'),
  source: yup.string().required('Source is required'),
  reference: yup.string().required('Reference is required'),
  memo: yup.string(),
  branch_id: yup.string(),
  external_ref: yup.string(),
  lines: yup.array().of(
    yup.object({
      account_id: yup.string().required('Account is required'),
      branch_id: yup.string(),
      memo: yup.string(),
      debit: yup.number().min(0, 'Debit must be non-negative'),
      credit: yup.number().min(0, 'Credit must be non-negative'),
    })
  ).min(1, 'At least one journal line is required'),
});

interface JournalFormData extends Omit<CreateJournalData, 'journal_date'> {
  journal_date: Date;
  lines: (CreateJournalLineData & { id?: string })[];
}

const JournalForm: React.FC = () => {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const isEditing = Boolean(id);
  const queryClient = useQueryClient();
  const [accounts, setAccounts] = useState<GlAccount[]>([]);

  const { register, handleSubmit, control, formState: { errors }, setValue, watch, reset } = useForm<JournalFormData>({
    resolver: yupResolver(schema),
    defaultValues: {
      journal_date: new Date(),
      currency: 'ETB',
      fx_rate: 1,
      source: 'MANUAL',
      reference: '',
      memo: '',
      lines: [{ account_id: '', memo: '', debit: 0, credit: 0 }],
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: 'lines',
  });

  // Fetch journal data if editing
  const { data: journal, isLoading: isLoadingJournal } = useQuery({
    queryKey: ['journal', id],
    queryFn: () => financeService.getJournal(id!),
    enabled: isEditing,
  });

  // Fetch accounts for autocomplete
  const { data: accountsData } = useQuery({
    queryKey: ['accounts'],
    queryFn: () => financeService.getAccounts({ status: 'ACTIVE' }),
  });

  // Create journal mutation
  const createMutation = useMutation({
    mutationFn: (data: CreateJournalData) => financeService.createJournal(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['journals'] });
      navigate('/finance/journals');
    },
  });

  // Update journal mutation
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<CreateJournalData> }) =>
      financeService.updateJournal(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['journals'] });
      navigate('/finance/journals');
    },
  });

  useEffect(() => {
    if (accountsData?.data) {
      setAccounts(accountsData.data);
    }
  }, [accountsData]);

  useEffect(() => {
    if (journal && isEditing) {
      reset({
        journal_date: new Date(journal.journal_date),
        currency: journal.currency,
        fx_rate: journal.fx_rate,
        source: journal.source,
        reference: journal.reference,
        memo: journal.memo,
        branch_id: journal.branch_id,
        external_ref: journal.external_ref,
        lines: journal.lines?.map(line => ({
          id: line.id,
          account_id: line.account_id,
          branch_id: line.branch_id,
          memo: line.memo,
          debit: line.debit,
          credit: line.credit,
        })) || [],
      });
    }
  }, [journal, isEditing, reset]);

  const onSubmit = (data: JournalFormData) => {
    const submitData: CreateJournalData = {
      ...data,
      journal_date: data.journal_date.toISOString().split('T')[0],
      lines: data.lines.map(line => ({
        account_id: line.account_id,
        branch_id: line.branch_id,
        memo: line.memo,
        debit: line.debit || 0,
        credit: line.credit || 0,
      })),
    };

    if (isEditing && id) {
      updateMutation.mutate({ id, data: submitData });
    } else {
      createMutation.mutate(submitData);
    }
  };

  const handleCancel = () => {
    navigate('/finance/journals');
  };

  const addLine = () => {
    append({ account_id: '', memo: '', debit: 0, credit: 0 });
  };

  const removeLine = (index: number) => {
    if (fields.length > 1) {
      remove(index);
    }
  };

  const calculateTotal = (field: 'debit' | 'credit') => {
    return watch('lines')?.reduce((sum, line) => sum + (line[field] || 0), 0) || 0;
  };

  const totalDebit = calculateTotal('debit');
  const totalCredit = calculateTotal('credit');
  const isBalanced = Math.abs(totalDebit - totalCredit) < 0.01;

  const isLoading = createMutation.isPending || updateMutation.isPending;
  const error = createMutation.error || updateMutation.error;

  if (isLoadingJournal && isEditing) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="200px">
        <CircularProgress />
      </Box>
    );
  }

  return (
    <LocalizationProvider dateAdapter={AdapterDateFns}>
      <Box>
        <Typography variant="h4" gutterBottom>
          {isEditing ? 'Edit Journal' : 'Create Journal'}
        </Typography>

        <Paper sx={{ p: 3, mb: 3 }}>
          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error.message || 'An error occurred'}
            </Alert>
          )}

          {!isBalanced && (
            <Alert severity="warning" sx={{ mb: 2 }}>
              Journal is not balanced. Total Debit: {totalDebit.toFixed(2)}, Total Credit: {totalCredit.toFixed(2)}
            </Alert>
          )}

          <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
            {/* Journal Header */}
            <Typography variant="h6" gutterBottom>
              Journal Header
            </Typography>
            <Grid container spacing={3} sx={{ mb: 3 }}>
              <Grid item xs={12} sm={6} md={3}>
                <DatePicker
                  label="Journal Date"
                  value={watch('journal_date')}
                  onChange={(date) => setValue('journal_date', date || new Date())}
                  slotProps={{
                    textField: {
                      fullWidth: true,
                      error: !!errors.journal_date,
                      helperText: errors.journal_date?.message,
                    }
                  }}
                />
              </Grid>
              <Grid item xs={12} sm={6} md={3}>
                <TextField
                  {...register('currency')}
                  required
                  fullWidth
                  label="Currency"
                  error={!!errors.currency}
                  helperText={errors.currency?.message}
                  disabled={isLoading}
                />
              </Grid>
              <Grid item xs={12} sm={6} md={3}>
                <TextField
                  {...register('fx_rate')}
                  required
                  fullWidth
                  type="number"
                  label="FX Rate"
                  error={!!errors.fx_rate}
                  helperText={errors.fx_rate?.message}
                  disabled={isLoading}
                />
              </Grid>
              <Grid item xs={12} sm={6} md={3}>
                <FormControl fullWidth error={!!errors.source}>
                  <InputLabel>Source</InputLabel>
                  <Select
                    {...register('source')}
                    label="Source"
                    disabled={isLoading}
                  >
                    <MenuItem value="MANUAL">Manual</MenuItem>
                    <MenuItem value="SYSTEM">System</MenuItem>
                    <MenuItem value="IMPORT">Import</MenuItem>
                  </Select>
                </FormControl>
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  {...register('reference')}
                  required
                  fullWidth
                  label="Reference"
                  error={!!errors.reference}
                  helperText={errors.reference?.message}
                  disabled={isLoading}
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <TextField
                  {...register('memo')}
                  fullWidth
                  label="Memo"
                  error={!!errors.memo}
                  helperText={errors.memo?.message}
                  disabled={isLoading}
                />
              </Grid>
            </Grid>

            {/* Journal Lines */}
            <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
              <Typography variant="h6">
                Journal Lines
              </Typography>
              <Button
                variant="outlined"
                startIcon={<AddIcon />}
                onClick={addLine}
                disabled={isLoading}
              >
                Add Line
              </Button>
            </Box>

            <TableContainer component={Paper}>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Account</TableCell>
                    <TableCell>Memo</TableCell>
                    <TableCell align="right">Debit</TableCell>
                    <TableCell align="right">Credit</TableCell>
                    <TableCell width={80}>Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {fields.map((field, index) => (
                    <TableRow key={field.id}>
                      <TableCell>
                        <Autocomplete
                          options={accounts}
                          getOptionLabel={(option) => `${option.code} - ${option.name}`}
                          value={accounts.find(acc => acc.id === watch(`lines.${index}.account_id`)) || null}
                          onChange={(_, newValue) => {
                            setValue(`lines.${index}.account_id`, newValue?.id || '');
                          }}
                          renderInput={(params) => (
                            <TextField
                              {...params}
                              size="small"
                              error={!!errors.lines?.[index]?.account_id}
                              helperText={errors.lines?.[index]?.account_id?.message}
                            />
                          )}
                          disabled={isLoading}
                        />
                      </TableCell>
                      <TableCell>
                        <TextField
                          {...register(`lines.${index}.memo`)}
                          size="small"
                          fullWidth
                          disabled={isLoading}
                        />
                      </TableCell>
                      <TableCell align="right">
                        <TextField
                          {...register(`lines.${index}.debit`)}
                          size="small"
                          type="number"
                          inputProps={{ min: 0, step: 0.01 }}
                          disabled={isLoading}
                          onChange={(e) => {
                            const value = parseFloat(e.target.value) || 0;
                            setValue(`lines.${index}.debit`, value);
                            if (value > 0) {
                              setValue(`lines.${index}.credit`, 0);
                            }
                          }}
                        />
                      </TableCell>
                      <TableCell align="right">
                        <TextField
                          {...register(`lines.${index}.credit`)}
                          size="small"
                          type="number"
                          inputProps={{ min: 0, step: 0.01 }}
                          disabled={isLoading}
                          onChange={(e) => {
                            const value = parseFloat(e.target.value) || 0;
                            setValue(`lines.${index}.credit`, value);
                            if (value > 0) {
                              setValue(`lines.${index}.debit`, 0);
                            }
                          }}
                        />
                      </TableCell>
                      <TableCell>
                        <IconButton
                          onClick={() => removeLine(index)}
                          disabled={isLoading || fields.length === 1}
                          color="error"
                        >
                          <DeleteIcon />
                        </IconButton>
                      </TableCell>
                    </TableRow>
                  ))}
                  <TableRow>
                    <TableCell colSpan={2} align="right">
                      <strong>Totals:</strong>
                    </TableCell>
                    <TableCell align="right">
                      <strong>{totalDebit.toFixed(2)}</strong>
                    </TableCell>
                    <TableCell align="right">
                      <strong>{totalCredit.toFixed(2)}</strong>
                    </TableCell>
                    <TableCell />
                  </TableRow>
                </TableBody>
              </Table>
            </TableContainer>

            <Box sx={{ mt: 3, display: 'flex', gap: 2 }}>
              <Button
                type="submit"
                variant="contained"
                startIcon={<SaveIcon />}
                disabled={isLoading || !isBalanced}
              >
                {isLoading ? <CircularProgress size={20} /> : (isEditing ? 'Update Journal' : 'Create Journal')}
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
    </LocalizationProvider>
  );
};

export default JournalForm;