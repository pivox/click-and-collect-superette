'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';

const STORAGE_KEY = 'selected_store';

export interface SelectedStore {
  id: string;
  name: string;
  logoLetter?: string | null;
}

interface SelectedStoreContextValue {
  selectedStore: SelectedStore | null;
  selectStore: (shop: SelectedStore) => void;
  clearStore: () => void;
}

const SelectedStoreContext = createContext<SelectedStoreContextValue | null>(null);

export function SelectedStoreProvider({ children }: { children: React.ReactNode }) {
  const [selectedStore, setSelectedStore] = useState<SelectedStore | null>(null);

  useEffect(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) setSelectedStore(JSON.parse(raw) as SelectedStore);
    } catch { /* ignore */ }
  }, []);

  const selectStore = (shop: SelectedStore) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(shop));
    setSelectedStore(shop);
  };

  const clearStore = () => {
    localStorage.removeItem(STORAGE_KEY);
    setSelectedStore(null);
  };

  return (
    <SelectedStoreContext.Provider value={{ selectedStore, selectStore, clearStore }}>
      {children}
    </SelectedStoreContext.Provider>
  );
}

export function useSelectedStore(): SelectedStoreContextValue {
  const ctx = useContext(SelectedStoreContext);
  if (!ctx) throw new Error('useSelectedStore must be used within SelectedStoreProvider');
  return ctx;
}
