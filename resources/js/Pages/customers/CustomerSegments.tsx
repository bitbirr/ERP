import React, { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Button,
  TextField,
  Chip,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  TablePagination,
  InputAdornment,
  Alert,
  Grid,
  Card,
  CardContent,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
} from '@mui/material';
import {
  Add as AddIcon,
  Edit as EditIcon,
  Delete as DeleteIcon,
  Search as SearchIcon,
  Group as GroupIcon,
  People as PeopleIcon,
} from '@mui/icons-material';
import axios from 'axios';

interface CustomerSegment {
  id: string;
  name: string;
  description?: string;
  is_active: boolean;
  created_at: string;
  members_count?: number;
}

const CustomerSegments: React.FC = () => {
  const [segments, setSegments] = useState<CustomerSegment[]>([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(10);
  const [total, setTotal] = useState(0);
  const [searchQuery, setSearchQuery] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editingSegment, setEditingSegment] = useState<CustomerSegment | null>(null);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [segmentToDelete, setSegmentToDelete] = useState<CustomerSegment | null>(null);

  const fetchSegments = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams({
        page: (page + 1).toString(),
        per_page: rowsPerPage.toString(),
        q: searchQuery,
      });

      const response = await axios.get(`/api/segments?${params}`);
      setSegments(response.data.data);
      setTotal(response.data.meta.total);
    } catch (err) {
      setError('Failed to load customer segments');
      console.error('Error fetching segments:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSegments();
  }, [page, rowsPerPage, searchQuery]);

  const handleSearch = (event: React.ChangeEvent<HTMLInputElement>) => {
    setSearchQuery(event.target.value);
    setPage(0);
  };

  const handleCreate = () => {
    setEditingSegment(null);
    setFormOpen(true);
  };

  const handleEdit = (segment: CustomerSegment) => {
    setEditingSegment(segment);
    setFormOpen(true);
  };

  const handleDelete = (segment: CustomerSegment) => {
    setSegmentToDelete(segment);
    setDeleteDialogOpen(true);
  };

  const confirmDelete = async () => {
    if (!segmentToDelete) return;

    try {
      await axios.delete(`/api/segments/${segmentToDelete.id}`);
      setDeleteDialogOpen(false);
      setSegmentToDelete(null);
      fetchSegments();
    } catch (err) {
      setError('Failed to delete customer segment');
      console.error('Error deleting segment:', err);
    }
  };

  const handleFormClose = (refresh = false) => {
    setFormOpen(false);
    setEditingSegment(null);
    if (refresh) {
      fetchSegments();
    }
  };

  const handlePageChange = (event: unknown, newPage: number) => {
    setPage(newPage);
  };

  const handleRowsPerPageChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    setRowsPerPage(parseInt(event.target.value, 10));
    setPage(0);
  };

  const activeSegments = segments.filter(s => s.is_active).length;
  const totalMembers = segments.reduce((sum, s) => sum + (s.members_count || 0), 0);

  return (
    <Box sx={{ p: 3 }}>
      <Typography variant="h4" gutterBottom>
        Customer Segments Management
      </Typography>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      {/* Summary Cards */}
      <Grid container spacing={3} sx={{ mb: 3 }}>
        <Grid item xs={12} md={4}>
          <Card>
            <CardContent>
              <Typography color="textSecondary" gutterBottom>
                Total Segments
              </Typography>
              <Typography variant="h4">
                {total}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} md={4}>
          <Card>
            <CardContent>
              <Typography color="textSecondary" gutterBottom>
                Active Segments
              </Typography>
              <Typography variant="h4" color="success.main">
                {activeSegments}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} md={4}>
          <Card>
            <CardContent>
              <Typography color="textSecondary" gutterBottom>
                Total Members
              </Typography>
              <Typography variant="h4">
                {totalMembers}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Search and Actions */}
      <Box sx={{ mb: 3, display: 'flex', gap: 2, flexWrap: 'wrap', alignItems: 'center' }}>
        <TextField
          placeholder="Search segments..."
          value={searchQuery}
          onChange={handleSearch}
          InputProps={{
            startAdornment: (
              <InputAdornment position="start">
                <SearchIcon />
              </InputAdornment>
            ),
          }}
          sx={{ minWidth: 300 }}
        />

        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={handleCreate}
          sx={{ ml: 'auto' }}
        >
          Add Segment
        </Button>
      </Box>

      {/* Data Table */}
      <TableContainer component={Paper}>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Name</TableCell>
              <TableCell>Description</TableCell>
              <TableCell>Members</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Created</TableCell>
              <TableCell>Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading ? (
              <TableRow>
                <TableCell colSpan={6} align="center">
                  Loading...
                </TableCell>
              </TableRow>
            ) : segments.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} align="center">
                  No customer segments found
                </TableCell>
              </TableRow>
            ) : (
              segments.map((segment) => (
                <TableRow key={segment.id}>
                  <TableCell>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                      <GroupIcon color="primary" />
                      <Typography variant="body2">{segment.name}</Typography>
                    </Box>
                  </TableCell>
                  <TableCell>
                    <Typography variant="body2" sx={{ maxWidth: 300, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      {segment.description || '-'}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                      <PeopleIcon fontSize="small" />
                      <Typography variant="body2">
                        {segment.members_count || 0}
                      </Typography>
                    </Box>
                  </TableCell>
                  <TableCell>
                    <Chip
                      label={segment.is_active ? 'Active' : 'Inactive'}
                      size="small"
                      color={segment.is_active ? 'success' : 'error'}
                    />
                  </TableCell>
                  <TableCell>
                    <Typography variant="body2">
                      {new Date(segment.created_at).toLocaleDateString()}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Button
                      size="small"
                      onClick={() => handleEdit(segment)}
                      startIcon={<EditIcon />}
                    >
                      Edit
                    </Button>
                    <Button
                      size="small"
                      onClick={() => handleDelete(segment)}
                      startIcon={<DeleteIcon />}
                      color="error"
                    >
                      Delete
                    </Button>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>

      <TablePagination
        component="div"
        count={total}
        page={page}
        onPageChange={handlePageChange}
        rowsPerPage={rowsPerPage}
        onRowsPerPageChange={handleRowsPerPageChange}
        rowsPerPageOptions={[10, 25, 50]}
      />

      {/* Segment Form Dialog */}
      <Dialog
        open={formOpen}
        onClose={() => handleFormClose()}
        maxWidth="md"
        fullWidth
      >
        <DialogTitle>
          {editingSegment ? 'Edit Customer Segment' : 'Add New Customer Segment'}
        </DialogTitle>
        <DialogContent>
          <CustomerSegmentForm
            segment={editingSegment}
            onClose={handleFormClose}
          />
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog
        open={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
      >
        <DialogTitle>Confirm Delete</DialogTitle>
        <DialogContent>
          <Typography>
            Are you sure you want to delete customer segment "{segmentToDelete?.name}"?
            This action cannot be undone.
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDeleteDialogOpen(false)}>Cancel</Button>
          <Button onClick={confirmDelete} color="error" variant="contained">
            Delete
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

// Customer Segment Form Component
interface CustomerSegmentFormProps {
  segment?: CustomerSegment | null;
  onClose: (refresh?: boolean) => void;
}

const CustomerSegmentForm: React.FC<CustomerSegmentFormProps> = ({ segment, onClose }) => {
  const [formData, setFormData] = useState({
    name: segment?.name || '',
    description: segment?.description || '',
    is_active: segment?.is_active ?? true,
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setErrors({});

    try {
      if (segment) {
        await axios.patch(`/api/segments/${segment.id}`, formData);
      } else {
        await axios.post('/api/segments', formData);
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

  return (
    <Box component="form" onSubmit={handleSubmit} sx={{ mt: 1 }}>
      <Grid container spacing={3}>
        <Grid item xs={12}>
          <TextField
            fullWidth
            required
            label="Segment Name"
            value={formData.name}
            onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
            error={!!errors.name}
            helperText={errors.name}
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
          {segment ? 'Update Segment' : 'Create Segment'}
        </Button>
      </Box>
    </Box>
  );
};

export default CustomerSegments;