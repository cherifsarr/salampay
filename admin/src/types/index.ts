// User types
export interface User {
  id: string;
  name: string;
  email: string;
  phone: string;
  role: "admin" | "support" | "finance";
  status: "active" | "inactive";
  created_at: string;
}

export interface Customer {
  id: string;
  name: string;
  phone: string;
  email?: string;
  kyc_status: "pending" | "verified" | "rejected";
  wallet_balance: number;
  status: "active" | "suspended" | "blocked";
  created_at: string;
}

export interface Merchant {
  id: string;
  business_name: string;
  business_type: string;
  owner_name: string;
  email: string;
  phone: string;
  kyb_status: "pending" | "verified" | "rejected";
  wallet_balance: number;
  status: "active" | "suspended" | "blocked";
  api_key_count: number;
  created_at: string;
}

export interface Transaction {
  id: string;
  reference: string;
  type: "deposit" | "withdrawal" | "transfer" | "payment" | "refund" | "payout";
  amount: number;
  fee: number;
  net_amount: number;
  currency: string;
  status: "pending" | "processing" | "completed" | "failed" | "refunded";
  provider: string;
  customer_id?: string;
  customer_name?: string;
  merchant_id?: string;
  merchant_name?: string;
  description?: string;
  created_at: string;
}

export interface Settlement {
  id: string;
  merchant_id: string;
  merchant_name: string;
  amount: number;
  fee: number;
  net_amount: number;
  status: "pending" | "processing" | "completed" | "failed";
  bank_name?: string;
  account_number?: string;
  created_at: string;
  completed_at?: string;
}

export interface Provider {
  id: string;
  name: string;
  code: string;
  type: "mobile_money" | "card" | "bank";
  status: "active" | "inactive" | "maintenance";
  balance?: number;
  transaction_count: number;
  success_rate: number;
}

export interface DashboardStats {
  total_users: number;
  total_merchants: number;
  total_transactions: number;
  total_volume: number;
  today_transactions: number;
  today_volume: number;
  pending_kyc: number;
  pending_settlements: number;
}

export interface ChartData {
  date: string;
  transactions: number;
  volume: number;
}

// API Response types
export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
