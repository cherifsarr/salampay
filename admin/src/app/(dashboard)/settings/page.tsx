"use client";

import { useState } from "react";
import {
  User,
  Lock,
  Bell,
  Shield,
  Globe,
  CreditCard,
  Save,
} from "lucide-react";
import { Button } from "@/components";
import { useAuth } from "@/lib/auth-context";

const tabs = [
  { id: "profile", name: "Profil", icon: User },
  { id: "security", name: "Sécurité", icon: Lock },
  { id: "notifications", name: "Notifications", icon: Bell },
  { id: "fees", name: "Frais", icon: CreditCard },
  { id: "api", name: "API", icon: Globe },
];

export default function SettingsPage() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState("profile");
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    setSaving(true);
    await new Promise((resolve) => setTimeout(resolve, 1000));
    setSaving(false);
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Paramètres</h1>
        <p className="text-gray-500">Gérez vos préférences et configurations</p>
      </div>

      <div className="flex flex-col lg:flex-row gap-6">
        {/* Sidebar */}
        <div className="lg:w-64 flex-shrink-0">
          <nav className="bg-white rounded-xl shadow-sm border border-gray-100 p-2">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left transition-colors ${
                  activeTab === tab.id
                    ? "bg-emerald-50 text-emerald-700"
                    : "text-gray-600 hover:bg-gray-50"
                }`}
              >
                <tab.icon className="h-5 w-5" />
                <span className="font-medium">{tab.name}</span>
              </button>
            ))}
          </nav>
        </div>

        {/* Content */}
        <div className="flex-1">
          <div className="bg-white rounded-xl shadow-sm border border-gray-100">
            {/* Profile Tab */}
            {activeTab === "profile" && (
              <div className="p-6 space-y-6">
                <h2 className="text-lg font-semibold text-gray-900">
                  Informations du profil
                </h2>

                <div className="flex items-center gap-6">
                  <div className="h-20 w-20 bg-emerald-100 rounded-full flex items-center justify-center">
                    <User className="h-10 w-10 text-emerald-600" />
                  </div>
                  <div>
                    <Button variant="outline" size="sm">
                      Changer la photo
                    </Button>
                    <p className="mt-1 text-sm text-gray-500">
                      JPG, PNG ou GIF. Max 2MB.
                    </p>
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Nom complet
                    </label>
                    <input
                      type="text"
                      defaultValue={user?.name || "Admin"}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Email
                    </label>
                    <input
                      type="email"
                      defaultValue={user?.email || "admin@salampay.sn"}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Téléphone
                    </label>
                    <input
                      type="tel"
                      defaultValue="+221 77 123 45 67"
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Rôle
                    </label>
                    <input
                      type="text"
                      value="Administrateur"
                      disabled
                      className="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500"
                    />
                  </div>
                </div>

                <div className="pt-4 border-t border-gray-100 flex justify-end">
                  <Button onClick={handleSave} loading={saving}>
                    <Save className="h-4 w-4 mr-2" />
                    Enregistrer
                  </Button>
                </div>
              </div>
            )}

            {/* Security Tab */}
            {activeTab === "security" && (
              <div className="p-6 space-y-6">
                <h2 className="text-lg font-semibold text-gray-900">
                  Sécurité du compte
                </h2>

                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Mot de passe actuel
                    </label>
                    <input
                      type="password"
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Nouveau mot de passe
                    </label>
                    <input
                      type="password"
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Confirmer le mot de passe
                    </label>
                    <input
                      type="password"
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                  </div>
                </div>

                <div className="p-4 bg-gray-50 rounded-lg">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <Shield className="h-5 w-5 text-emerald-600" />
                      <div>
                        <p className="font-medium text-gray-900">
                          Authentification à deux facteurs
                        </p>
                        <p className="text-sm text-gray-500">
                          Ajouter une couche de sécurité supplémentaire
                        </p>
                      </div>
                    </div>
                    <Button variant="outline" size="sm">
                      Activer
                    </Button>
                  </div>
                </div>

                <div className="pt-4 border-t border-gray-100 flex justify-end">
                  <Button onClick={handleSave} loading={saving}>
                    <Save className="h-4 w-4 mr-2" />
                    Mettre à jour
                  </Button>
                </div>
              </div>
            )}

            {/* Notifications Tab */}
            {activeTab === "notifications" && (
              <div className="p-6 space-y-6">
                <h2 className="text-lg font-semibold text-gray-900">
                  Préférences de notifications
                </h2>

                <div className="space-y-4">
                  {[
                    {
                      title: "Nouvelles transactions",
                      description: "Recevoir une alerte pour chaque transaction",
                    },
                    {
                      title: "Règlements en attente",
                      description: "Rappel des règlements à traiter",
                    },
                    {
                      title: "KYC/KYB à vérifier",
                      description: "Notification des nouvelles demandes",
                    },
                    {
                      title: "Alertes de sécurité",
                      description: "Activités suspectes détectées",
                    },
                    {
                      title: "Rapports quotidiens",
                      description: "Résumé quotidien par email",
                    },
                  ].map((item, index) => (
                    <div
                      key={index}
                      className="flex items-center justify-between p-4 border border-gray-200 rounded-lg"
                    >
                      <div>
                        <p className="font-medium text-gray-900">{item.title}</p>
                        <p className="text-sm text-gray-500">{item.description}</p>
                      </div>
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          defaultChecked={index < 4}
                          className="sr-only peer"
                        />
                        <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                      </label>
                    </div>
                  ))}
                </div>

                <div className="pt-4 border-t border-gray-100 flex justify-end">
                  <Button onClick={handleSave} loading={saving}>
                    <Save className="h-4 w-4 mr-2" />
                    Enregistrer
                  </Button>
                </div>
              </div>
            )}

            {/* Fees Tab */}
            {activeTab === "fees" && (
              <div className="p-6 space-y-6">
                <h2 className="text-lg font-semibold text-gray-900">
                  Configuration des frais
                </h2>

                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead>
                      <tr className="border-b border-gray-200">
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-500">
                          Type
                        </th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-500">
                          Frais fixes (XOF)
                        </th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-500">
                          Frais (%)
                        </th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-500">
                          Min (XOF)
                        </th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-500">
                          Max (XOF)
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {[
                        { type: "Dépôt", fixed: 0, percent: 1, min: 100, max: 5000 },
                        { type: "Retrait", fixed: 100, percent: 1.5, min: 200, max: 10000 },
                        { type: "Transfert", fixed: 50, percent: 0.5, min: 100, max: 2500 },
                        { type: "Paiement", fixed: 0, percent: 2, min: 50, max: 25000 },
                        { type: "Règlement", fixed: 0, percent: 0.5, min: 500, max: 50000 },
                      ].map((fee, index) => (
                        <tr key={index} className="border-b border-gray-100">
                          <td className="px-4 py-3 font-medium text-gray-900">
                            {fee.type}
                          </td>
                          <td className="px-4 py-3">
                            <input
                              type="number"
                              defaultValue={fee.fixed}
                              className="w-24 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                          </td>
                          <td className="px-4 py-3">
                            <input
                              type="number"
                              step="0.1"
                              defaultValue={fee.percent}
                              className="w-24 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                          </td>
                          <td className="px-4 py-3">
                            <input
                              type="number"
                              defaultValue={fee.min}
                              className="w-24 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                          </td>
                          <td className="px-4 py-3">
                            <input
                              type="number"
                              defaultValue={fee.max}
                              className="w-24 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <div className="pt-4 border-t border-gray-100 flex justify-end">
                  <Button onClick={handleSave} loading={saving}>
                    <Save className="h-4 w-4 mr-2" />
                    Enregistrer
                  </Button>
                </div>
              </div>
            )}

            {/* API Tab */}
            {activeTab === "api" && (
              <div className="p-6 space-y-6">
                <h2 className="text-lg font-semibold text-gray-900">
                  Configuration API
                </h2>

                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      URL Webhook
                    </label>
                    <input
                      type="url"
                      placeholder="https://votre-serveur.com/webhook"
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                    <p className="mt-1 text-sm text-gray-500">
                      URL où les événements seront envoyés
                    </p>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Secret Webhook
                    </label>
                    <div className="flex gap-2">
                      <input
                        type="text"
                        value="whsec_****************************"
                        disabled
                        className="flex-1 px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 font-mono text-sm"
                      />
                      <Button variant="outline">Régénérer</Button>
                    </div>
                  </div>

                  <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p className="text-sm text-yellow-800">
                      <strong>Attention:</strong> La régénération du secret
                      invalidera l&apos;ancien secret. Assurez-vous de mettre à jour
                      vos intégrations.
                    </p>
                  </div>
                </div>

                <div className="pt-4 border-t border-gray-100 flex justify-end">
                  <Button onClick={handleSave} loading={saving}>
                    <Save className="h-4 w-4 mr-2" />
                    Enregistrer
                  </Button>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
