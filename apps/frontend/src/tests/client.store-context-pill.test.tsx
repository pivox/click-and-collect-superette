import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn() }),
}));
vi.mock('@/lib/store/SelectedStoreContext', () => ({
  useSelectedStore: vi.fn(),
}));
vi.mock('@/lib/hooks/useHydrated', () => ({
  useHydrated: vi.fn(),
}));

import { StoreContextPill } from '@/components/store/StoreContextPill';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { useHydrated } from '@/lib/hooks/useHydrated';

describe('StoreContextPill', () => {
  it('ne rend rien avant hydratation (SSR guard)', () => {
    vi.mocked(useHydrated).mockReturnValue(false);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: null, selectStore: vi.fn(), clearStore: vi.fn(),
    });
    const { container } = render(<StoreContextPill />);
    expect(container.firstChild).toBeNull();
  });

  it('affiche la pill ambre "Choisir une supérette" quand aucun store', async () => {
    vi.mocked(useHydrated).mockReturnValue(true);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: null, selectStore: vi.fn(), clearStore: vi.fn(),
    });
    render(<StoreContextPill />);
    await waitFor(() => expect(screen.getByText('Choisir une supérette')).toBeTruthy());
  });

  it('affiche le nom du store quand sélectionné', async () => {
    vi.mocked(useHydrated).mockReturnValue(true);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 's1', name: 'Aziza Montplaisir' },
      selectStore: vi.fn(),
      clearStore: vi.fn(),
    });
    render(<StoreContextPill />);
    await waitFor(() => expect(screen.getByText('Aziza Montplaisir')).toBeTruthy());
  });

  it('les deux pills sont des liens vers /stores', async () => {
    vi.mocked(useHydrated).mockReturnValue(true);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: null, selectStore: vi.fn(), clearStore: vi.fn(),
    });
    render(<StoreContextPill />);
    await waitFor(() => {
      expect(screen.getByRole('link').getAttribute('href')).toBe('/stores');
    });
  });
});
