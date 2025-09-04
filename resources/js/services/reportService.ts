import axios from 'axios';

export interface SummaryData {
  total_users: number;
  total_customers: number;
  active_customers: number;
  total_orders: number;
  total_revenue: number;
  total_products: number;
  active_products: number;
  total_inventory_value: number;
  low_stock_items: number;
  total_stock_movements: number;
}

export interface OrderSummary {
  status: string;
  count: number;
  total: number;
}

export interface RevenueData {
  date: string;
  revenue: number;
}

export interface TopSellingProduct {
  name: string;
  total_quantity: number;
  total_revenue: number;
}

export interface LowStockItem {
  id: string;
  product: {
    name: string;
  };
  branch: {
    name: string;
  };
  on_hand: number;
}

export interface RecentOrder {
  id: string;
  order_number: string;
  status: string;
  grand_total: number;
  created_at: string;
  customer: {
    name: string;
  };
  creator: {
    name: string;
  };
}

export interface DashboardData {
  summary: SummaryData;
  orders_summary: OrderSummary[];
  revenue_over_time: RevenueData[];
  top_selling_products: TopSellingProduct[];
  low_stock_items: LowStockItem[];
  recent_orders: RecentOrder[];
}

class ReportService {
  private baseUrl = '/api';

  async getDashboard(): Promise<DashboardData> {
    const response = await axios.get(`${this.baseUrl}/reports/dashboard`);
    return response.data;
  }

  async getSummary(): Promise<SummaryData> {
    const response = await axios.get(`${this.baseUrl}/reports/summary`);
    return response.data;
  }

  async getOrdersSummary(): Promise<OrderSummary[]> {
    const response = await axios.get(`${this.baseUrl}/reports/orders-summary`);
    return response.data;
  }

  async getRevenueOverTime(): Promise<RevenueData[]> {
    const response = await axios.get(`${this.baseUrl}/reports/revenue-over-time`);
    return response.data;
  }

  async getTopSellingProducts(limit: number = 10): Promise<TopSellingProduct[]> {
    const response = await axios.get(`${this.baseUrl}/reports/top-selling-products`, {
      params: { limit }
    });
    return response.data;
  }

  async getLowStockItems(): Promise<LowStockItem[]> {
    const response = await axios.get(`${this.baseUrl}/reports/low-stock-items`);
    return response.data;
  }

  async getRecentOrders(limit: number = 10): Promise<RecentOrder[]> {
    const response = await axios.get(`${this.baseUrl}/reports/recent-orders`, {
      params: { limit }
    });
    return response.data;
  }
}

export default new ReportService();