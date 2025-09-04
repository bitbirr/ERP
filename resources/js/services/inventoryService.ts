import axios from 'axios';

const API_BASE = 'https://localhost:8000/api';

export interface InventoryItem {
  id: string;
  product_id: string;
  branch_id: string;
  on_hand: number;
  reserved: number;
  available: number;
  product: {
    id: string;
    name: string;
    code: string;
    type: string;
    uom: string;
  };
  branch: {
    id: string;
    name: string;
  };
  created_at: string;
  updated_at: string;
}

export interface StockMovement {
  id: string;
  product_id: string;
  branch_id: string;
  qty: number;
  type: string;
  ref?: string;
  created_at: string;
  product: {
    name: string;
    code: string;
  };
  branch: {
    name: string;
  };
}

export interface InventoryFilters {
  product_id?: string;
  branch_id?: string;
  has_stock?: boolean;
  page?: number;
  per_page?: number;
}

export interface StockMovementFilters {
  product_id?: string;
  branch_id?: string;
  type?: string;
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

export interface StockOperationData {
  product_id: string;
  branch_id: string;
  qty: number;
  ref?: string;
  context?: Record<string, any>;
}

export interface TransferData {
  product_id: string;
  from_branch_id: string;
  to_branch_id: string;
  qty: number;
  ref?: string;
  context?: Record<string, any>;
}

export interface BulkReceiveData {
  branch_id: string;
  items: Array<{
    product_id: string;
    qty: number;
    ref?: string;
  }>;
  context?: Record<string, any>;
}

export const inventoryService = {
  // Get inventory items with filters
  getInventory: async (filters: InventoryFilters = {}) => {
    const response = await axios.get(`${API_BASE}/inventory`, { params: filters });
    return response.data;
  },

  // Get specific inventory item
  getInventoryItem: async (branchId: string, productId: string) => {
    const response = await axios.get(`${API_BASE}/inventory/${branchId}/${productId}`);
    return response.data;
  },

  // Set opening balance
  setOpeningBalance: async (data: StockOperationData) => {
    const response = await axios.post(`${API_BASE}/inventory/opening`, data);
    return response.data;
  },

  // Receive stock
  receiveStock: async (data: StockOperationData) => {
    const response = await axios.post(`${API_BASE}/inventory/receive`, data);
    return response.data;
  },

  // Issue stock
  issueStock: async (data: StockOperationData) => {
    const response = await axios.post(`${API_BASE}/inventory/issue`, data);
    return response.data;
  },

  // Reserve stock
  reserveStock: async (data: StockOperationData) => {
    const response = await axios.post(`${API_BASE}/inventory/reserve`, data);
    return response.data;
  },

  // Unreserve stock
  unreserveStock: async (data: StockOperationData) => {
    const response = await axios.post(`${API_BASE}/inventory/unreserve`, data);
    return response.data;
  },

  // Transfer stock between branches
  transferStock: async (data: TransferData) => {
    const response = await axios.post(`${API_BASE}/inventory/transfer`, data);
    return response.data;
  },

  // Adjust stock levels
  adjustStock: async (data: StockOperationData & { reason?: string }) => {
    const response = await axios.post(`${API_BASE}/inventory/adjust`, data);
    return response.data;
  },

  // Bulk receive
  bulkReceive: async (data: BulkReceiveData) => {
    const response = await axios.post(`${API_BASE}/inventory/receive/bulk`, data);
    return response.data;
  },

  // Bulk reserve
  bulkReserve: async (data: BulkReceiveData) => {
    const response = await axios.post(`${API_BASE}/inventory/reserve/bulk`, data);
    return response.data;
  },

  // Get stock movements
  getStockMovements: async (filters: StockMovementFilters = {}) => {
    const response = await axios.get(`${API_BASE}/stock-movements`, { params: filters });
    return response.data;
  },

  // Get stock on hand report
  getStockOnHand: async (filters: { branch?: string; type?: string } = {}) => {
    const response = await axios.get(`${API_BASE}/reports/stock/onhand`, { params: filters });
    return response.data;
  },

  // Get stock movements report
  getStockMovementsReport: async (filters: { from?: string; to?: string; type?: string; branch?: string; per_page?: number } = {}) => {
    const response = await axios.get(`${API_BASE}/reports/stock/movements`, { params: filters });
    return response.data;
  },

  // Get stock valuation report
  getStockValuation: async (filters: { branch?: string; as_of?: string } = {}) => {
    const response = await axios.get(`${API_BASE}/reports/stock/valuation`, { params: filters });
    return response.data;
  },

  // Get reserved backlog report
  getReservedBacklog: async (filters: { branch?: string; older_than?: string } = {}) => {
    const response = await axios.get(`${API_BASE}/reports/stock/reserved-backlog`, { params: filters });
    return response.data;
  },
};