import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('@/lib/store/SelectedStoreContext', () => ({ useSelectedStore: vi.fn() }));
vi.mock('@/lib/store/hasActiveKadhia', () => ({ hasActiveKadhia: vi.fn() }));
vi.mock('@/lib/hooks/useHydrated', () => ({ useHydrated: vi.fn() }));

import { StartKadhiaCta } from '@/components/store/StartKadhiaCta';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';
import { useHydrated } from '@/lib/hooks/useHydrated';

const SHOP = { id: 'shop-1', name: 'Aziza', logoLetter: 'A' };

describe('StartKadhiaCta', () => {
  const selectStore = vi.fn();

  beforeEach(() => {
    push.mockClear();
    selectStore.mockClear();
    vi.mocked(hasActiveKadhia).mockReturnValue(false);
    vi.mocked(useHydrated).mockReturnValue(true);
  });

  it('auto-sélectionne le store au montage si hydraté et aucun store actif', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: null, selectStore, clearStore: vi.fn(),
    });
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(selectStore).toHaveBeenCalledWith(SHOP));
  });

  it("n'auto-sélectionne pas avant hydratation (guard stale closure)", async () => {
    vi.mocked(useHydrated).mockReturnValue(false);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: null, selectStore, clearStore: vi.fn(),
    });
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(selectStore).not.toHaveBeenCalled());
  });

  it("n'auto-sélectionne pas si le même store est déjà actif", async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: SHOP, selectStore, clearStore: vi.fn(),
    });
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(selectStore).not.toHaveBeenCalled());
  });

  it('navigue vers le catalogue au clic sans conflit', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: SHOP, selectStore, clearStore: vi.fn(),
    });
    render(<StartKadhiaCta shop={SHOP} />);
    act(() => screen.getByRole('button').click());
    await waitFor(() => expect(push).toHaveBeenCalledWith('/stores/shop-1/catalog'));
  });

  it('affiche le warning si Kadhia active dans un autre store', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 'other', name: 'Monoprix' }, selectStore, clearStore: vi.fn(),
    });
    vi.mocked(hasActiveKadhia).mockReturnValue(true);
    render(<StartKadhiaCta shop={SHOP} />);
    act(() => screen.getByRole('button').click());
    await waitFor(() => expect(screen.getByText('Changer de supérette ?')).toBeTruthy());
  });

  it('confirmer le warning sélectionne le store et navigue', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 'other', name: 'Monoprix' }, selectStore, clearStore: vi.fn(),
    });
    vi.mocked(hasActiveKadhia).mockReturnValue(true);
    render(<StartKadhiaCta shop={SHOP} />);
    act(() => screen.getByRole('button').click());
    await waitFor(() => screen.getByRole('button', { name: 'Changer quand même' }));
    act(() => screen.getByRole('button', { name: 'Changer quand même' }).click());
    await waitFor(() => expect(selectStore).toHaveBeenCalledWith(SHOP));
    expect(push).toHaveBeenCalledWith('/stores/shop-1/catalog');
  });

  it('annuler le warning ferme le dialog sans naviguer', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 'other', name: 'Monoprix' }, selectStore, clearStore: vi.fn(),
    });
    vi.mocked(hasActiveKadhia).mockReturnValue(true);
    render(<StartKadhiaCta shop={SHOP} />);
    act(() => screen.getByRole('button').click());
    await waitFor(() => screen.getByRole('button', { name: 'Annuler' }));
    act(() => screen.getByRole('button', { name: 'Annuler' }).click());
    await waitFor(() => expect(screen.queryByText('Changer de supérette ?')).toBeNull());
    expect(selectStore).not.toHaveBeenCalled();
    expect(push).not.toHaveBeenCalled();
  });
});
