import React, { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Button,
  TextField,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
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
  Accordion,
  AccordionSummary,
  AccordionDetails,
} from '@mui/material';
import {
  Add as AddIcon,
  Edit as EditIcon,
  Delete as DeleteIcon,
  Search as SearchIcon,
  LocationOn as LocationIcon,
  ExpandMore as ExpandMoreIcon,
  Public as PublicIcon,
  Map as MapIcon,
  Home as HomeIcon,
} from '@mui/icons-material';
import axios from 'axios';

interface Region {
  id: number;
  name: string;
  code?: string;
  is_active: boolean;
  zones?: Zone[];
}

interface Zone {
  id: number;
  name: string;
  code?: string;
  region_id: number;
  is_active: boolean;
  woredas?: Woreda[];
}

interface Woreda {
  id: number;
  name: string;
  code?: string;
  zone_id: number;
  is_active: boolean;
  kebeles?: Kebele[];
}

interface Kebele {
  id: number;
  name: string;
  code?: string;
  woreda_id: number;
  is_active: boolean;
}

const AddressEnums: React.FC = () => {
  const [regions, setRegions] = useState<Region[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<any>(null);
  const [formType, setFormType] = useState<'region' | 'zone' | 'woreda' | 'kebele'>('region');
  const [parentId, setParentId] = useState<number | null>(null);

  const fetchRegions = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await axios.get('/api/regions?include=zones.woredas.kebeles');
      setRegions(response.data.data || []);
    } catch (err) {
      setError('Failed to load address data');
      console.error('Error fetching regions:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRegions();
  }, []);

  const handleCreate = (type: 'region' | 'zone' | 'woreda' | 'kebele', parentId?: number) => {
    setFormType(type);
    setParentId(parentId || null);
    setEditingItem(null);
    setFormOpen(true);
  };

  const handleEdit = (item: any, type: 'region' | 'zone' | 'woreda' | 'kebele') => {
    setFormType(type);
    setEditingItem(item);
    setParentId(null);
    setFormOpen(true);
  };

  const handleDelete = async (item: any, type: string, endpoint: string) => {
    if (!confirm(`Are you sure you want to delete this ${type}?`)) return;

    try {
      await axios.delete(`/api/${endpoint}/${item.id}`);
      fetchRegions();
    } catch (err) {
      setError(`Failed to delete ${type}`);
      console.error(`Error deleting ${type}:`, err);
    }
  };

  const handleFormClose = (refresh = false) => {
    setFormOpen(false);
    setEditingItem(null);
    if (refresh) {
      fetchRegions();
    }
  };

  const getStats = () => {
    let totalRegions = regions.length;
    let totalZones = regions.reduce((sum, r) => sum + (r.zones?.length || 0), 0);
    let totalWoredas = regions.reduce((sum, r) =>
      sum + r.zones?.reduce((zSum, z) => zSum + (z.woredas?.length || 0), 0) || 0, 0);
    let totalKebeles = regions.reduce((sum, r) =>
      sum + r.zones?.reduce((zSum, z) =>
        zSum + z.woredas?.reduce((wSum, w) => wSum + (w.kebeles?.length || 0), 0) || 0, 0) || 0, 0);

    return { totalRegions, totalZones, totalWoredas, totalKebeles };
  };

  const stats = getStats();

  return (
    <Box sx={{ p: 3 }}>
      <Typography variant="h4" gutterBottom>
        Address Data Management
      </Typography>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      {/* Summary Cards */}
      <Grid container spacing={3} sx={{ mb: 3 }}>
        <Grid item xs={12} md={3}>
          <Card>
            <CardContent>
              <Typography color="textSecondary" gutterBottom>
                Regions
              </Typography>
              <Typography variant="h4">
                {stats.totalRegions}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} md={3}>
          <Card>
            <CardContent>
              <Typography color="textSecondary" gutterBottom>
                Zones
              </Typography>
              <Typography variant="h4">
                {stats.totalZones}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} md={3}>
          <Card>
            <CardContent>
              <Typography color="textSecondary" gutterBottom>
                Woredas
              </Typography>
              <Typography variant="h4">
                {stats.totalWoredas}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} md={3}>
          <Card>
            <CardContent>
              <Typography color="textSecondary" gutterBottom>
                Kebeles
              </Typography>
              <Typography variant="h4">
                {stats.totalKebeles}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Hierarchical Display */}
      <Box sx={{ mb: 3 }}>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => handleCreate('region')}
          sx={{ mb: 2 }}
        >
          Add Region
        </Button>

        {loading ? (
          <Typography>Loading...</Typography>
        ) : (
          regions.map((region) => (
            <Accordion key={region.id} sx={{ mb: 1 }}>
              <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                  <PublicIcon color="primary" />
                  <Typography variant="h6">{region.name}</Typography>
                  {region.code && <Chip label={region.code} size="small" />}
                  <Chip
                    label={region.is_active ? 'Active' : 'Inactive'}
                    size="small"
                    color={region.is_active ? 'success' : 'error'}
                  />
                  <Button
                    size="small"
                    onClick={(e) => {
                      e.stopPropagation();
                      handleEdit(region, 'region');
                    }}
                  >
                    Edit
                  </Button>
                  <Button
                    size="small"
                    color="error"
                    onClick={(e) => {
                      e.stopPropagation();
                      handleDelete(region, 'region', 'regions');
                    }}
                  >
                    Delete
                  </Button>
                </Box>
              </AccordionSummary>
              <AccordionDetails>
                <Box sx={{ pl: 4 }}>
                  <Button
                    size="small"
                    startIcon={<AddIcon />}
                    onClick={() => handleCreate('zone', region.id)}
                    sx={{ mb: 2 }}
                  >
                    Add Zone
                  </Button>

                  {region.zones?.map((zone) => (
                    <Accordion key={zone.id} sx={{ mb: 1 }}>
                      <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                          <MapIcon color="secondary" />
                          <Typography>{zone.name}</Typography>
                          {zone.code && <Chip label={zone.code} size="small" />}
                          <Chip
                            label={zone.is_active ? 'Active' : 'Inactive'}
                            size="small"
                            color={zone.is_active ? 'success' : 'error'}
                          />
                          <Button
                            size="small"
                            onClick={(e) => {
                              e.stopPropagation();
                              handleEdit(zone, 'zone');
                            }}
                          >
                            Edit
                          </Button>
                          <Button
                            size="small"
                            color="error"
                            onClick={(e) => {
                              e.stopPropagation();
                              handleDelete(zone, 'zone', 'zones');
                            }}
                          >
                            Delete
                          </Button>
                        </Box>
                      </AccordionSummary>
                      <AccordionDetails>
                        <Box sx={{ pl: 4 }}>
                          <Button
                            size="small"
                            startIcon={<AddIcon />}
                            onClick={() => handleCreate('woreda', zone.id)}
                            sx={{ mb: 2 }}
                          >
                            Add Woreda
                          </Button>

                          {zone.woredas?.map((woreda) => (
                            <Accordion key={woreda.id} sx={{ mb: 1 }}>
                              <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                  <LocationOn color="action" />
                                  <Typography>{woreda.name}</Typography>
                                  {woreda.code && <Chip label={woreda.code} size="small" />}
                                  <Chip
                                    label={woreda.is_active ? 'Active' : 'Inactive'}
                                    size="small"
                                    color={woreda.is_active ? 'success' : 'error'}
                                  />
                                  <Button
                                    size="small"
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      handleEdit(woreda, 'woreda');
                                    }}
                                  >
                                    Edit
                                  </Button>
                                  <Button
                                    size="small"
                                    color="error"
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      handleDelete(woreda, 'woreda', 'woredas');
                                    }}
                                  >
                                    Delete
                                  </Button>
                                </Box>
                              </AccordionSummary>
                              <AccordionDetails>
                                <Box sx={{ pl: 4 }}>
                                  <Button
                                    size="small"
                                    startIcon={<AddIcon />}
                                    onClick={() => handleCreate('kebele', woreda.id)}
                                    sx={{ mb: 2 }}
                                  >
                                    Add Kebele
                                  </Button>

                                  {woreda.kebeles?.map((kebele) => (
                                    <Box key={kebele.id} sx={{ display: 'flex', alignItems: 'center', gap: 2, mb: 1 }}>
                                      <HomeIcon color="disabled" />
                                      <Typography>{kebele.name}</Typography>
                                      {kebele.code && <Chip label={kebele.code} size="small" />}
                                      <Chip
                                        label={kebele.is_active ? 'Active' : 'Inactive'}
                                        size="small"
                                        color={kebele.is_active ? 'success' : 'error'}
                                      />
                                      <Button
                                        size="small"
                                        onClick={() => handleEdit(kebele, 'kebele')}
                                      >
                                        Edit
                                      </Button>
                                      <Button
                                        size="small"
                                        color="error"
                                        onClick={() => handleDelete(kebele, 'kebele', 'kebeles')}
                                      >
                                        Delete
                                      </Button>
                                    </Box>
                                  ))}
                                </Box>
                              </AccordionDetails>
                            </Accordion>
                          ))}
                        </Box>
                      </AccordionDetails>
                    </Accordion>
                  ))}
                </Box>
              </AccordionDetails>
            </Accordion>
          ))
        )}
      </Box>

      {/* Form Dialog */}
      <Dialog
        open={formOpen}
        onClose={() => handleFormClose()}
        maxWidth="sm"
        fullWidth
      >
        <DialogTitle>
          {editingItem ? `Edit ${formType}` : `Add New ${formType}`}
        </DialogTitle>
        <DialogContent>
          <AddressEnumForm
            type={formType}
            item={editingItem}
            parentId={parentId}
            onClose={handleFormClose}
          />
        </DialogContent>
      </Dialog>
    </Box>
  );
};

// Form Component
interface AddressEnumFormProps {
  type: 'region' | 'zone' | 'woreda' | 'kebele';
  item?: any;
  parentId?: number | null;
  onClose: (refresh?: boolean) => void;
}

const AddressEnumForm: React.FC<AddressEnumFormProps> = ({ type, item, parentId, onClose }) => {
  const [formData, setFormData] = useState({
    name: item?.name || '',
    code: item?.code || '',
    is_active: item?.is_active ?? true,
    parent_id: parentId || item?.region_id || item?.zone_id || item?.woreda_id || null,
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setErrors({});

    try {
      const endpoint = `${type}s`;
      const data = { ...formData };

      if (type !== 'region') {
        data[`${type.slice(0, -1)}_id`] = formData.parent_id;
        delete data.parent_id;
      }

      if (item) {
        await axios.patch(`/api/${endpoint}/${item.id}`, data);
      } else {
        await axios.post(`/api/${endpoint}`, data);
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
            label="Name"
            value={formData.name}
            onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
            error={!!errors.name}
            helperText={errors.name}
          />
        </Grid>

        <Grid item xs={12}>
          <TextField
            fullWidth
            label="Code"
            value={formData.code}
            onChange={(e) => setFormData(prev => ({ ...prev, code: e.target.value }))}
            error={!!errors.code}
            helperText={errors.code}
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
          {item ? 'Update' : 'Create'}
        </Button>
      </Box>
    </Box>
  );
};

export default AddressEnums;