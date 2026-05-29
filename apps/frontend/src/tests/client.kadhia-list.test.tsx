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
  listMyKadhias: vi.fn(),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import MesKadhiasPage from '@/app/(client)/kadhia/page';
import { listMyKadhias } from '@/lib/services';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client' };

function mockAuth(user: typeof MOCK_USER | null, isLoading = false) {
  vi.mocked(useClientAuth).mockReturnValue({
    user,
    isLoading,
    login: vi.fn(),
    logout: vi.fn(),
  } as unknown as ReturnType<typeof useClientAuth>);
}

const emptyResult = { items: [], total: 0, page: 1, pages: 1 };

function makeDraftItem() {
  return {
    id: 'k-draft-1',
    storeId: 'store-1',
    storeName: 'Épicerie Centrale',
    status: 'draft',
    linesCount: 3,
    totalTnd: '8.500',
    updatedAt: new Date(Date.now() - 30 * 60_000).toISOString(),
  };
}

function makeSubmittedItem() {
  return {
    id: 'k-sub-1',
    storeId: 'store-1',
    storeName: 'Épicerie du Marché',
    status: 'submitted',
    linesCount: 2,
    totalTnd: '5.200',
    updatedAt: new Date(Date.now() - 2 * 3600_000).toISOString(),
  };
}

describe('MesKadhiasPage (list)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAuth(MOCK_USER);
  });

  it('affiche un squelette puis l\'état vide quand aucune Kadhia en cours', async () => {
    vi.mocked(listMyKadhias).mockResolvedValue(emptyResult);
    render(<MesKadhiasPage />);
    await waitFor(() => {
      expect(screen.getByText(/Aucune Kadhia en cours/i)).toBeTruthy();
    });
  });

  it('affiche une carte de Kadhia brouillon avec bouton Continuer', async () => {
    vi.mocked(listMyKadhias).mockResolvedValue({ items: [makeDraftItem()], total: 1, page: 1, pages: 1 });
    render(<MesKadhiasPage />);
    await waitFor(() => {
      expect(screen.getByText('Épicerie Centrale')).toBeTruthy();
      expect(screen.getByText(/Continuer/i)).toBeTruthy();
    });
    const link = screen.getByRole('link', { name: /Continuer/i });
    expect(link.getAttribute('href')).toBe('/kadhia/k-draft-1');
  });

  it('affiche le badge Brouillon pour les Kadhia draft', async () => {
    vi.mocked(listMyKadhias).mockResolvedValue({ items: [makeDraftItem()], total: 1, page: 1, pages: 1 });
    render(<MesKadhiasPage />);
    await waitFor(() => {
      expect(screen.getByText('Brouillon')).toBeTruthy();
    });
  });

  it('affiche une carte de Kadhia soumise avec bouton Voir', async () => {
    vi.mocked(listMyKadhias).mockResolvedValueOnce(emptyResult); // draft tab initial
    render(<MesKadhiasPage />);
    await waitFor(() => screen.getByText(/Aucune Kadhia en cours/i));

    vi.mocked(listMyKadhias).mockResolvedValue({ items: [makeSubmittedItem()], total: 1, page: 1, pages: 1 });
    const envoyeesTab = screen.getByText('Envoyées');
    fireEvent.click(envoyeesTab);

    await waitFor(() => {
      expect(screen.getByText('Épicerie du Marché')).toBeTruthy();
      expect(screen.getByText(/Voir/i)).toBeTruthy();
    });
    const link = screen.getByRole('link', { name: /Voir/i });
    expect(link.getAttribute('href')).toBe('/kadhia/k-sub-1');
  });

  it('affiche l\'état vide Envoyées quand aucune Kadhia soumise', async () => {
    vi.mocked(listMyKadhias).mockResolvedValue(emptyResult);
    render(<MesKadhiasPage />);
    await waitFor(() => screen.getByText(/Aucune Kadhia en cours/i));

    const envoyeesTab = screen.getByText('Envoyées');
    fireEvent.click(envoyeesTab);

    await waitFor(() => {
      expect(screen.getByText(/Aucune Kadhia envoyée/i)).toBeTruthy();
    });
  });

  it('appelle listMyKadhias avec "draft" au chargement initial', async () => {
    vi.mocked(listMyKadhias).mockResolvedValue(emptyResult);
    render(<MesKadhiasPage />);
    await waitFor(() => {
      expect(vi.mocked(listMyKadhias)).toHaveBeenCalledWith('draft');
    });
  });

  it('retourne null pendant le chargement de l\'auth', () => {
    mockAuth(null, true);
    vi.mocked(listMyKadhias).mockResolvedValue(emptyResult);
    const { container } = render(<MesKadhiasPage />);
    expect(container.firstChild).toBeNull();
  });

  it('affiche la note personnelle comme titre si elle est renseignée', async () => {
    const itemWithNote = { ...makeDraftItem(), notes: 'courses maison' };
    vi.mocked(listMyKadhias).mockResolvedValue({ items: [itemWithNote], total: 1, page: 1, pages: 1 });
    render(<MesKadhiasPage />);
    await waitFor(() => {
      expect(screen.getByText('courses maison')).toBeTruthy();
      expect(screen.getByText('Épicerie Centrale')).toBeTruthy();
    });
  });

  it('affiche le nom de la supérette comme titre quand aucune note n\'est présente', async () => {
    vi.mocked(listMyKadhias).mockResolvedValue({ items: [makeDraftItem()], total: 1, page: 1, pages: 1 });
    render(<MesKadhiasPage />);
    await waitFor(() => {
      expect(screen.getByText('Épicerie Centrale')).toBeTruthy();
      // Seul le store name s'affiche comme titre — pas de duplicate
      const titleEl = screen.getAllByText('Épicerie Centrale');
      expect(titleEl.length).toBe(1);
    });
  });
});
