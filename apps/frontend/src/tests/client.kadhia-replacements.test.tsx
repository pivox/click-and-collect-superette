import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useSearchParams: () => ({ get: () => null }),
}));

vi.mock('next/link', () => ({
  default: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

vi.mock('@/lib/services', () => ({
  addLine: vi.fn(),
  fetchKadhia: vi.fn(),
  fetchKadhiaReplacements: vi.fn(),
  updateLineQuantity: vi.fn(),
  discardKadhia: vi.fn(),
  patchKadhiaNotes: vi.fn(),
  listMyKadhias: vi.fn(),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import KadhiaDetailPage from '@/app/(client)/kadhia/[kadhiaId]/page';
import {
  addLine,
  fetchKadhia,
  fetchKadhiaReplacements,
  updateLineQuantity,
} from '@/lib/services';
import type { KadhiaReplacementLine } from '@/lib/services/kadhia.service';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { ClientLocaleProvider } from '@/lib/i18n/ClientLocaleContext';
import type { Kadhia, ProductOffer } from '@/types';

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client' };

function makeOffer(id: string, nameFr: string, isAvailable = true): ProductOffer {
  return {
    id,
    productReferenceId: `ref-${id}`,
    nameFr,
    nameAr: null,
    brand: '',
    volume: null,
    unit: null,
    priceTnd: '2.000',
    isAvailable,
    photoUrl: null,
    category: 'laitages',
  };
}

function makeKadhia(overrides: Partial<Kadhia> = {}): Kadhia {
  return {
    id: 'k-1',
    shopId: 'shop-1',
    status: 'draft',
    lines: [
      {
        id: 'mp-old',
        productOffer: makeOffer('mp-old', 'Lait Délice', false),
        quantity: 2,
        unitPriceTnd: '2.000',
        lineTotalTnd: '4.000',
      },
    ],
    totalTnd: '4.000',
    notes: null,
    ...overrides,
  };
}

const REPLACEMENT_LINE: KadhiaReplacementLine = {
  lineId: 'line-1',
  merchantProductId: 'mp-old',
  productName: 'Lait Délice',
  quantity: 2,
  alternatives: [makeOffer('mp-new', 'Lait Vitalait')],
};

function renderDetail() {
  return render(
    <ClientLocaleProvider>
      <KadhiaDetailPage params={{ kadhiaId: 'k-1' }} />
    </ClientLocaleProvider>,
  );
}

describe('KadhiaDetailPage — remplacements produits indisponibles', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useClientAuth).mockReturnValue({
      user: MOCK_USER,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
    } as unknown as ReturnType<typeof useClientAuth>);
    vi.mocked(fetchKadhia).mockResolvedValue(makeKadhia());
    vi.mocked(fetchKadhiaReplacements).mockResolvedValue([REPLACEMENT_LINE]);
  });

  it('affiche le badge rupture et le bloc de remplacement', async () => {
    renderDetail();

    expect(await screen.findByText('Produits indisponibles')).toBeInTheDocument();
    expect(screen.getByText('Rupture')).toBeInTheDocument();
    expect(screen.getByText(/n'est plus disponible/)).toBeInTheDocument();
    expect(screen.getByText('Lait Vitalait')).toBeInTheDocument();
    expect(fetchKadhiaReplacements).toHaveBeenCalledWith('k-1');
  });

  it('remplace la ligne : ajout de la nouvelle puis suppression de l’ancienne', async () => {
    const replacedKadhia = makeKadhia({
      lines: [
        {
          id: 'mp-new',
          productOffer: makeOffer('mp-new', 'Lait Vitalait'),
          quantity: 2,
          unitPriceTnd: '2.100',
          lineTotalTnd: '4.200',
        },
      ],
      totalTnd: '4.200',
    });
    vi.mocked(addLine).mockResolvedValue(replacedKadhia);
    vi.mocked(updateLineQuantity).mockResolvedValue(replacedKadhia);
    vi.mocked(fetchKadhiaReplacements)
      .mockResolvedValueOnce([REPLACEMENT_LINE])
      .mockResolvedValueOnce([]);

    renderDetail();

    const replaceButton = await screen.findByRole('button', { name: 'Remplacer' });
    fireEvent.click(replaceButton);

    await waitFor(() => {
      expect(addLine).toHaveBeenCalledWith(
        'shop-1',
        'k-1',
        expect.objectContaining({ id: 'mp-new' }),
        2,
      );
    });
    // L'ajout précède toujours la suppression pour ne jamais perdre la ligne.
    expect(updateLineQuantity).toHaveBeenCalledWith('shop-1', 'k-1', 'mp-old', 0);
    expect(
      vi.mocked(addLine).mock.invocationCallOrder[0],
    ).toBeLessThan(vi.mocked(updateLineQuantity).mock.invocationCallOrder[0]);

    await waitFor(() => {
      expect(screen.queryByText('Produits indisponibles')).not.toBeInTheDocument();
    });
    expect(screen.getByText('Lait Vitalait')).toBeInTheDocument();
  });

  it("affiche l'erreur si le remplacement échoue", async () => {
    vi.mocked(addLine).mockRejectedValue(new Error('boom'));

    renderDetail();

    const replaceButton = await screen.findByRole('button', { name: 'Remplacer' });
    fireEvent.click(replaceButton);

    expect(await screen.findByText('Le remplacement a échoué. Réessaie.')).toBeInTheDocument();
    expect(updateLineQuantity).not.toHaveBeenCalled();
  });

  it('indique quand aucune alternative n’existe', async () => {
    vi.mocked(fetchKadhiaReplacements).mockResolvedValue([
      { ...REPLACEMENT_LINE, alternatives: [] },
    ]);

    renderDetail();

    expect(
      await screen.findByText('Aucune alternative disponible dans cette supérette.'),
    ).toBeInTheDocument();
  });

  it('recharge les remplacements après un changement de quantité', async () => {
    const updatedKadhia = makeKadhia();
    updatedKadhia.lines[0].quantity = 3;
    vi.mocked(updateLineQuantity).mockResolvedValue(updatedKadhia);

    renderDetail();

    await screen.findByText('Produits indisponibles');
    const callsBefore = vi.mocked(fetchKadhiaReplacements).mock.calls.length;

    fireEvent.click(screen.getByRole('button', { name: 'Augmenter la quantité' }));

    // La carte de remplacement porte la quantité — elle doit suivre la ligne.
    await waitFor(() => {
      expect(vi.mocked(fetchKadhiaReplacements).mock.calls.length).toBeGreaterThan(callsBefore);
    });
  });

  it('ne charge pas les remplacements pour une Kadhia non-draft', async () => {
    vi.mocked(fetchKadhia).mockResolvedValue(makeKadhia({ status: 'submitted' }));

    renderDetail();

    expect(await screen.findByText('Lait Délice')).toBeInTheDocument();
    expect(fetchKadhiaReplacements).not.toHaveBeenCalled();
    expect(screen.queryByText('Produits indisponibles')).not.toBeInTheDocument();
  });
});
