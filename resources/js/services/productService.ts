import axios from 'axios';

export interface Product {
  id: string;
  category_id?: string;
  code: string;
  name: string;
  type: 'YIMULU' | 'SERVICE' | 'OTHER';
  uom: string;
  price?: number;
  cost?: number;
  discount_percent?: number;
  pricing_strategy?: 'FIXED' | 'PERCENTAGE' | 'MARGIN';
  is_active: boolean;
  meta?: any;
  category?: ProductCategory;
  created_at: string;
  updated_at: string;
}

export interface ProductCategory {
  id: string;
  name: string;
  description?: string;
  product_count: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface ProductFilters {
  category_id?: string;
  type?: string;
  is_active?: boolean;
  q?: string;
}

export interface CategoryFilters {
  is_active?: boolean;
  q?: string;
}

class ProductService {
  private baseUrl = '/api';

  // Product methods
  async getProducts(filters?: ProductFilters, page = 1, perPage = 50) {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: perPage.toString(),
      ...filters
    });

    const response = await axios.get(`${this.baseUrl}/products?${params}`);
    return response.data;
  }

  async getProduct(id: string): Promise<Product> {
    const response = await axios.get(`${this.baseUrl}/products/${id}`);
    return response.data;
  }

  async createProduct(product: Partial<Product>): Promise<Product> {
    const response = await axios.post(`${this.baseUrl}/products`, product);
    return response.data;
  }

  async updateProduct(id: string, product: Partial<Product>): Promise<Product> {
    const response = await axios.put(`${this.baseUrl}/products/${id}`, product);
    return response.data;
  }

  async deleteProduct(id: string): Promise<void> {
    await axios.delete(`${this.baseUrl}/products/${id}`);
  }

  // Category methods
  async getCategories(filters?: CategoryFilters, page = 1, perPage = 50) {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: perPage.toString(),
      ...filters
    });

    const response = await axios.get(`${this.baseUrl}/product-categories?${params}`);
    return response.data;
  }

  async getCategory(id: string): Promise<ProductCategory> {
    const response = await axios.get(`${this.baseUrl}/product-categories/${id}`);
    return response.data;
  }

  async createCategory(category: Partial<ProductCategory>): Promise<ProductCategory> {
    const response = await axios.post(`${this.baseUrl}/product-categories`, category);
    return response.data;
  }

  async updateCategory(id: string, category: Partial<ProductCategory>): Promise<ProductCategory> {
    const response = await axios.put(`${this.baseUrl}/product-categories/${id}`, category);
    return response.data;
  }

  async deleteCategory(id: string): Promise<void> {
    await axios.delete(`${this.baseUrl}/product-categories/${id}`);
  }
}

export const productService = new ProductService();