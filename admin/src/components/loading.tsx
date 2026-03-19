import { cn } from "@/lib/utils";
import { Loader2 } from "lucide-react";

interface LoadingProps {
  className?: string;
  size?: "sm" | "md" | "lg";
}

const sizes = {
  sm: "h-4 w-4",
  md: "h-6 w-6",
  lg: "h-8 w-8",
};

export function Loading({ className, size = "md" }: LoadingProps) {
  return (
    <Loader2
      className={cn("animate-spin text-emerald-600", sizes[size], className)}
    />
  );
}

export function PageLoading() {
  return (
    <div className="flex items-center justify-center h-64">
      <Loading size="lg" />
    </div>
  );
}

export function FullPageLoading() {
  return (
    <div className="fixed inset-0 bg-white flex items-center justify-center z-50">
      <div className="text-center">
        <Loading size="lg" />
        <p className="mt-4 text-gray-500">Chargement...</p>
      </div>
    </div>
  );
}
