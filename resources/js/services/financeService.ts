import axios from 'axios';

const API_BASE = '/api';

// GL Account interfaces
export interface GlAccount {
  id: string;
  code: string;
  name: string;
  type: 'ASSET' | 'LIABILITY' | 'EQUITY' | 'REVENUE' | 'EXPENSE';
  normal_balance: 'DEBIT' | 'CREDIT';
  parent_id?: string;
  level: number;
  is_postable: boolean;
  status: 'ACTIVE' | 'INACTIVE';
  branch_id?: string;
  created_at: string;
  updated_at: string;
  parent?: GlAccount;
  children?: GlAccount[];
}

export interface AccountTreeNode extends GlAccount {
  children: AccountTreeNode[];
}

export interface AccountBalance {
  account_id: string;
  account_code: string;
  account_name: string;
  balance: number;
  debit_total: number;
  credit_total: number;
  as_of_date: string;
}

// GL Journal interfaces
export interface GlJournal {
  id: string;
  journal_no: string;
  journal_date: string;
  currency: string;
  fx_rate: number;
  source: string;
  reference: string;
  memo: string;
  status: 'DRAFT' | 'POSTED' | 'VOIDED' | 'REVERSED';
  posted_at?: string;
  posted_by?: string;
  branch_id?: string;
  external_ref?: string;
  created_at: string;
  updated_at: string;
  lines?: GlLine[];
  branch?: {
    id: string;
    name: string;
  };
  postedBy?: {
    id: string;
    name: string;
  };
}

export interface GlLine {
  id: string;
  journal_id: string;
  line_no: number;
  account_id: string;
  branch_id?: string;
  cost_center_id?: string;
  project_id?: string;
  customer_id?: string;
  supplier_id?: string;
  item_id?: string;
  memo: string;
  debit: number;
  credit: number;
  meta?: Record<string, any>;
  created_at: string;
  updated_at: string;
  account?: GlAccount;
  branch?: {
    id: string;
    name: string;
  };
}

export interface CreateJournalData {
  journal_date: string;
  currency: string;
  fx_rate?: number;
  source: string;
  reference: string;
  memo: string;
  branch_id?: string;
  external_ref?: string;
  lines: CreateJournalLineData[];
}

export interface CreateJournalLineData {
  account_id: string;
  branch_id?: string;
  cost_center_id?: string;
  project_id?: string;
  customer_id?: string;
  supplier_id?: string;
  item_id?: string;
  memo: string;
  debit: number;
  credit: number;
  meta?: Record<string, any>;
}

export interface JournalFilters {
  status?: string;
  source?: string;
  branch_id?: string;
  start_date?: string;
  end_date?: string;
  page?: number;
  per_page?: number;
}

export const financeService = {
  // Account operations
  getAccounts: async (params?: { branch_id?: string; type?: string; status?: string }): Promise<{ data: GlAccount[]; meta: any }> => {
    console.log('FinanceService: Making request to:', `${API_BASE}/gl/accounts`, 'with params:', params);
    try {
      const response = await axios.get(`${API_BASE}/gl/accounts`, { params });
      console.log('FinanceService: Request successful');
      return response.data;
    } catch (error: any) {
      console.error('FinanceService: Request failed:', error.message, error.code);
      throw error;
    }
  },

  getAccountTree: async (params?: { branch_id?: string }): Promise<AccountTreeNode[]> => {
    const response = await axios.get(`${API_BASE}/gl/accounts/tree`, { params });
    return response.data;
  },

  getAccount: async (accountId: string): Promise<GlAccount> => {
    const response = await axios.get(`${API_BASE}/gl/accounts/${accountId}`);
    return response.data;
  },

  getAccountBalance: async (accountId: string, params?: { as_of_date?: string }): Promise<AccountBalance> => {
    const response = await axios.get(`${API_BASE}/gl/accounts/${accountId}/balance`, { params });
    return response.data;
  },

  // Journal operations
  getJournals: async (filters?: JournalFilters): Promise<{ data: GlJournal[]; meta: any }> => {
    const response = await axios.get(`${API_BASE}/gl/journals`, { params: filters });
    return response.data;
  },

  createJournal: async (journalData: CreateJournalData): Promise<GlJournal> => {
    const response = await axios.post(`${API_BASE}/gl/journals`, journalData);
    return response.data;
  },

  getJournal: async (journalId: string): Promise<GlJournal> => {
    const response = await axios.get(`${API_BASE}/gl/journals/${journalId}`);
    return response.data;
  },

  updateJournal: async (journalId: string, journalData: Partial<CreateJournalData>): Promise<GlJournal> => {
    const response = await axios.patch(`${API_BASE}/gl/journals/${journalId}`, journalData);
    return response.data;
  },

  postJournal: async (journalId: string): Promise<GlJournal> => {
    const response = await axios.post(`${API_BASE}/gl/journals/${journalId}/post`);
    return response.data;
  },

  reverseJournal: async (journalId: string, reason?: string): Promise<GlJournal> => {
    const response = await axios.post(`${API_BASE}/gl/journals/${journalId}/reverse`, { reason });
    return response.data;
  },

  voidJournal: async (journalId: string, reason?: string): Promise<GlJournal> => {
    const response = await axios.post(`${API_BASE}/gl/journals/${journalId}/void`, { reason });
    return response.data;
  },

  validateJournal: async (journalId: string): Promise<{ valid: boolean; errors: string[] }> => {
    const response = await axios.post(`${API_BASE}/gl/journals/${journalId}/validate`);
    return response.data;
  },

  deleteJournal: async (journalId: string): Promise<void> => {
    await axios.delete(`${API_BASE}/gl/journals/${journalId}`);
  },
};