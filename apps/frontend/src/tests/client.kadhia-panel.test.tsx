import { render, screen } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { KadhiaPanel } from '@/components/product/KadhiaPanel';
import { ClientLocaleProvider } from '@/lib/i18n/ClientLocaleContext';
import type { Kadhia } from '@/types';

vi.mock('next/link', () => ({
  default: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

function renderPanel(kadhia: Kadhia | null) {
  return render(
    <ClientLocaleProvider>
      <KadhiaPanel kadhia={kadhia} />
    </ClientLocaleProvider>,
  );
}

const mockKadhiaEmpty: Kadhia = {
  id: 'k-1',
  shopId: 'shop-1',
  status: 'draft',
  lines: [],
  totalTnd: '0.000',
};

const mockKadhiaWithLines: Kadhia = {
  id: 'k-1',
  shopId: 'shop-1',
  status: 'draft',
  lines: [
    {
      id: 'l-1',
      productOffer: {
        id: 'p-1',
        productReferenceId: 'ref-1',
        nameFr: 'Lait Vitalait 1L',
        nameAr: null,
        brand: 'Vitalait',
        volume: 1000,
        unit: 'ml',
        priceTnd: '3.000',
        isAvailable: true,
        photoUrl: null,
        category: 'dairy',
      },
      quantity: 2,
      unitPriceTnd: '3.000',
      lineTotalTnd: '6.000',
    },
  ],
  totalTnd: '6.000',
};

describe('KadhiaPanel', () => {
  it('affiche un état vide quand la kadhia est vide', () => {
    renderPanel(mockKadhiaEmpty);
    expect(screen.getByText(/kadhia vide/i)).toBeTruthy();
  });

  it('affiche les lignes quand la kadhia a des articles', () => {
    renderPanel(mockKadhiaWithLines);
    expect(screen.getByText('Lait Vitalait 1L')).toBeTruthy();
    // fr-FR locale: comma decimal separator
    expect(screen.getByText('6,000 TND')).toBeTruthy();
  });

  it('affiche le total', () => {
    renderPanel(mockKadhiaWithLines);
    expect(screen.getByText(/total/i)).toBeTruthy();
  });

  it('le CTA pointe vers /kadhia/slot quand la kadhia a des lignes', () => {
    renderPanel(mockKadhiaWithLines);
    const cta = screen.getByRole('link', { name: /créneau/i });
    expect(cta.getAttribute('href')).toBe('/kadhia/slot');
  });

  it('le CTA est désactivé quand la kadhia est vide', () => {
    renderPanel(mockKadhiaEmpty);
    const btn = screen.getByRole('button', { name: /créneau/i });
    expect(btn).toBeDisabled();
  });
});
