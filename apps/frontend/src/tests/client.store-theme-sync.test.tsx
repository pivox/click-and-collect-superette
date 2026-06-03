import { render, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
  getStoreTheme: vi.fn(),
  pathname: '/',
  selectedStore: null as { id: string; name: string } | null,
}));

vi.mock('next/navigation', () => ({
  usePathname: () => mocks.pathname,
}));

vi.mock('@/lib/services', () => ({
  getStoreTheme: mocks.getStoreTheme,
}));

vi.mock('@/lib/store/SelectedStoreContext', () => ({
  useSelectedStore: () => ({
    selectedStore: mocks.selectedStore,
    selectStore: vi.fn(),
    clearStore: vi.fn(),
  }),
}));

import { StoreThemeSync } from '@/components/store/StoreThemeSync';

describe('StoreThemeSync', () => {
  beforeEach(() => {
    mocks.getStoreTheme.mockReset();
    mocks.getStoreTheme.mockResolvedValue({
      primaryColor: '#AA1122',
      secondaryColor: '#BB2233',
      fontFamily: 'Cairo',
    });
    mocks.pathname = '/';
    mocks.selectedStore = null;
    document.documentElement.style.removeProperty('--primary');
    document.documentElement.style.removeProperty('--secondary');
    document.documentElement.style.removeProperty('--font-family');
  });

  it('applique le thème de la supérette sélectionnée hors route de supérette différente', async () => {
    mocks.selectedStore = { id: 'store-a', name: 'Supérette A' };

    render(<StoreThemeSync />);

    await waitFor(() => expect(mocks.getStoreTheme).toHaveBeenCalledWith('store-a'));
    expect(document.documentElement.style.getPropertyValue('--primary')).toBe('#AA1122');
  });

  it("n'applique pas le thème sélectionné si une autre supérette est ouverte par URL", async () => {
    mocks.selectedStore = { id: 'store-a', name: 'Supérette A' };
    mocks.pathname = '/stores/store-b/catalog';

    render(<StoreThemeSync />);

    await waitFor(() => expect(mocks.getStoreTheme).not.toHaveBeenCalled());
    expect(document.documentElement.style.getPropertyValue('--primary')).toBe('');
  });
});
