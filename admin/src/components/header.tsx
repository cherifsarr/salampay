"use client";

import { Bell, Search, User } from "lucide-react";
import { useAuth } from "@/lib/auth-context";

export function Header() {
  const { user } = useAuth();

  return (
    <header className="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
      {/* Search */}
      <div className="flex-1 max-w-md">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            type="text"
            placeholder="Rechercher..."
            className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
          />
        </div>
      </div>

      {/* Right side */}
      <div className="flex items-center gap-4">
        {/* Notifications */}
        <button className="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
          <Bell className="h-5 w-5" />
          <span className="absolute top-1 right-1 h-2 w-2 bg-red-500 rounded-full" />
        </button>

        {/* User menu */}
        <div className="flex items-center gap-3 pl-4 border-l border-gray-200">
          <div className="h-8 w-8 bg-emerald-100 rounded-full flex items-center justify-center">
            <User className="h-4 w-4 text-emerald-600" />
          </div>
          <div className="text-sm">
            <p className="font-medium text-gray-900">{user?.name || "Admin"}</p>
            <p className="text-gray-500 text-xs">{user?.role || "Administrateur"}</p>
          </div>
        </div>
      </div>
    </header>
  );
}
