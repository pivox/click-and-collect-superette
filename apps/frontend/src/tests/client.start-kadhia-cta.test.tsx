import { render, screen, act, waitFor } from '@/tests/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('@/lib/store/SelectedStoreContext', () => ({ useSelectedStore: vi.fn() }));
vi.mock('@/lib/store/hasActiveKadhia', () => ({ hasActiveKadhia: vi.fn() }));
vi.mock('@/lib/hooks/useHydrated', () => ({ useHydrated: vi.fn() }));
vi.mock('@/lib/services/kadhia.service', () => ({ countStoreDraftKadhias: vi.fn() }));

import { StartKadhiaCta } from '@/components/store/StartKadhiaCta';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';
import { useHydrated } from '@/lib/hooks/useHydrated';
import { countStoreDraftKadhias } from '@/lib/services/kadhia.service';

const SHOP = { id: 'shop-1', name: 'Aziza', logoLetter: 'A' };

describe('StartKadhiaCta', () => {
  const selectStore = vi.fn();

  beforeEach(() => {
    push.mockClear();
    selectStore.mockClear();
    vi.mocked(hasActiveKadhia).mockReturnValue(false);
    vi.mocked(useHydrated).mockReturnValue(true);
    vi.mocked(countStoreDraftKadhias).mockResolvedValue(0);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: SHOP, selectStore, clearStore: vi.fn(),
    });
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
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(selectStore).not.toHaveBeenCalled());
  });

  it('navigue vers le catalogue au clic sans conflit', async () => {
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(screen.getByRole('button')).toBeEnabled());
    act(() => screen.getByRole('button').click());
    await waitFor(() => expect(push).toHaveBeenCalledWith('/stores/shop-1/catalog'));
  });

  it('affiche le warning si Kadhia active dans un autre store', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 'other', name: 'Monoprix' }, selectStore, clearStore: vi.fn(),
    });
    vi.mocked(hasActiveKadhia).mockReturnValue(true);
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(screen.getByRole('button')).toBeEnabled());
    act(() => screen.getByRole('button').click());
    await waitFor(() => expect(screen.getByText('Changer de supérette ?')).toBeTruthy());
  });

  it('confirmer le warning sélectionne le store et navigue', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 'other', name: 'Monoprix' }, selectStore, clearStore: vi.fn(),
    });
    vi.mocked(hasActiveKadhia).mockReturnValue(true);
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(screen.getByRole('button')).toBeEnabled());
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
    await waitFor(() => expect(screen.getByRole('button')).toBeEnabled());
    act(() => screen.getByRole('button').click());
    await waitFor(() => screen.getByRole('button', { name: 'Annuler' }));
    act(() => screen.getByRole('button', { name: 'Annuler' }).click());
    await waitFor(() => expect(screen.queryByText('Changer de supérette ?')).toBeNull());
    expect(selectStore).not.toHaveBeenCalled();
    expect(push).not.toHaveBeenCalled();
  });

  // ─── CTA contextuel (P0 — continuer une Kadhia draft) ──────────────────────

  it('reste désactivé tant que le décompte des drafts n’est pas résolu', async () => {
    let resolve!: (count: number) => void;
    vi.mocked(countStoreDraftKadhias).mockReturnValue(new Promise((r) => { resolve = r; }));
    render(<StartKadhiaCta shop={SHOP} />);
    expect(screen.getByRole('button')).toBeDisabled();
    act(() => resolve(0));
    await waitFor(() => expect(screen.getByRole('button')).toBeEnabled());
  });

  it('aucune draft : le bouton reste "Commencer ma Kadhia" et navigue vers le catalogue', async () => {
    vi.mocked(countStoreDraftKadhias).mockResolvedValue(0);
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(countStoreDraftKadhias).toHaveBeenCalledWith('shop-1'));
    expect(screen.getByRole('button', { name: 'Commencer ma Kadhia' })).toBeTruthy();
    act(() => screen.getByRole('button').click());
    await waitFor(() => expect(push).toHaveBeenCalledWith('/stores/shop-1/catalog'));
  });

  it('une draft : le bouton devient "Continuer ma Kadhia" et ouvre le catalogue', async () => {
    vi.mocked(countStoreDraftKadhias).mockResolvedValue(1);
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => screen.getByRole('button', { name: 'Continuer ma Kadhia' }));
    act(() => screen.getByRole('button').click());
    await waitFor(() => expect(push).toHaveBeenCalledWith('/stores/shop-1/catalog'));
  });

  it('plusieurs drafts : le bouton devient "Reprendre une Kadhia" et ouvre Mes Kadhia', async () => {
    vi.mocked(countStoreDraftKadhias).mockResolvedValue(3);
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => screen.getByRole('button', { name: 'Reprendre une Kadhia' }));
    act(() => screen.getByRole('button').click());
    await waitFor(() => expect(push).toHaveBeenCalledWith('/kadhia'));
  });

  it('erreur API du décompte : repli sur "Commencer ma Kadhia" sans créer de Kadhia', async () => {
    vi.mocked(countStoreDraftKadhias).mockRejectedValue(new Error('network'));
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(countStoreDraftKadhias).toHaveBeenCalled());
    expect(screen.getByRole('button', { name: 'Commencer ma Kadhia' })).toBeTruthy();
    act(() => screen.getByRole('button').click());
    await waitFor(() => expect(push).toHaveBeenCalledWith('/stores/shop-1/catalog'));
  });

  it('respecte la locale : libellé en arabe quand client:lang = ar', async () => {
    window.localStorage.setItem('client:lang', 'ar');
    vi.mocked(countStoreDraftKadhias).mockResolvedValue(1);
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(screen.getByRole('button', { name: 'أكمل Kadhia' })).toBeTruthy());
    window.localStorage.removeItem('client:lang');
  });

  it('double-clic : ne déclenche qu’une seule navigation', async () => {
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => screen.getByRole('button', { name: 'Commencer ma Kadhia' }));
    act(() => {
      screen.getByRole('button').click();
      screen.getByRole('button').click();
    });
    await waitFor(() => expect(push).toHaveBeenCalledTimes(1));
  });
});
