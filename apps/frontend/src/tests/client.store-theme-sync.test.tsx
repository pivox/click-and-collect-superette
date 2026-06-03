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
      accentColor: '#CC3344',
      textColor: '#FFFFFF',
      backgroundColor: '#000000',
      fontFamily: 'Cairo',
      baseFontSize: '19px',
    });
    mocks.pathname = '/';
    mocks.selectedStore = null;
    [
      '--primary',
      '--secondary',
      '--accent',
      '--ink',
      '--bg',
      '--font-family',
      '--font-size-base',
    ].forEach((property) => document.documentElement.style.removeProperty(property));
  });

  it('applique le thème de la supérette sélectionnée hors route de supérette différente', async () => {
    mocks.selectedStore = { id: 'store-a', name: 'Supérette A' };

    render(<StoreThemeSync />);

    await waitFor(() => expect(mocks.getStoreTheme).toHaveBeenCalledWith('store-a'));
    expect(document.documentElement.style.getPropertyValue('--primary')).toBe('#AA1122');
    expect(document.documentElement.style.getPropertyValue('--secondary')).toBe('#BB2233');
    expect(document.documentElement.style.getPropertyValue('--accent')).toBe('#CC3344');
    expect(document.documentElement.style.getPropertyValue('--ink')).toBe('#FFFFFF');
    expect(document.documentElement.style.getPropertyValue('--bg')).toBe('#000000');
    expect(document.documentElement.style.getPropertyValue('--font-family')).toBe('Cairo');
    expect(document.documentElement.style.getPropertyValue('--font-size-base')).toBe('19px');
  });

  it("n'applique pas le thème sélectionné si une autre supérette est ouverte par URL", async () => {
    mocks.selectedStore = {
      id: '11111111-1111-4111-8111-111111111111',
      name: 'Supérette A',
    };
    mocks.pathname = '/stores/22222222-2222-4222-8222-222222222222/catalog';

    render(<StoreThemeSync />);

    await waitFor(() => expect(mocks.getStoreTheme).not.toHaveBeenCalled());
    expect(document.documentElement.style.getPropertyValue('--primary')).toBe('');
    expect(document.documentElement.style.getPropertyValue('--accent')).toBe('');
  });

  it('conserve le thème sélectionné sur les routes QR statiques', async () => {
    mocks.selectedStore = { id: 'store-a', name: 'Supérette A' };
    mocks.pathname = '/stores/by-qr-scan';

    render(<StoreThemeSync />);

    await waitFor(() => expect(mocks.getStoreTheme).toHaveBeenCalledWith('store-a'));
    expect(document.documentElement.style.getPropertyValue('--primary')).toBe('#AA1122');
  });
});
