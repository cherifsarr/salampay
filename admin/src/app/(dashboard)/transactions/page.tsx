"use client";

import { useEffect, useState } from "react";
import {
  Search,
  Eye,
  RefreshCw,
  ArrowUpRight,
  ArrowDownRight,
  Download,
  Filter,
} from "lucide-react";
import { StatusBadge, Pagination, PageLoading, Button } from "@/components";
import { formatCurrency, formatDate } from "@/lib/utils";
import type { Transaction } from "@/types";

// Mock data
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
    customer_id: "1",
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
    customer_id: "2",
    merchant_name: "Boutique Mode",
    merchant_id: "1",
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
    customer_id: "3",
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
    customer_id: "4",
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
    customer_id: "5",
    merchant_name: "Restaurant Le Ndaar",
    merchant_id: "2",
    created_at: new Date(Date.now() - 14400000).toISOString(),
  },
  {
    id: "6",
    reference: "TXN-2024-001239",
    type: "payout",
    amount: 500000,
    fee: 2500,
    net_amount: 497500,
    currency: "XOF",
    status: "completed",
    provider: "Wave",
    merchant_name: "Boutique Mode",
    merchant_id: "1",
    created_at: new Date(Date.now() - 18000000).toISOString(),
  },
  {
    id: "7",
    reference: "TXN-2024-001240",
    type: "deposit",
    amount: 200000,
    fee: 2000,
    net_amount: 198000,
    currency: "XOF",
    status: "failed",
    provider: "Orange Money",
    customer_name: "Cheikh Diop",
    customer_id: "6",
    description: "Timeout from provider",
    created_at: new Date(Date.now() - 21600000).toISOString(),
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

const typeIcons: Record<string, { icon: typeof ArrowUpRight; color: string }> = {
  deposit: { icon: ArrowDownRight, color: "text-emerald-500" },
  payment: { icon: ArrowDownRight, color: "text-emerald-500" },
  withdrawal: { icon: ArrowUpRight, color: "text-red-500" },
  transfer: { icon: ArrowUpRight, color: "text-blue-500" },
  refund: { icon: ArrowUpRight, color: "text-purple-500" },
  payout: { icon: ArrowUpRight, color: "text-orange-500" },
};

export default function TransactionsPage() {
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [typeFilter, setTypeFilter] = useState("");
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages] = useState(10);

  useEffect(() => {
    setTimeout(() => {
      setTransactions(mockTransactions);
      setLoading(false);
    }, 500);
  }, []);

  const filteredTransactions = transactions.filter((tx) => {
    const matchesSearch =
      tx.reference.toLowerCase().includes(search.toLowerCase()) ||
      tx.customer_name?.toLowerCase().includes(search.toLowerCase()) ||
      tx.merchant_name?.toLowerCase().includes(search.toLowerCase());
    const matchesStatus = !statusFilter || tx.status === statusFilter;
    const matchesType = !typeFilter || tx.type === typeFilter;
    return matchesSearch && matchesStatus && matchesType;
  });

  if (loading) {
    return <PageLoading />;
  }

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Transactions</h1>
          <p className="text-gray-500">Historique de toutes les transactions</p>
        </div>
        <Button variant="outline">
          <Download className="h-4 w-4 mr-2" />
          Exporter
        </Button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <p className="text-sm text-gray-500">Aujourd&apos;hui</p>
          <p className="text-2xl font-bold text-gray-900 mt-1">1,234</p>
          <p className="text-xs text-emerald-600 mt-1">+12% vs hier</p>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <p className="text-sm text-gray-500">Volume aujourd&apos;hui</p>
          <p className="text-2xl font-bold text-gray-900 mt-1">
            {formatCurrency(45000000)}
          </p>
          <p className="text-xs text-emerald-600 mt-1">+8% vs hier</p>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <p className="text-sm text-gray-500">En attente</p>
          <p className="text-2xl font-bold text-yellow-600 mt-1">23</p>
          <p className="text-xs text-gray-500 mt-1">À traiter</p>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <p className="text-sm text-gray-500">Échouées</p>
          <p className="text-2xl font-bold text-red-600 mt-1">5</p>
          <p className="text-xs text-gray-500 mt-1">Dernières 24h</p>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              type="text"
              placeholder="Rechercher par référence, client ou marchand..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
            />
          </div>
          <select
            value={typeFilter}
            onChange={(e) => setTypeFilter(e.target.value)}
            className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="">Tous les types</option>
            <option value="deposit">Dépôt</option>
            <option value="withdrawal">Retrait</option>
            <option value="transfer">Transfert</option>
            <option value="payment">Paiement</option>
            <option value="refund">Remboursement</option>
            <option value="payout">Versement</option>
          </select>
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="">Tous les statuts</option>
            <option value="completed">Terminé</option>
            <option value="pending">En attente</option>
            <option value="processing">En cours</option>
            <option value="failed">Échoué</option>
            <option value="refunded">Remboursé</option>
          </select>
          <select className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="">Tous les fournisseurs</option>
            <option value="wave">Wave</option>
            <option value="orange">Orange Money</option>
            <option value="free">Free Money</option>
            <option value="wizall">Wizall</option>
            <option value="emoney">E-Money</option>
          </select>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
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
                  Parties
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Montant
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Frais
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
                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {filteredTransactions.map((tx) => {
                const typeConfig = typeIcons[tx.type] || typeIcons.deposit;
                const TypeIcon = typeConfig.icon;

                return (
                  <tr key={tx.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className="text-sm font-medium text-gray-900">
                        {tx.reference}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center gap-2">
                        <TypeIcon className={`h-4 w-4 ${typeConfig.color}`} />
                        <span className="text-sm text-gray-600">
                          {typeLabels[tx.type] || tx.type}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm">
                        {tx.customer_name && (
                          <p className="text-gray-900">{tx.customer_name}</p>
                        )}
                        {tx.merchant_name && (
                          <p className="text-gray-500 text-xs">
                            → {tx.merchant_name}
                          </p>
                        )}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {formatCurrency(tx.amount)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {formatCurrency(tx.fee)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className="px-2 py-1 bg-gray-100 rounded text-xs font-medium text-gray-600">
                        {tx.provider}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <StatusBadge status={tx.status} />
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {formatDate(tx.created_at)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right">
                      <div className="flex items-center justify-end gap-2">
                        <button
                          className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
                          title="Voir détails"
                        >
                          <Eye className="h-4 w-4" />
                        </button>
                        {tx.status === "completed" && tx.type !== "refund" && (
                          <button
                            className="p-2 text-purple-400 hover:text-purple-600 hover:bg-purple-50 rounded-lg"
                            title="Rembourser"
                          >
                            <RefreshCw className="h-4 w-4" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div className="px-6 py-4 border-t border-gray-100">
          <Pagination
            currentPage={currentPage}
            totalPages={totalPages}
            onPageChange={setCurrentPage}
          />
        </div>
      </div>
    </div>
  );
}
