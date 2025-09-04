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
  Paper,
} from '@mui/material';
import { Add as AddIcon, Edit as EditIcon, PostAdd as PostIcon, Undo as ReverseIcon, Block as VoidIcon } from '@mui/icons-material';
import { DataGrid, GridColDef, GridToolbar } from '@mui/x-data-grid';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';
import { financeService, GlJournal } from '../../services/financeService';

const Journals: React.FC = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState({
    status: '',
    source: '',
    start_date: null as Date | null,
    end_date: null as Date | null,
  });

  // Fetch journals
  const { data, isLoading, error: queryError } = useQuery({
    queryKey: ['journals', filters],
    queryFn: () => financeService.getJournals({
      status: filters.status || undefined,
      source: filters.source || undefined,
      start_date: filters.start_date ? filters.start_date.toISOString().split('T')[0] : undefined,
      end_date: filters.end_date ? filters.end_date.toISOString().split('T')[0] : undefined,
      per_page: 50,
    }),
  });

  // Post journal mutation
  const postMutation = useMutation({
    mutationFn: (journalId: string) => financeService.postJournal(journalId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['journals'] });
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to post journal');
    },
  });

  // Reverse journal mutation
  const reverseMutation = useMutation({
    mutationFn: ({ journalId, reason }: { journalId: string; reason: string }) =>
      financeService.reverseJournal(journalId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['journals'] });
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to reverse journal');
    },
  });

  // Void journal mutation
  const voidMutation = useMutation({
    mutationFn: ({ journalId, reason }: { journalId: string; reason: string }) =>
      financeService.voidJournal(journalId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['journals'] });
      setError(null);
    },
    onError: (error: any) => {
      setError(error.response?.data?.message || 'Failed to void journal');
    },
  });

  const handleCreateJournal = () => {
    navigate('/finance/journals/new');
  };

  const handleEditJournal = (journalId: string) => {
    navigate(`/finance/journals/${journalId}/edit`);
  };

  const handleViewJournal = (journalId: string) => {
    navigate(`/finance/journals/${journalId}`);
  };

  const handlePostJournal = (journalId: string) => {
    if (window.confirm('Are you sure you want to post this journal? This action cannot be undone.')) {
      postMutation.mutate(journalId);
    }
  };

  const handleReverseJournal = (journalId: string) => {
    const reason = prompt('Enter reason for reversal:');
    if (reason) {
      reverseMutation.mutate({ journalId, reason });
    }
  };

  const handleVoidJournal = (journalId: string) => {
    const reason = prompt('Enter reason for voiding:');
    if (reason) {
      voidMutation.mutate({ journalId, reason });
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'DRAFT': return 'warning';
      case 'POSTED': return 'success';
      case 'VOIDED': return 'error';
      case 'REVERSED': return 'secondary';
      default: return 'default';
    }
  };

  const columns: GridColDef[] = [
    { field: 'journal_no', headerName: 'Journal No', width: 120 },
    {
      field: 'journal_date',
      headerName: 'Date',
      width: 120,
      valueFormatter: (params) => new Date(params.value).toLocaleDateString(),
    },
    { field: 'source', headerName: 'Source', width: 100 },
    { field: 'reference', headerName: 'Reference', width: 150 },
    {
      field: 'status',
      headerName: 'Status',
      width: 100,
      renderCell: (params) => (
        <Chip
          label={params.value}
          color={getStatusColor(params.value)}
          size="small"
        />
      ),
    },
    { field: 'currency', headerName: 'Currency', width: 80 },
    {
      field: 'total_debit',
      headerName: 'Total Debit',
      width: 120,
      valueFormatter: (params) => {
        const journal = params.row as GlJournal;
        return journal.lines ? journal.lines.reduce((sum, line) => sum + line.debit, 0).toFixed(2) : '0.00';
      },
    },
    {
      field: 'total_credit',
      headerName: 'Total Credit',
      width: 120,
      valueFormatter: (params) => {
        const journal = params.row as GlJournal;
        return journal.lines ? journal.lines.reduce((sum, line) => sum + line.credit, 0).toFixed(2) : '0.00';
      },
    },
    {
      field: 'created_at',
      headerName: 'Created',
      width: 180,
      valueFormatter: (params) => new Date(params.value).toLocaleDateString(),
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 200,
      renderCell: (params) => {
        const journal = params.row as GlJournal;
        return (
          <Box>
            <Button
              size="small"
              onClick={() => handleViewJournal(journal.id)}
              sx={{ mr: 1, mb: 1 }}
            >
              View
            </Button>
            {journal.status === 'DRAFT' && (
              <>
                <Button
                  size="small"
                  startIcon={<EditIcon />}
                  onClick={() => handleEditJournal(journal.id)}
                  sx={{ mr: 1, mb: 1 }}
                >
                  Edit
                </Button>
                <Button
                  size="small"
                  startIcon={<PostIcon />}
                  onClick={() => handlePostJournal(journal.id)}
                  sx={{ mr: 1, mb: 1 }}
                >
                  Post
                </Button>
              </>
            )}
            {journal.status === 'POSTED' && (
              <Button
                size="small"
                startIcon={<ReverseIcon />}
                onClick={() => handleReverseJournal(journal.id)}
                sx={{ mr: 1, mb: 1 }}
              >
                Reverse
              </Button>
            )}
            {(journal.status === 'DRAFT' || journal.status === 'POSTED') && (
              <Button
                size="small"
                startIcon={<VoidIcon />}
                color="error"
                onClick={() => handleVoidJournal(journal.id)}
                sx={{ mb: 1 }}
              >
                Void
              </Button>
            )}
          </Box>
        );
      },
    },
  ];

  if (isLoading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="200px">
        <CircularProgress />
      </Box>
    );
  }

  if (queryError) {
    return (
      <Alert severity="error">
        Failed to load journals. Please try again.
      </Alert>
    );
  }

  const journals = data?.data || [];

  return (
    <LocalizationProvider dateAdapter={AdapterDateFns}>
      <Box>
        <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
          <Typography variant="h4">General Ledger Journals</Typography>
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={handleCreateJournal}
          >
            Create Journal
          </Button>
        </Box>

        {/* Filters */}
        <Paper sx={{ p: 2, mb: 3 }}>
          <Typography variant="h6" gutterBottom>
            Filters
          </Typography>
          <Grid container spacing={2}>
            <Grid item xs={12} sm={6} md={3}>
              <FormControl fullWidth size="small">
                <InputLabel>Status</InputLabel>
                <Select
                  value={filters.status}
                  label="Status"
                  onChange={(e) => setFilters({ ...filters, status: e.target.value })}
                >
                  <MenuItem value="">All</MenuItem>
                  <MenuItem value="DRAFT">Draft</MenuItem>
                  <MenuItem value="POSTED">Posted</MenuItem>
                  <MenuItem value="VOIDED">Voided</MenuItem>
                  <MenuItem value="REVERSED">Reversed</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <TextField
                fullWidth
                size="small"
                label="Source"
                value={filters.source}
                onChange={(e) => setFilters({ ...filters, source: e.target.value })}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <DatePicker
                label="Start Date"
                value={filters.start_date}
                onChange={(date) => setFilters({ ...filters, start_date: date })}
                slotProps={{ textField: { size: 'small', fullWidth: true } }}
              />
            </Grid>
            <Grid item xs={12} sm={6} md={3}>
              <DatePicker
                label="End Date"
                value={filters.end_date}
                onChange={(date) => setFilters({ ...filters, end_date: date })}
                slotProps={{ textField: { size: 'small', fullWidth: true } }}
              />
            </Grid>
          </Grid>
        </Paper>

        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}

        <div style={{ height: 600, width: '100%' }}>
          <DataGrid
            rows={journals}
            columns={columns}
            pageSize={10}
            rowsPerPageOptions={[10, 25, 50]}
            components={{ Toolbar: GridToolbar }}
            disableSelectionOnClick
            getRowId={(row) => row.id}
          />
        </div>
      </Box>
    </LocalizationProvider>
  );
};

export default Journals;