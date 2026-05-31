'use client';
import { cn } from '@/lib/cn';
import { Button } from '@/components/ui/Button';

export interface Column<T> {
  key: string;
  label: string;
  sortable?: boolean;
  render?: (row: T) => React.ReactNode;
}

interface Pagination {
  page: number;
  total: number;
  limit: number;
  onPageChange: (page: number) => void;
}

interface AdminTableProps<T extends { id: string }> {
  columns: Column<T>[];
  data: T[];
  isLoading?: boolean;
  emptyMessage?: string;
  emptyAction?: { label: string; onClick: () => void };
  pagination?: Pagination;
  sortKey?: string | null;
  sortDir?: 'asc' | 'desc';
  onSort?: (key: string) => void;
}

export function AdminTable<T extends { id: string }>({
  columns,
  data,
  isLoading,
  emptyMessage = 'Aucun résultat',
  emptyAction,
  pagination,
  sortKey,
  sortDir,
  onSort,
}: AdminTableProps<T>) {
  const pageCount = pagination ? Math.ceil(pagination.total / pagination.limit) : 1;

  return (
    <div className="rounded-xl border border-line bg-card overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full min-w-[720px] text-sm">
          <thead className="border-b border-line bg-soft">
            <tr>
              {columns.map((col) => (
                <th
                  key={col.key}
                  onClick={col.sortable && onSort ? () => onSort(col.key) : undefined}
                  className={cn(
                    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted',
                    col.sortable && 'cursor-pointer select-none hover:text-ink',
                  )}
                >
                  {col.label}
                  {col.sortable && sortKey === col.key && (
                    <span className="ml-1">{sortDir === 'asc' ? '↑' : '↓'}</span>
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {isLoading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  {columns.map((col) => (
                    <td key={col.key} className="px-4 py-3">
                      <div className="h-4 w-3/4 animate-pulse rounded bg-soft" />
                    </td>
                  ))}
                </tr>
              ))
            ) : data.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="px-4 py-12 text-center">
                  <p className="text-sm text-muted">{emptyMessage}</p>
                  {emptyAction && (
                    <Button
                      variant="ghost"
                      size="md"
                      className="mt-3"
                      onClick={emptyAction.onClick}
                    >
                      {emptyAction.label}
                    </Button>
                  )}
                </td>
              </tr>
            ) : (
              data.map((row) => (
                <tr key={row.id} className="hover:bg-soft/50">
                  {columns.map((col) => (
                    <td key={col.key} className="px-4 py-3">
                      {col.render
                        ? col.render(row)
                        : String((row as Record<string, unknown>)[col.key] ?? '')}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      {pagination && (
        <div className="flex flex-col gap-3 border-t border-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <span className="text-xs text-muted">
            {pagination.total} résultat{pagination.total !== 1 ? 's' : ''}
          </span>
          <div className="flex w-full items-center justify-between gap-2 sm:w-auto">
            <Button
              variant="ghost"
              size="md"
              className="shrink-0"
              disabled={pagination.page <= 1}
              onClick={() => pagination.onPageChange(pagination.page - 1)}
            >
              ← Précédent
            </Button>
            <span className="text-xs text-muted">
              {pagination.page} / {Math.max(1, pageCount)}
            </span>
            <Button
              variant="ghost"
              size="md"
              className="shrink-0"
              disabled={pagination.page >= pageCount}
              onClick={() => pagination.onPageChange(pagination.page + 1)}
            >
              Suivant →
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
