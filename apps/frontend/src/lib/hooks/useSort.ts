import { useState, useMemo } from 'react';

type SortDir = 'asc' | 'desc';

interface UseSortResult<T> {
  sorted: T[];
  sortKey: keyof T | null;
  sortDir: SortDir;
  toggleSort: (key: keyof T) => void;
}

export function useSort<T>(items: T[]): UseSortResult<T> {
  const [sortKey, setSortKey] = useState<keyof T | null>(null);
  const [sortDir, setSortDir] = useState<SortDir>('asc');

  const toggleSort = (key: keyof T) => {
    if (sortKey === key) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortKey(key);
      setSortDir('asc');
    }
  };

  const sorted = useMemo(() => {
    if (!sortKey) return items;
    return [...items].sort((a, b) => {
      const av = a[sortKey];
      const bv = b[sortKey];
      if (av == null) return 1;
      if (bv == null) return -1;
      const cmp = String(av).localeCompare(String(bv), undefined, {
        numeric: true,
        sensitivity: 'base',
      });
      return sortDir === 'asc' ? cmp : -cmp;
    });
  }, [items, sortKey, sortDir]);

  return { sorted, sortKey, sortDir, toggleSort };
}
