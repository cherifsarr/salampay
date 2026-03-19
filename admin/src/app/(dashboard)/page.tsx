"use client";

import { useEffect, useState } from "react";
import {
  Users,
  Store,
  CreditCard,
  Wallet,
  TrendingUp,
  ArrowUpRight,
  ArrowDownRight,
} from "lucide-react";
import { StatsCard, StatusBadge, PageLoading } from "@/components";
import { api } from "@/lib/api";
import { formatCurrency, formatDate } from "@/lib/utils";
import type { DashboardStats, Transaction } from "@/types";

// Mock data for demonstration
const mockStats: DashboardStats = {
  total_users: 12453,
  total_merchants: 342,
  total_transactions: 89234,
  total_volume: 1250000000,
  today_transactions: 1234,
  today_volume: 45000000,
  pending_kyc: 23,
  pending_settlements: 8,
};

const mockTransactions: Transaction[] = [
  {
    id: "1",
    reference: "TXN-2024-001234",
    type: "deposit",
    amount: 50000,
    fee: 500,
    net_amount: 49500,
    currency: "XOF",
    status: "completed",
    provider: "Wave",
    customer_name: "Mamadou Diallo",
    created_at: new Date().toISOString(),
  },
  {
    id: "2",
    reference: "TXN-2024-001235",
    type: "payment",
    amount: 25000,
    fee: 250,
    net_amount: 24750,
    currency: "XOF",
    status: "completed",
    provider: "Orange Money",
    customer_name: "Fatou Sow",
    merchant_name: "Boutique Mode",
    created_at: new Date(Date.now() - 3600000).toISOString(),
  },
  {
    id: "3",
    reference: "TXN-2024-001236",
    type: "transfer",
    amount: 100000,
    fee: 500,
    net_amount: 99500,
    currency: "XOF",
    status: "pending",
    provider: "Wave",
    customer_name: "Ibrahima Fall",
    created_at: new Date(Date.now() - 7200000).toISOString(),
  },
  {
    id: "4",
    reference: "TXN-2024-001237",
    type: "withdrawal",
    amount: 75000,
    fee: 750,
    net_amount: 74250,
    currency: "XOF",
    status: "processing",
    provider: "Free Money",
    customer_name: "Aminata Ndiaye",
    created_at: new Date(Date.now() - 10800000).toISOString(),
  },
  {
    id: "5",
    reference: "TXN-2024-001238",
    type: "refund",
    amount: 15000,
    fee: 0,
    net_amount: 15000,
    currency: "XOF",
    status: "completed",
    provider: "Wave",
    customer_name: "Omar Ba",
    merchant_name: "Restaurant Le Ndaar",
    created_at: new Date(Date.now() - 14400000).toISOString(),
  },
];

const typeLabels: Record<string, string> = {
  deposit: "Dépôt",
  withdrawal: "Retrait",
  transfer: "Transfert",
  payment: "Paiement",
  refund: "Remboursement",
  payout: "Versement",
};

export default function DashboardPage() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadData() {
      try {
        // In production, fetch from API
        // const statsRes = await api.getDashboardStats();
        // const txRes = await api.getRecentTransactions();

        // Using mock data for now
        setStats(mockStats);
        setTransactions(mockTransactions);
      } catch (error) {
        console.error("Failed to load dashboard data", error);
      } finally {
        setLoading(false);
      }
    }
    loadData();
  }, []);

  if (loading) {
    return <PageLoading />;
  }

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Tableau de bord</h1>
        <p className="text-gray-500">Vue d&apos;ensemble de votre plateforme</p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatsCard
          title="Clients"
          value={stats?.total_users.toLocaleString() || "0"}
          subtitle={`${stats?.pending_kyc || 0} KYC en attente`}
          icon={Users}
          trend={{ value: 12, positive: true }}
        />
        <StatsCard
          title="Marchands"
          value={stats?.total_merchants.toLocaleString() || "0"}
          icon={Store}
          trend={{ value: 8, positive: true }}
        />
        <StatsCard
          title="Transactions"
          value={stats?.today_transactions.toLocaleString() || "0"}
          subtitle="Aujourd'hui"
          icon={CreditCard}
          trend={{ value: 5, positive: true }}
        />
        <StatsCard
          title="Volume"
          value={formatCurrency(stats?.today_volume || 0)}
          subtitle="Aujourd'hui"
          icon={Wallet}
          trend={{ value: 15, positive: true }}
        />
      </div>

      {/* Quick Stats */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Total Volume Card */}
        <div className="lg:col-span-2 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl p-6 text-white">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-emerald-100">Volume total</p>
              <p className="mt-2 text-4xl font-bold">
                {formatCurrency(stats?.total_volume || 0)}
              </p>
              <p className="mt-2 text-emerald-100">
                {stats?.total_transactions.toLocaleString()} transactions au total
              </p>
            </div>
            <div className="p-4 bg-white/20 rounded-xl">
              <TrendingUp className="h-8 w-8" />
            </div>
          </div>
        </div>

        {/* Pending Actions */}
        <div className="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
          <h3 className="font-semibold text-gray-900 mb-4">Actions en attente</h3>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <span className="text-gray-600">KYC à vérifier</span>
              <span className="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                {stats?.pending_kyc || 0}
              </span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-gray-600">Règlements en attente</span>
              <span className="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                {stats?.pending_settlements || 0}
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* Recent Transactions */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100">
        <div className="p-6 border-b border-gray-100">
          <h3 className="font-semibold text-gray-900">Transactions récentes</h3>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="bg-gray-50">
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Référence
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Type
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Client
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Montant
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Fournisseur
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Statut
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Date
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {transactions.map((tx) => (
                <tr key={tx.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {tx.reference}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                    <div className="flex items-center gap-2">
                      {tx.type === "deposit" || tx.type === "payment" ? (
                        <ArrowDownRight className="h-4 w-4 text-emerald-500" />
                      ) : (
                        <ArrowUpRight className="h-4 w-4 text-red-500" />
                      )}
                      {typeLabels[tx.type] || tx.type}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                    {tx.customer_name}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {formatCurrency(tx.amount)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                    {tx.provider}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge status={tx.status} />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {formatDate(tx.created_at)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
