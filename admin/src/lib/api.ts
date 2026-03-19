import axios, { AxiosInstance, AxiosError } from "axios";
import Cookies from "js-cookie";
import type {
  ApiResponse,
  PaginatedResponse,
  User,
  Customer,
  Merchant,
  Transaction,
  Settlement,
  Provider,
  DashboardStats,
  ChartData,
} from "@/types";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/admin";

class ApiClient {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: API_URL,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
    });

    this.client.interceptors.request.use((config) => {
      const token = Cookies.get("admin_token");
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    });

    this.client.interceptors.response.use(
      (response) => response,
      (error: AxiosError) => {
        if (error.response?.status === 401) {
          Cookies.remove("admin_token");
          if (typeof window !== "undefined") {
            window.location.href = "/login";
          }
        }
        return Promise.reject(error);
      }
    );
  }

  // Auth
  async login(email: string, password: string): Promise<ApiResponse<{ token: string; user: User }>> {
    const response = await this.client.post("/auth/login", { email, password });
    return response.data;
  }

  async logout(): Promise<void> {
    await this.client.post("/auth/logout");
    Cookies.remove("admin_token");
  }

  async getProfile(): Promise<ApiResponse<User>> {
    const response = await this.client.get("/auth/profile");
    return response.data;
  }

  // Dashboard
  async getDashboardStats(): Promise<ApiResponse<DashboardStats>> {
    const response = await this.client.get("/dashboard/stats");
    return response.data;
  }

  async getChartData(period: string = "7d"): Promise<ApiResponse<ChartData[]>> {
    const response = await this.client.get(`/dashboard/chart?period=${period}`);
    return response.data;
  }

  async getRecentTransactions(): Promise<ApiResponse<Transaction[]>> {
    const response = await this.client.get("/dashboard/recent-transactions");
    return response.data;
  }

  // Customers
  async getCustomers(page: number = 1, search?: string): Promise<PaginatedResponse<Customer>> {
    const params = new URLSearchParams({ page: String(page) });
    if (search) params.append("search", search);
    const response = await this.client.get(`/customers?${params}`);
    return response.data;
  }

  async getCustomer(id: string): Promise<ApiResponse<Customer>> {
    const response = await this.client.get(`/customers/${id}`);
    return response.data;
  }

  async updateCustomerStatus(id: string, status: string): Promise<ApiResponse<Customer>> {
    const response = await this.client.patch(`/customers/${id}/status`, { status });
    return response.data;
  }

  async verifyCustomerKyc(id: string, approved: boolean, reason?: string): Promise<ApiResponse<Customer>> {
    const response = await this.client.post(`/customers/${id}/kyc-verify`, { approved, reason });
    return response.data;
  }

  // Merchants
  async getMerchants(page: number = 1, search?: string): Promise<PaginatedResponse<Merchant>> {
    const params = new URLSearchParams({ page: String(page) });
    if (search) params.append("search", search);
    const response = await this.client.get(`/merchants?${params}`);
    return response.data;
  }

  async getMerchant(id: string): Promise<ApiResponse<Merchant>> {
    const response = await this.client.get(`/merchants/${id}`);
    return response.data;
  }

  async updateMerchantStatus(id: string, status: string): Promise<ApiResponse<Merchant>> {
    const response = await this.client.patch(`/merchants/${id}/status`, { status });
    return response.data;
  }

  async verifyMerchantKyb(id: string, approved: boolean, reason?: string): Promise<ApiResponse<Merchant>> {
    const response = await this.client.post(`/merchants/${id}/kyb-verify`, { approved, reason });
    return response.data;
  }

  // Transactions
  async getTransactions(
    page: number = 1,
    filters?: { status?: string; type?: string; search?: string }
  ): Promise<PaginatedResponse<Transaction>> {
    const params = new URLSearchParams({ page: String(page) });
    if (filters?.status) params.append("status", filters.status);
    if (filters?.type) params.append("type", filters.type);
    if (filters?.search) params.append("search", filters.search);
    const response = await this.client.get(`/transactions?${params}`);
    return response.data;
  }

  async getTransaction(id: string): Promise<ApiResponse<Transaction>> {
    const response = await this.client.get(`/transactions/${id}`);
    return response.data;
  }

  async refundTransaction(id: string, reason: string): Promise<ApiResponse<Transaction>> {
    const response = await this.client.post(`/transactions/${id}/refund`, { reason });
    return response.data;
  }

  // Settlements
  async getSettlements(page: number = 1, status?: string): Promise<PaginatedResponse<Settlement>> {
    const params = new URLSearchParams({ page: String(page) });
    if (status) params.append("status", status);
    const response = await this.client.get(`/settlements?${params}`);
    return response.data;
  }

  async processSettlement(id: string): Promise<ApiResponse<Settlement>> {
    const response = await this.client.post(`/settlements/${id}/process`);
    return response.data;
  }

  async rejectSettlement(id: string, reason: string): Promise<ApiResponse<Settlement>> {
    const response = await this.client.post(`/settlements/${id}/reject`, { reason });
    return response.data;
  }

  // Providers
  async getProviders(): Promise<ApiResponse<Provider[]>> {
    const response = await this.client.get("/providers");
    return response.data;
  }

  async updateProviderStatus(id: string, status: string): Promise<ApiResponse<Provider>> {
    const response = await this.client.patch(`/providers/${id}/status`, { status });
    return response.data;
  }

  async getProviderBalance(id: string): Promise<ApiResponse<{ balance: number; currency: string }>> {
    const response = await this.client.get(`/providers/${id}/balance`);
    return response.data;
  }
}

export const api = new ApiClient();
