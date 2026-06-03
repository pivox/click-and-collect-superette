"use client";

interface PaginationProps {
  page: number;
  pages: number;
  onPageChange: (page: number) => void;
  className?: string;
}

export function Pagination({ page, pages, onPageChange, className = "" }: PaginationProps) {
  if (pages <= 1) return null;

  return (
    <div className={`flex items-center justify-between gap-2 mt-4 ${className}`}>
      <button
        type="button"
        onClick={() => onPageChange(page - 1)}
        disabled={page <= 1}
        className="text-sm font-bold text-primary disabled:opacity-40 disabled:cursor-not-allowed"
      >
        ← Précédent
      </button>
      <span className="text-xs text-muted">
        Page {page} / {pages}
      </span>
      <button
        type="button"
        onClick={() => onPageChange(page + 1)}
        disabled={page >= pages}
        className="text-sm font-bold text-primary disabled:opacity-40 disabled:cursor-not-allowed"
      >
        Suivant →
      </button>
    </div>
  );
}
