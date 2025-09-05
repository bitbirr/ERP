import axios from 'axios';

const API_BASE = '/api';

// Bank Account interfaces
export interface BankAccount {
  id: string;
  name: string;
  external_number: string;
  account_number: string;
  gl_account_id: string;
  is_active: boolean;
  account_type: string;
  balance: number;
  branch_id: string;
  customer_id: string;
  created_at: string;
  updated_at: string;
  branch?: {
    id: string;
    name: string;
  };
  customer?: {
    id: string;
    name: string;
  };
  glAccount?: {
    id: string;
    code: string;
    name: string;
  };
  transactions?: TelebirrTransaction[];
}

export interface TelebirrTransaction {
  id: string;
  amount: number;
  tx_type: 'TOPUP' | 'REPAY';
  created_at: string;
}

export interface BankAccountSummary {
  total_accounts: number;
  active_accounts: number;
  total_balance: number;
  recent_transactions: TelebirrTransaction[];
}

export interface BankAccountFilters {
  branch_id?: string;
  account_type?: string;
  status?: 'active' | 'inactive';
  gl_number?: string;
  name?: string;
  balance_min?: number;
  balance_max?: number;
  created_from?: string;
  created_to?: string;
  updated_from?: string;
  updated_to?: string;
  sort?: string;
  direction?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

export interface CreateBankAccountData {
  name: string;
  external_number: string;
  account_number: string;
  gl_account_id: string;
  account_type: string;
  balance?: number;
  branch_id: string;
  customer_id: string;
}

export interface UpdateBankAccountData extends Partial<CreateBankAccountData> {
  is_active?: boolean;
}

export interface BankAccountResponse {
  data: {
    data: BankAccount[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  summary: BankAccountSummary;
}

export const bankAccountService = {
  // Get all bank accounts with filtering and pagination
  getAccounts: async (filters?: BankAccountFilters): Promise<BankAccountResponse> => {
    try {
      const response = await axios.get(`${API_BASE}/accounts`, { params: filters });
      return response.data;
    } catch (error: any) {
      console.error('BankAccountService: Failed to fetch accounts:', error.message);
      throw error;
    }
  },

  // Get single bank account
  getAccount: async (accountId: string): Promise<BankAccount> => {
    try {
      const response = await axios.get(`${API_BASE}/accounts/${accountId}`);
      return response.data;
    } catch (error: any) {
      console.error('BankAccountService: Failed to fetch account:', error.message);
      throw error;
    }
  },

  // Create new bank account
  createAccount: async (accountData: CreateBankAccountData): Promise<BankAccount> => {
    try {
      const response = await axios.post(`${API_BASE}/accounts`, accountData);
      return response.data;
    } catch (error: any) {
      console.error('BankAccountService: Failed to create account:', error.message);
      throw error;
    }
  },

  // Update bank account
  updateAccount: async (accountId: string, accountData: UpdateBankAccountData): Promise<BankAccount> => {
    try {
      const response = await axios.patch(`${API_BASE}/accounts/${accountId}`, accountData);
      return response.data;
    } catch (error: any) {
      console.error('BankAccountService: Failed to update account:', error.message);
      throw error;
    }
  },

  // Delete bank account
  deleteAccount: async (accountId: string): Promise<void> => {
    try {
      await axios.delete(`${API_BASE}/accounts/${accountId}`);
    } catch (error: any) {
      console.error('BankAccountService: Failed to delete account:', error.message);
      throw error;
    }
  },

  // Get account summary
  getAccountSummary: async (): Promise<BankAccountSummary> => {
    try {
      const response = await axios.get(`${API_BASE}/accounts`, { params: { per_page: 1 } });
      return response.data.summary;
    } catch (error: any) {
      console.error('BankAccountService: Failed to fetch summary:', error.message);
      throw error;
    }
  },
};