import { cn } from "@/lib/utils";

type StatusType =
  | "pending"
  | "processing"
  | "completed"
  | "failed"
  | "refunded"
  | "active"
  | "inactive"
  | "suspended"
  | "blocked"
  | "verified"
  | "rejected"
  | "maintenance";

interface StatusBadgeProps {
  status: StatusType;
  className?: string;
}

const statusConfig: Record<StatusType, { label: string; color: string }> = {
  pending: { label: "En attente", color: "bg-yellow-100 text-yellow-800" },
  processing: { label: "En cours", color: "bg-blue-100 text-blue-800" },
  completed: { label: "Terminé", color: "bg-emerald-100 text-emerald-800" },
  failed: { label: "Échoué", color: "bg-red-100 text-red-800" },
  refunded: { label: "Remboursé", color: "bg-purple-100 text-purple-800" },
  active: { label: "Actif", color: "bg-emerald-100 text-emerald-800" },
  inactive: { label: "Inactif", color: "bg-gray-100 text-gray-800" },
  suspended: { label: "Suspendu", color: "bg-orange-100 text-orange-800" },
  blocked: { label: "Bloqué", color: "bg-red-100 text-red-800" },
  verified: { label: "Vérifié", color: "bg-emerald-100 text-emerald-800" },
  rejected: { label: "Rejeté", color: "bg-red-100 text-red-800" },
  maintenance: { label: "Maintenance", color: "bg-yellow-100 text-yellow-800" },
};

export function StatusBadge({ status, className }: StatusBadgeProps) {
  const config = statusConfig[status] || statusConfig.pending;

  return (
    <span
      className={cn(
        "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium",
        config.color,
        className
      )}
    >
      {config.label}
    </span>
  );
}
