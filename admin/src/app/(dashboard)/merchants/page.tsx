"use client";

import { useEffect, useState } from "react";
import { Search, Eye, Ban, CheckCircle, Key, Store } from "lucide-react";
import { StatusBadge, Pagination, PageLoading } from "@/components";
import { formatCurrency, formatDate } from "@/lib/utils";
import type { Merchant } from "@/types";

// Mock data
const mockMerchants: Merchant[] = [
  {
    id: "1",
    business_name: "Boutique Mode Dakar",
    business_type: "Retail",
    owner_name: "Cheikh Diop",
    email: "contact@boutiquemode.sn",
    phone: "+221771234567",
    kyb_status: "verified",
    wallet_balance: 2500000,
    status: "active",
    api_key_count: 2,
    created_at: "2024-01-10T10:30:00Z",
  },
  {
    id: "2",
    business_name: "Restaurant Le Ndaar",
    business_type: "Food & Beverage",
    owner_name: "Awa Diallo",
    email: "info@lendaar.sn",
    phone: "+221772345678",
    kyb_status: "verified",
    wallet_balance: 875000,
    status: "active",
    api_key_count: 1,
    created_at: "2024-01-25T14:45:00Z",
  },
  {
    id: "3",
    business_name: "Tech Solutions SN",
    business_type: "Technology",
    owner_name: "Moussa Sarr",
    email: "hello@techsolutions.sn",
    phone: "+221773456789",
    kyb_status: "pending",
    wallet_balance: 0,
    status: "active",
    api_key_count: 0,
    created_at: "2024-03-01T09:15:00Z",
  },
  {
    id: "4",
    business_name: "Pharmacie Centrale",
    business_type: "Healthcare",
    owner_name: "Dr. Fatou Ndiaye",
    email: "pharmacie.centrale@email.sn",
    phone: "+221774567890",
    kyb_status: "verified",
    wallet_balance: 1250000,
    status: "active",
    api_key_count: 1,
    created_at: "2023-11-20T16:20:00Z",
  },
  {
    id: "5",
    business_name: "Transport Express",
    business_type: "Transportation",
    owner_name: "Omar Fall",
    email: "info@transportexpress.sn",
    phone: "+221775678901",
    kyb_status: "rejected",
    wallet_balance: 0,
    status: "suspended",
    api_key_count: 0,
    created_at: "2024-02-15T11:00:00Z",
  },
];

export default function MerchantsPage() {
  const [merchants, setMerchants] = useState<Merchant[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages] = useState(3);

  useEffect(() => {
    setTimeout(() => {
      setMerchants(mockMerchants);
      setLoading(false);
    }, 500);
  }, []);

  const filteredMerchants = merchants.filter(
    (m) =>
      m.business_name.toLowerCase().includes(search.toLowerCase()) ||
      m.owner_name.toLowerCase().includes(search.toLowerCase()) ||
      m.email.toLowerCase().includes(search.toLowerCase())
  );

  if (loading) {
    return <PageLoading />;
  }

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Marchands</h1>
          <p className="text-gray-500">Gestion des comptes marchands</p>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-emerald-50 rounded-lg">
              <Store className="h-5 w-5 text-emerald-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">342</p>
              <p className="text-sm text-gray-500">Total marchands</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-blue-50 rounded-lg">
              <CheckCircle className="h-5 w-5 text-blue-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">298</p>
              <p className="text-sm text-gray-500">KYB vérifié</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-yellow-50 rounded-lg">
              <Key className="h-5 w-5 text-yellow-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">12</p>
              <p className="text-sm text-gray-500">KYB en attente</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-purple-50 rounded-lg">
              <Key className="h-5 w-5 text-purple-600" />
            </div>
            <div>
              <p className="text-2xl font-bold text-gray-900">456</p>
              <p className="text-sm text-gray-500">Clés API actives</p>
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
              placeholder="Rechercher par nom, propriétaire ou email..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
            />
          </div>
          <select className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="">Tous les types</option>
            <option value="retail">Retail</option>
            <option value="food">Food & Beverage</option>
            <option value="tech">Technology</option>
            <option value="healthcare">Healthcare</option>
          </select>
          <select className="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="">Tous les KYB</option>
            <option value="verified">Vérifié</option>
            <option value="pending">En attente</option>
            <option value="rejected">Rejeté</option>
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
                  Entreprise
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Type
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Solde
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Clés API
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  KYB
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Statut
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Inscrit le
                </th>
                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {filteredMerchants.map((merchant) => (
                <tr key={merchant.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center">
                      <div className="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <Store className="h-5 w-5 text-blue-600" />
                      </div>
                      <div className="ml-4">
                        <p className="text-sm font-medium text-gray-900">
                          {merchant.business_name}
                        </p>
                        <p className="text-sm text-gray-500">
                          {merchant.owner_name}
                        </p>
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                    {merchant.business_type}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {formatCurrency(merchant.wallet_balance)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                    <span className="px-2 py-1 bg-gray-100 rounded text-xs font-medium">
                      {merchant.api_key_count}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge status={merchant.kyb_status} />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge status={merchant.status} />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {formatDate(merchant.created_at)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
                        title="Voir détails"
                      >
                        <Eye className="h-4 w-4" />
                      </button>
                      {merchant.kyb_status === "pending" && (
                        <button
                          className="p-2 text-emerald-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg"
                          title="Approuver KYB"
                        >
                          <CheckCircle className="h-4 w-4" />
                        </button>
                      )}
                      {merchant.status === "active" && (
                        <button
                          className="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                          title="Suspendre"
                        >
                          <Ban className="h-4 w-4" />
                        </button>
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
