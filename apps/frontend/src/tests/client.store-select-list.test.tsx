import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push }),
}));
vi.mock('@/lib/store/SelectedStoreContext', () => ({
  useSelectedStore: vi.fn(),
}));
vi.mock('@/lib/store/hasActiveKadhia', () => ({
  hasActiveKadhia: vi.fn(),
}));

import { StoreSelectList } from '@/components/store/StoreSelectList';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';
import type { Shop } from '@/types';

const SHOP_A: Shop = { id: 'a', name: 'Aziza', slug: 'aziza', isActive: true, address: null, city: null, phone: null };
const SHOP_B: Shop = { id: 'b', name: 'Monoprix', slug: 'monoprix', isActive: true, address: null, city: null, phone: null };

describe('StoreSelectList', () => {
  const selectStore = vi.fn();

  beforeEach(() => {
    push.mockClear();
    selectStore.mockClear();
    vi.mocked(hasActiveKadhia).mockReturnValue(false);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: null, selectStore, clearStore: vi.fn(),
    });
  });

  it('affiche le message vide si aucune supérette', () => {
    render(<StoreSelectList shops={[]} />);
    expect(screen.getByText('Aucune supérette disponible pour le moment.')).toBeTruthy();
  });

  it('affiche les cartes des supérettes', () => {
    render(<StoreSelectList shops={[SHOP_A, SHOP_B]} />);
    expect(screen.getByText('Aziza')).toBeTruthy();
    expect(screen.getByText('Monoprix')).toBeTruthy();
  });

  it('sélectionne le store et navigue au clic sans Kadhia active', async () => {
    render(<StoreSelectList shops={[SHOP_A]} />);
    act(() => screen.getByText('Aziza').closest('[role="button"]')!.click());
    await waitFor(() => expect(selectStore).toHaveBeenCalledWith({ id: 'a', name: 'Aziza', logoLetter: undefined }));
    expect(push).toHaveBeenCalledWith('/stores/a');
  });

  it('affiche le warning si Kadhia active dans le store courant', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 'a', name: 'Aziza' }, selectStore, clearStore: vi.fn(),
    });
    vi.mocked(hasActiveKadhia).mockReturnValue(true);
    render(<StoreSelectList shops={[SHOP_A, SHOP_B]} />);
    act(() => screen.getByText('Monoprix').closest('[role="button"]')!.click());
    await waitFor(() => expect(screen.getByText('Changer de supérette ?')).toBeTruthy());
    expect(selectStore).not.toHaveBeenCalled();
  });

  it('confirmer le warning appelle selectStore et navigue', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 'a', name: 'Aziza' }, selectStore, clearStore: vi.fn(),
    });
    vi.mocked(hasActiveKadhia).mockReturnValue(true);
    render(<StoreSelectList shops={[SHOP_A, SHOP_B]} />);
    act(() => screen.getByText('Monoprix').closest('[role="button"]')!.click());
    await waitFor(() => screen.getByRole('button', { name: 'Changer quand même' }));
    act(() => screen.getByRole('button', { name: 'Changer quand même' }).click());
    await waitFor(() => expect(selectStore).toHaveBeenCalledWith({ id: 'b', name: 'Monoprix', logoLetter: undefined }));
    expect(push).toHaveBeenCalledWith('/stores/b');
  });
});
