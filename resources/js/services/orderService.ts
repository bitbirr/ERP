const API_BASE = '/api';

export interface OrderLine {
  id: string;
  order_id: string;
  product_id: string;
  uom: string;
  qty: number;
  price: number;
  discount: number;
  tax_rate?: number;
  tax_amount: number;
  line_total: number;
  notes?: string;
  product?: {
    id: string;
    name: string;
    sku: string;
  };
}

export interface Order {
  id: string;
  order_number: string;
  status: string;
  branch_id: string;
  customer_id?: string;
  currency: string;
  subtotal: number;
  tax_total: number;
  discount_total: number;
  grand_total: number;
  notes?: string;
  created_by: string;
  approved_by?: string;
  approved_at?: string;
  cancelled_by?: string;
  cancelled_at?: string;
  created_at: string;
  updated_at: string;
  lines?: OrderLine[];
  customer?: {
    id: string;
    name: string;
    email: string;
  };
  branch?: {
    id: string;
    name: string;
  };
  creator?: {
    id: string;
    name: string;
  };
  approver?: {
    id: string;
    name: string;
  };
}

export interface CreateOrderData {
  branch_id: string;
  customer_id?: string;
  currency: string;
  notes?: string;
  line_items: {
    product_id: string;
    uom: string;
    qty: number;
    price: number;
    discount?: number;
    tax_rate?: number;
    tax_amount?: number;
    notes?: string;
  }[];
}

export const orderService = {
  // Get all orders with pagination
  getOrders: async (params?: { page?: number; per_page?: number; status?: string; customer_id?: string }) => {
    console.log('OrderService: Making request to:', `${API_BASE}/orders`, 'with params:', params);
    try {
      const response = await window.axios.get(`${API_BASE}/orders`, { params });
      console.log('OrderService: Request successful');
      return response.data;
    } catch (error: any) {
      console.error('OrderService: Request failed:', error.message, error.code);
      throw error;
    }
  },

  // Create a new order
  createOrder: async (orderData: CreateOrderData): Promise<Order> => {
    const response = await window.axios.post(`${API_BASE}/orders`, orderData);
    return response.data;
  },

  // Get a specific order
  getOrder: async (id: string): Promise<Order> => {
    const response = await window.axios.get(`${API_BASE}/orders/${id}`);
    return response.data;
  },

  // Update an order
  updateOrder: async (id: string, orderData: Partial<CreateOrderData>): Promise<Order> => {
    const response = await window.axios.patch(`${API_BASE}/orders/${id}`, orderData);
    return response.data;
  },

  // Approve an order
  approveOrder: async (id: string): Promise<Order> => {
    const response = await window.axios.patch(`${API_BASE}/orders/${id}/approve`);
    return response.data;
  },

  // Cancel an order
  cancelOrder: async (id: string): Promise<Order> => {
    const response = await window.axios.patch(`${API_BASE}/orders/${id}/cancel`);
    return response.data;
  },

  // Delete an order
  deleteOrder: async (id: string): Promise<void> => {
    await window.axios.delete(`${API_BASE}/orders/${id}`);
  },
};