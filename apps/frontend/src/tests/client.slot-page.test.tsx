import { render, screen } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('@/lib/services', () => ({
  listSlotsForShop: vi.fn(),
  submitKadhia: vi.fn(),
  readLocalKadhia: vi.fn(),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import SlotPage from '@/app/(client)/kadhia/slot/page';
import { listSlotsForShop, readLocalKadhia } from '@/lib/services';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { ClientLocaleProvider } from '@/lib/i18n/ClientLocaleContext';
import type { PickupSlot } from '@/types';

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client' };

function mockAuth() {
  vi.mocked(useClientAuth).mockReturnValue({
    user: MOCK_USER,
    isLoading: false,
    login: vi.fn(),
    logout: vi.fn(),
  } as unknown as ReturnType<typeof useClientAuth>);
}

// A morning slot (09:00 Africa/Tunis) so the "Matin" period group renders.
function morningSlot(): PickupSlot {
  const start = new Date();
  start.setHours(9, 0, 0, 0);
  const end = new Date(start.getTime() + 30 * 60_000);
  return {
    id: 'slot-1',
    startsAt: start.toISOString(),
    endsAt: end.toISOString(),
    label: 'Créneau matin',
    available: true,
  } as PickupSlot;
}

function renderPage() {
  return render(
    <ClientLocaleProvider>
      <SlotPage />
    </ClientLocaleProvider>,
  );
}

describe('SlotPage (S14-004)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockAuth();
    vi.mocked(readLocalKadhia).mockReturnValue({ id: 'k-1', shopId: 'shop-1' } as ReturnType<
      typeof readLocalKadhia
    >);
    vi.mocked(listSlotsForShop).mockResolvedValue([morningSlot()]);
  });

  it('affiche les libellés du créneau en français par défaut', async () => {
    renderPage();
    expect(await screen.findByText('Créneaux disponibles')).toBeTruthy();
    expect(screen.getByText('Créneau de retrait')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Envoyer la commande' })).toBeTruthy();
    // Morning period label rendered above the slot group.
    expect(screen.getByText('Matin')).toBeTruthy();
  });

  it('affiche les libellés du créneau en arabe quand la langue est AR', async () => {
    localStorage.setItem('client:lang', 'ar');
    renderPage();
    expect(await screen.findByText('موعد الاستلام')).toBeTruthy(); // "Créneau de retrait"
    expect(screen.getByText('المواعيد المتاحة')).toBeTruthy(); // "Créneaux disponibles"
    expect(screen.getByText('صباحاً')).toBeTruthy(); // "Matin"
  });
});
