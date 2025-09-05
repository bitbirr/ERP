import axios from 'axios';

const API_BASE = 'http://localhost:8000/api';

export interface TelebirrAgent {
  id: string;
  name: string;
  short_code: string;
  phone: string;
  location?: string;
  status: string;
  notes?: string;
  created_at: string;
  updated_at: string;
  transactions?: TelebirrTransaction[];
}

export interface TelebirrTransaction {
  id: string;
  tx_type: string;
  agent_id?: string;
  bank_account_id?: string;
  amount: number;
  currency: string;
  idempotency_key: string;
  gl_journal_id?: string;
  status: string;
  remarks?: string;
  external_ref?: string;
  created_by?: string;
  approved_by?: string;
  posted_at?: string;
  created_at: string;
  updated_at: string;
  agent?: TelebirrAgent;
  bankAccount?: any;
  glJournal?: any;
  createdBy?: any;
  approvedBy?: any;
}

export interface CreateAgentData {
  name: string;
  short_code: string;
  phone: string;
  location?: string;
  status: string;
  notes?: string;
}

export interface BankAccount {
  id: string;
  name: string;
  external_number: string;
  account_number: string;
  account_type: string;
  balance: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface CreateTransactionData {
  tx_type: string;
  agent_short_code?: string;
  bank_external_number?: string;
  amount: number;
  currency?: string;
  idempotency_key: string;
  remarks?: string;
  external_ref?: string;
}

export const telebirrService = {
  // ===== AGENT MANAGEMENT =====

  // Get all agents
  getAgents: async (params?: { page?: number; per_page?: number; status?: string; search?: string }) => {
    const response = await axios.get(`${API_BASE}/telebirr/agents`, { params });
    return response.data;
  },

  // Create agent
  createAgent: async (agentData: CreateAgentData): Promise<TelebirrAgent> => {
    const response = await axios.post(`${API_BASE}/telebirr/agents`, agentData);
    return response.data.data;
  },

  // Get specific agent
  getAgent: async (id: string): Promise<TelebirrAgent> => {
    const response = await axios.get(`${API_BASE}/telebirr/agents/${id}`);
    return response.data.data;
  },

  // Update agent
  updateAgent: async (id: string, agentData: Partial<CreateAgentData>): Promise<TelebirrAgent> => {
    const response = await axios.patch(`${API_BASE}/telebirr/agents/${id}`, agentData);
    return response.data.data;
  },

  // ===== TRANSACTION MANAGEMENT =====

  // Get all transactions
  getTransactions: async (params?: {
    page?: number;
    per_page?: number;
    tx_type?: string;
    status?: string;
    agent_id?: string;
    date_from?: string;
    date_to?: string;
    search?: string;
  }) => {
    const response = await axios.get(`${API_BASE}/telebirr/transactions`, { params });
    return response.data;
  },

  // Get specific transaction
  getTransaction: async (id: string): Promise<TelebirrTransaction> => {
    const response = await axios.get(`${API_BASE}/telebirr/transactions/${id}`);
    return response.data.data;
  },

  // Post TOPUP transaction
  postTopup: async (data: CreateTransactionData) => {
    const response = await axios.post(`${API_BASE}/telebirr/transactions/topup`, data);
    return response.data;
  },

  // Post ISSUE transaction
  postIssue: async (data: CreateTransactionData): Promise<TelebirrTransaction> => {
    const response = await axios.post(`${API_BASE}/telebirr/transactions/issue`, data);
    return response.data.data;
  },

  // Post REPAY transaction
  postRepay: async (data: CreateTransactionData): Promise<TelebirrTransaction> => {
    const response = await axios.post(`${API_BASE}/telebirr/transactions/repay`, data);
    return response.data.data;
  },

  // Post LOAN transaction
  postLoan: async (data: CreateTransactionData): Promise<TelebirrTransaction> => {
    const response = await axios.post(`${API_BASE}/telebirr/transactions/loan`, data);
    return response.data.data;
  },

  // Void transaction
  voidTransaction: async (id: string): Promise<TelebirrTransaction> => {
    const response = await axios.post(`${API_BASE}/telebirr/transactions/${id}/void`);
    return response.data.data;
  },

  // ===== REPORTING =====

  // Get agent balances
  getAgentBalances: async () => {
    const response = await axios.get(`${API_BASE}/telebirr/reports/agent-balances`);
    return response.data;
  },

  // Get transaction summary
  getTransactionSummary: async (params: { date_from: string; date_to: string }) => {
    const response = await axios.get(`${API_BASE}/telebirr/reports/transaction-summary`, { params });
    return response.data;
  },

  // Get reconciliation data
  getReconciliation: async (params: { date_from: string; date_to: string }) => {
    const response = await axios.get(`${API_BASE}/telebirr/reconciliation`, { params });
    return response.data;
  },

  // Get dashboard data
  getDashboard: async () => {
    const response = await axios.get(`${API_BASE}/telebirr/dashboard`);
    return response.data;
  },

  // Get bank accounts
  getBankAccounts: async () => {
    const response = await axios.get(`${API_BASE}/accounts`);
    return response.data;
  },
};