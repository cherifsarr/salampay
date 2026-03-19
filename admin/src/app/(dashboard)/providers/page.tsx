"use client";

import { useEffect, useState } from "react";
import {
  RefreshCw,
  Settings,
  AlertCircle,
  CheckCircle,
  Pause,
  Play,
} from "lucide-react";
import { StatusBadge, PageLoading, Button } from "@/components";
import { formatCurrency } from "@/lib/utils";
import type { Provider } from "@/types";

// Mock data
const mockProviders: Provider[] = [
  {
    id: "1",
    name: "Wave",
    code: "wave",
    type: "mobile_money",
    status: "active",
    balance: 15000000,
    transaction_count: 45230,
    success_rate: 98.5,
  },
  {
    id: "2",
    name: "Orange Money",
    code: "orange_money",
    type: "mobile_money",
    status: "active",
    balance: 8500000,
    transaction_count: 23450,
    success_rate: 97.2,
  },
  {
    id: "3",
    name: "Free Money",
    code: "free_money",
    type: "mobile_money",
    status: "active",
    balance: 5200000,
    transaction_count: 12340,
    success_rate: 96.8,
  },
  {
    id: "4",
    name: "Wizall",
    code: "wizall",
    type: "mobile_money",
    status: "maintenance",
    balance: 2100000,
    transaction_count: 5670,
    success_rate: 95.1,
  },
  {
    id: "5",
    name: "E-Money",
    code: "emoney",
    type: "mobile_money",
    status: "active",
    balance: 3800000,
    transaction_count: 8920,
    success_rate: 97.8,
  },
];

const providerLogos: Record<string, { color: string; bgColor: string }> = {
  wave: { color: "text-blue-600", bgColor: "bg-blue-100" },
  orange_money: { color: "text-orange-600", bgColor: "bg-orange-100" },
  free_money: { color: "text-red-600", bgColor: "bg-red-100" },
  wizall: { color: "text-purple-600", bgColor: "bg-purple-100" },
  emoney: { color: "text-emerald-600", bgColor: "bg-emerald-100" },
};

export default function ProvidersPage() {
  const [providers, setProviders] = useState<Provider[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState<string | null>(null);

  useEffect(() => {
    setTimeout(() => {
      setProviders(mockProviders);
      setLoading(false);
    }, 500);
  }, []);

  const handleRefreshBalance = async (providerId: string) => {
    setRefreshing(providerId);
    // Simulate API call
    await new Promise((resolve) => setTimeout(resolve, 1500));
    setRefreshing(null);
  };

  const totalBalance = providers.reduce((sum, p) => sum + (p.balance || 0), 0);
  const totalTransactions = providers.reduce(
    (sum, p) => sum + p.transaction_count,
    0
  );
  const activeProviders = providers.filter((p) => p.status === "active").length;

  if (loading) {
    return <PageLoading />;
  }

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Fournisseurs</h1>
          <p className="text-gray-500">Gestion des passerelles de paiement</p>
        </div>
        <Button onClick={() => providers.forEach((p) => handleRefreshBalance(p.id))}>
          <RefreshCw className="h-4 w-4 mr-2" />
          Actualiser tout
        </Button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl p-6 text-white">
          <p className="text-emerald-100">Solde total</p>
          <p className="mt-2 text-3xl font-bold">{formatCurrency(totalBalance)}</p>
          <p className="mt-2 text-emerald-100">
            Sur {providers.length} fournisseurs
          </p>
        </div>
        <div className="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
          <p className="text-gray-500">Transactions totales</p>
          <p className="mt-2 text-3xl font-bold text-gray-900">
            {totalTransactions.toLocaleString()}
          </p>
          <p className="mt-2 text-sm text-gray-500">Depuis le début</p>
        </div>
        <div className="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
          <p className="text-gray-500">Fournisseurs actifs</p>
          <p className="mt-2 text-3xl font-bold text-gray-900">
            {activeProviders}/{providers.length}
          </p>
          <p className="mt-2 text-sm text-emerald-600">
            <CheckCircle className="h-4 w-4 inline mr-1" />
            Tous opérationnels
          </p>
        </div>
      </div>

      {/* Provider Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {providers.map((provider) => {
          const style = providerLogos[provider.code] || {
            color: "text-gray-600",
            bgColor: "bg-gray-100",
          };

          return (
            <div
              key={provider.id}
              className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
            >
              {/* Header */}
              <div className="p-6 border-b border-gray-100">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div
                      className={`h-12 w-12 ${style.bgColor} rounded-xl flex items-center justify-center`}
                    >
                      <span className={`text-xl font-bold ${style.color}`}>
                        {provider.name[0]}
                      </span>
                    </div>
                    <div>
                      <h3 className="font-semibold text-gray-900">
                        {provider.name}
                      </h3>
                      <p className="text-sm text-gray-500 capitalize">
                        {provider.type.replace("_", " ")}
                      </p>
                    </div>
                  </div>
                  <StatusBadge status={provider.status} />
                </div>
              </div>

              {/* Stats */}
              <div className="p-6 space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-gray-500">Solde</span>
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-gray-900">
                      {formatCurrency(provider.balance || 0)}
                    </span>
                    <button
                      onClick={() => handleRefreshBalance(provider.id)}
                      className="p-1 hover:bg-gray-100 rounded transition-colors"
                      disabled={refreshing === provider.id}
                    >
                      <RefreshCw
                        className={`h-4 w-4 text-gray-400 ${
                          refreshing === provider.id ? "animate-spin" : ""
                        }`}
                      />
                    </button>
                  </div>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-gray-500">Transactions</span>
                  <span className="font-semibold text-gray-900">
                    {provider.transaction_count.toLocaleString()}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-gray-500">Taux de succès</span>
                  <span
                    className={`font-semibold ${
                      provider.success_rate >= 97
                        ? "text-emerald-600"
                        : provider.success_rate >= 95
                        ? "text-yellow-600"
                        : "text-red-600"
                    }`}
                  >
                    {provider.success_rate}%
                  </span>
                </div>

                {/* Success Rate Bar */}
                <div className="pt-2">
                  <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div
                      className={`h-full rounded-full ${
                        provider.success_rate >= 97
                          ? "bg-emerald-500"
                          : provider.success_rate >= 95
                          ? "bg-yellow-500"
                          : "bg-red-500"
                      }`}
                      style={{ width: `${provider.success_rate}%` }}
                    />
                  </div>
                </div>
              </div>

              {/* Actions */}
              <div className="px-6 py-4 bg-gray-50 border-t border-gray-100">
                <div className="flex items-center justify-between">
                  <button className="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                    <Settings className="h-4 w-4" />
                    Configurer
                  </button>
                  {provider.status === "active" ? (
                    <button className="flex items-center gap-2 text-sm text-yellow-600 hover:text-yellow-700">
                      <Pause className="h-4 w-4" />
                      Désactiver
                    </button>
                  ) : provider.status === "maintenance" ? (
                    <button className="flex items-center gap-2 text-sm text-emerald-600 hover:text-emerald-700">
                      <Play className="h-4 w-4" />
                      Activer
                    </button>
                  ) : (
                    <span className="flex items-center gap-2 text-sm text-gray-400">
                      <AlertCircle className="h-4 w-4" />
                      Inactif
                    </span>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
