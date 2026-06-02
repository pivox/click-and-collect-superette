'use client';

import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';

const STORAGE_KEY = 'selected_store';

export interface SelectedStore {
  id: string;
  name: string;
  logoLetter?: string | null;
}

export interface SelectedStoreContextValue {
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
      if (!raw) return;
      const parsed = JSON.parse(raw) as unknown;
      if (
        parsed &&
        typeof parsed === 'object' &&
        'id' in parsed && typeof (parsed as Record<string, unknown>).id === 'string' &&
        'name' in parsed && typeof (parsed as Record<string, unknown>).name === 'string'
      ) {
        setSelectedStore(parsed as SelectedStore);
      } else {
        localStorage.removeItem(STORAGE_KEY);
      }
    } catch {
      // SecurityError (privacy mode), QuotaExceeded, or malformed JSON — degrade to null
    }
  }, []);

  const selectStore = useCallback((shop: SelectedStore) => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(shop));
    } catch {
      // QuotaExceededError or SecurityError — still update React state
    }
    setSelectedStore(shop);
  }, []);

  const clearStore = useCallback(() => {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch {
      // SecurityError in restricted environments
    }
    setSelectedStore(null);
  }, []);

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
