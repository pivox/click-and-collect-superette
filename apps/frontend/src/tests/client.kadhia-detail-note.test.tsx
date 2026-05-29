import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('next/link', () => ({
  default: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

vi.mock('@/lib/services', () => ({
  fetchKadhia: vi.fn(),
  updateLineQuantity: vi.fn(),
  discardKadhia: vi.fn(),
  patchKadhiaNotes: vi.fn(),
  listMyKadhias: vi.fn(),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import KadhiaDetailPage from '@/app/(client)/kadhia/[kadhiaId]/page';
import { fetchKadhia, patchKadhiaNotes, discardKadhia } from '@/lib/services';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import type { Kadhia } from '@/types';

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client' };

function mockAuth() {
  vi.mocked(useClientAuth).mockReturnValue({
    user: MOCK_USER,
    isLoading: false,
    login: vi.fn(),
    logout: vi.fn(),
  } as unknown as ReturnType<typeof useClientAuth>);
}

function makeDraftKadhia(overrides: Partial<Kadhia> = {}): Kadhia {
  return {
    id: 'k-1',
    shopId: 'shop-1',
    status: 'draft',
    lines: [],
    totalTnd: '0.000',
    notes: null,
    ...overrides,
  };
}

describe('KadhiaDetailPage — note personnelle', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAuth();
  });

  it('affiche le bouton Ajouter quand la Kadhia n\'a pas de note', async () => {
    vi.mocked(fetchKadhia).mockResolvedValue(makeDraftKadhia());
    render(<KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />);
    await waitFor(() => {
      expect(screen.getByText('Ajouter')).toBeTruthy();
    });
  });

  it('affiche la note existante et le bouton Modifier', async () => {
    vi.mocked(fetchKadhia).mockResolvedValue(makeDraftKadhia({ notes: 'courses maison' }));
    render(<KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />);
    await waitFor(() => {
      // La note s'affiche dans le TopBar (titre) et dans la section Note personnelle
      expect(screen.getAllByText('courses maison').length).toBeGreaterThan(0);
      expect(screen.getByText('Modifier')).toBeTruthy();
    });
  });

  it('affiche la note dans le TopBar quand elle est renseignée', async () => {
    vi.mocked(fetchKadhia).mockResolvedValue(makeDraftKadhia({ notes: 'courses bureau' }));
    render(<KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />);
    await waitFor(() => {
      expect(screen.getAllByText('courses bureau').length).toBeGreaterThan(0);
    });
  });

  it('ouvre le formulaire d\'édition au clic sur Ajouter', async () => {
    vi.mocked(fetchKadhia).mockResolvedValue(makeDraftKadhia());
    render(<KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />);
    await waitFor(() => screen.getByText('Ajouter'));

    fireEvent.click(screen.getByText('Ajouter'));

    expect(screen.getByPlaceholderText(/courses maison/i)).toBeTruthy();
    expect(screen.getByText('Enregistrer')).toBeTruthy();
    expect(screen.getByText('Annuler')).toBeTruthy();
  });

  it('enregistre la note et ferme l\'éditeur', async () => {
    const updated = makeDraftKadhia({ notes: 'samedi matin' });
    vi.mocked(fetchKadhia).mockResolvedValue(makeDraftKadhia());
    vi.mocked(patchKadhiaNotes).mockResolvedValue(updated);

    render(<KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />);
    await waitFor(() => screen.getByText('Ajouter'));

    fireEvent.click(screen.getByText('Ajouter'));
    const textarea = screen.getByPlaceholderText(/courses maison/i);
    fireEvent.change(textarea, { target: { value: 'samedi matin' } });
    fireEvent.click(screen.getByText('Enregistrer'));

    await waitFor(() => {
      expect(vi.mocked(patchKadhiaNotes)).toHaveBeenCalledWith('k-1', 'samedi matin');
      expect(screen.getByText('samedi matin')).toBeTruthy();
      expect(screen.queryByText('Enregistrer')).toBeNull();
    });
  });

  it('annule l\'édition sans appeler l\'API', async () => {
    vi.mocked(fetchKadhia).mockResolvedValue(makeDraftKadhia({ notes: 'note initiale' }));
    render(<KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />);
    await waitFor(() => screen.getByText('Modifier'));

    fireEvent.click(screen.getByText('Modifier'));
    fireEvent.click(screen.getByText('Annuler'));

    await waitFor(() => {
      expect(vi.mocked(patchKadhiaNotes)).not.toHaveBeenCalled();
      expect(screen.getAllByText('note initiale').length).toBeGreaterThan(0);
    });
  });

  it('ne montre pas de champ d\'édition pour une Kadhia soumise', async () => {
    vi.mocked(fetchKadhia).mockResolvedValue(
      makeDraftKadhia({ status: 'submitted', notes: 'ma note' }),
    );
    render(<KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />);
    await waitFor(() => {
      expect(screen.getAllByText('ma note').length).toBeGreaterThan(0);
      expect(screen.queryByText('Modifier')).toBeNull();
      expect(screen.queryByText('Ajouter')).toBeNull();
    });
  });

  it('affiche une erreur si la sauvegarde échoue', async () => {
    vi.mocked(fetchKadhia).mockResolvedValue(makeDraftKadhia());
    vi.mocked(patchKadhiaNotes).mockRejectedValue(new Error('Network error'));

    render(<KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />);
    await waitFor(() => screen.getByText('Ajouter'));

    fireEvent.click(screen.getByText('Ajouter'));
    fireEvent.change(screen.getByPlaceholderText(/courses maison/i), {
      target: { value: 'test' },
    });
    fireEvent.click(screen.getByText('Enregistrer'));

    await waitFor(() => {
      expect(screen.getByText(/Impossible d'enregistrer/i)).toBeTruthy();
    });
  });

  it('envoie null à l\'API quand la note ne contient que des espaces', async () => {
    vi.mocked(fetchKadhia).mockResolvedValue(makeDraftKadhia());
    vi.mocked(patchKadhiaNotes).mockResolvedValue(makeDraftKadhia({ notes: null }));

    render(<KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />);
    await waitFor(() => screen.getByText('Ajouter'));

    fireEvent.click(screen.getByText('Ajouter'));
    fireEvent.change(screen.getByPlaceholderText(/courses maison/i), {
      target: { value: '   ' },
    });
    fireEvent.click(screen.getByText('Enregistrer'));

    await waitFor(() => {
      expect(vi.mocked(patchKadhiaNotes)).toHaveBeenCalledWith('k-1', null);
    });
  });

  it('affiche un message d\'erreur quand la suppression échoue', async () => {
    vi.mocked(fetchKadhia).mockResolvedValue(
      makeDraftKadhia({ lines: [{ id: 'l-1', productOffer: { id: 'p-1', productReferenceId: '', nameFr: 'Lait', nameAr: null, brand: '', volume: null, unit: null, priceTnd: '2.000', isAvailable: true, photoUrl: null, category: 'other' }, quantity: 1, unitPriceTnd: '2.000', lineTotalTnd: '2.000' }] }),
    );
    vi.mocked(discardKadhia).mockRejectedValue(new Error('Network error'));
    vi.stubGlobal('confirm', () => true);

    render(<KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />);
    await waitFor(() => screen.getByText('Supprimer cette Kadhia'));

    fireEvent.click(screen.getByText('Supprimer cette Kadhia'));

    await waitFor(() => {
      expect(screen.getByText(/Impossible de supprimer/i)).toBeTruthy();
    });

    vi.unstubAllGlobals();
  });

  it('efface le message d\'erreur de sauvegarde quand l\'utilisateur annule', async () => {
    vi.mocked(fetchKadhia).mockResolvedValue(makeDraftKadhia());
    vi.mocked(patchKadhiaNotes).mockRejectedValue(new Error('Network error'));

    render(<KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />);
    await waitFor(() => screen.getByText('Ajouter'));

    fireEvent.click(screen.getByText('Ajouter'));
    fireEvent.change(screen.getByPlaceholderText(/courses maison/i), {
      target: { value: 'test' },
    });
    fireEvent.click(screen.getByText('Enregistrer'));

    await waitFor(() => {
      expect(screen.getByText(/Impossible d'enregistrer/i)).toBeTruthy();
    });

    fireEvent.click(screen.getByText('Annuler'));

    await waitFor(() => {
      expect(screen.queryByText(/Impossible d'enregistrer/i)).toBeNull();
    });
  });
});
