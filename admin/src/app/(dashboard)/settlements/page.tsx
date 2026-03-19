"use client";

import { useEffect, useState } from "react";
import {
  Search,
  Eye,
  CheckCircle,
  XCircle,
  Clock,
  Wallet,
  TrendingUp,
} from "lucide-react";
import { StatusBadge, Pagination, PageLoading, Button } from "@/components";
import { formatCurrency, formatDate } from "@/lib/utils";
import type { Settlement } from "@/types";

// Mock data
const mockSettlements: Settlement[] = [
  {
    id: "1",
    merchant_id: "1",
    merchant_name: "Boutique Mode Dakar",
    amount: 500000,
    fee: 2500,
    net_amount: 497500,
    status: "pending",
    bank_name: "CBAO",
    account_number: "****4567",
    created_at: new Date().toISOString(),
  },
  {
    id: "2",
    merchant_id: "2",
    merchant_name: "Restaurant Le Ndaar",
    amount: 250000,
    fee: 1250,
    net_amount: 248750,
    status: "processing",
    bank_name: "BICIS",
    account_number: "****8901",
    created_at: new Date(Date.now() - 3600000).toISOString(),
  },
  {
    id: "3",
    merchant_id: "3",
    merchant_name: "Tech Solutions SN",
    amount: 1000000,
    fee: 5000,
    net_amount: 995000,
    status: "completed",
    bank_name: "Société Générale",
    account_number: "****2345",
    created_at: new Date(Date.now() - 86400000).toISOString(),
    completed_at: new Date(Date.now() - 43200000).toISOString(),
  },
  {
    id: "4",
    merchant_id: "4",
    merchant_name: "Pharmacie Centrale",
    amount: 750000,
    fee: 3750,
    net_amount: 746250,
    status: "completed",
    bank_name: "BOA",
    account_number: "****6789",
    created_at: new Date(Date.now() - 172800000).toISOString(),
    completed_at: new Date(Date.now() - 129600000).toISOString(),
  },
  {
    id: "5",
    merchant_id: "5",
    merchant_name: "Transport Express",
    amount: 300000,
    fee: 1500,
    net_amount: 298500,
    status: "failed",
    bank_name: "Ecobank",
    account_number: "****0123",
    created_at: new Date(Date.now() - 259200000).toISOString(),
  },
];

export default function SettlementsPage() {
  const [settlements, setSettlements] = useState<Settlement[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages] = useState(5);

  useEffect(() => {
    setTimeout(() => {
      setSettlements(mockSettlements);
      setLoading(false);
    }, 500);
  }, []);

  const filteredSettlements = settlements.filter((s) => {
    const matchesSearch = s.merchant_name
      .toLowerCase()
      .includes(search.toLowerCase());
    const matchesStatus = !statusFilter || s.status === statusFilter;
    return matchesSearch && matchesStatus;
  });

  const pendingCount = settlements.filter((s) => s.status === "pending").length;
  const processingCount = settlements.filter(
    (s) => s.status === "processing"
  ).length;
  const totalPending = settlements
    .filter((s) => s.status === "pending" || s.status === "processing")
    .reduce((sum, s) => sum + s.net_amount, 0);

  if (loading) {
    return <PageLoading />;
  }

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Règlements</h1>
          <p className="text-gray-500">Gestion des versements marchands</p>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-yellow-50 rounded-lg">
              <Clock className="h-5 w-5 text-yellow-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">{pendingCount}</p>
              <p className="text-sm text-gray-500">En attente</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-blue-50 rounded-lg">
              <TrendingUp className="h-5 w-5 text-blue-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">
                {processingCount}
              </p>
              <p className="text-sm text-gray-500">En cours</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-emerald-50 rounded-lg">
              <Wallet className="h-5 w-5 text-emerald-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">
                {formatCurrency(totalPending)}
              </p>
              <p className="text-sm text-gray-500">À verser</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-purple-50 rounded-lg">
              <CheckCircle className="h-5 w-5 text-purple-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">
                {formatCurrency(125000000)}
              </p>
              <p className="text-sm text-gray-500">Versé ce mois</p>
            </div>
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div className="flex flex-col sm:flex-row gap-4">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              type="text"
              placeholder="Rechercher par marchand..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
            />
          </div>
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="">Tous les statuts</option>
            <option value="pending">En attente</option>
            <option value="processing">En cours</option>
            <option value="completed">Terminé</option>
            <option value="failed">Échoué</option>
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
                  Marchand
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Montant
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Frais
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Net
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Banque
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
              {filteredSettlements.map((settlement) => (
                <tr key={settlement.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className="text-sm font-medium text-gray-900">
                      {settlement.merchant_name}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {formatCurrency(settlement.amount)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {formatCurrency(settlement.fee)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {formatCurrency(settlement.net_amount)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm">
                      <p className="text-gray-900">{settlement.bank_name}</p>
                      <p className="text-gray-500 text-xs">
                        {settlement.account_number}
                      </p>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge status={settlement.status} />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm">
                      <p className="text-gray-900">
                        {formatDate(settlement.created_at)}
                      </p>
                      {settlement.completed_at && (
                        <p className="text-gray-500 text-xs">
                          Versé: {formatDate(settlement.completed_at)}
                        </p>
                      )}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
                        title="Voir détails"
                      >
                        <Eye className="h-4 w-4" />
                      </button>
                      {settlement.status === "pending" && (
                        <>
                          <button
                            className="p-2 text-emerald-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg"
                            title="Approuver"
                          >
                            <CheckCircle className="h-4 w-4" />
                          </button>
                          <button
                            className="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                            title="Rejeter"
                          >
                            <XCircle className="h-4 w-4" />
                          </button>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
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
