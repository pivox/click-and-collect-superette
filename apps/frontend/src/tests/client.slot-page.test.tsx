import { fireEvent, render, screen } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('@/lib/services', () => ({
  discardKadhia: vi.fn(),
  listSlotsForShop: vi.fn(),
  submitKadhia: vi.fn(),
  readLocalKadhia: vi.fn(),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import SlotPage from '@/app/(client)/kadhia/slot/page';
import { discardKadhia, listSlotsForShop, readLocalKadhia, submitKadhia } from '@/lib/services';
import type { SlotListing } from '@/lib/services/slots.service';
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

function listing(
  slots: PickupSlot[],
  minimumPickupLeadTimeMinutes = 0,
  earliestBookableAt: string | null = null,
): SlotListing {
  return {
    slots,
    bookingPolicy: { minimumPickupLeadTimeMinutes, earliestBookableAt },
  };
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
    vi.mocked(listSlotsForShop).mockResolvedValue(listing([morningSlot()]));
    vi.mocked(discardKadhia).mockResolvedValue(undefined);
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

  it('affiche le blocage suspension sans perdre la Kadhia', async () => {
    vi.mocked(submitKadhia).mockRejectedValue({
      response: { data: { detail: 'STORE_SUSPENDED_FOR_SUBSCRIPTION' } },
    });

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: 'Envoyer la commande' }));

    expect(await screen.findByText('Cette supérette ne prend temporairement plus de nouvelles Kadhia. Votre Kadhia reste disponible.')).toBeTruthy();
    expect(submitKadhia).toHaveBeenCalledWith({
      shopId: 'shop-1',
      pickupSlotId: 'slot-1',
      customerNote: 'Si un produit est absent, remplacer par une marque proche.',
    });
    expect(readLocalKadhia).toHaveBeenCalled();
  });

  it("affiche l'expiration d'une acceptation partielle sans message générique", async () => {
    vi.mocked(submitKadhia).mockRejectedValue({
      response: { data: { detail: 'PARTIAL_ACCEPTANCE_EXPIRED' } },
    });

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: 'Envoyer la commande' }));

    expect(
      await screen.findByText("Le délai pour re-soumettre cette Kadhia modifiée est dépassé. Repars du catalogue avec une nouvelle Kadhia."),
    ).toBeTruthy();
    expect(discardKadhia).toHaveBeenCalledWith('shop-1', { suppressReactivation: true });
    expect(screen.getByRole('button', { name: 'Revenir au catalogue' })).toBeTruthy();
    expect((screen.getByRole('button', { name: 'Envoyer la commande' }) as HTMLButtonElement).disabled).toBe(true);
    expect(screen.queryByText("La commande n'a pas pu être envoyée. Ta Kadhia est conservée, tu peux réessayer.")).toBeNull();
  });

  // ORDER-LEAD-004

  it('affiche le délai de préparation de la supérette quand il est supérieur à zéro', async () => {
    vi.mocked(listSlotsForShop).mockResolvedValue(listing([morningSlot()], 720));

    renderPage();

    expect(
      await screen.findByText('Cette supérette demande au moins 12 h pour préparer une commande.'),
    ).toBeTruthy();
  });

  it("n'affiche pas la ligne de délai quand la politique est à zéro", async () => {
    renderPage();

    await screen.findByText('Créneaux disponibles');
    expect(screen.queryByText(/demande au moins/)).toBeNull();
  });

  it("explique l'absence de créneaux liée au délai, distinctement de l'état vide générique", async () => {
    vi.mocked(listSlotsForShop).mockResolvedValue(listing([], 720));

    renderPage();

    expect(
      await screen.findByText('Aucun rendez-vous n’est encore disponible après ce délai.'),
    ).toBeTruthy();
    expect(screen.getByText('Essaie un autre jour ou reviens plus tard.')).toBeTruthy();
    expect(screen.queryByText('Aucun créneau ce jour.')).toBeNull();
  });

  it("conserve l'état vide générique quand aucune politique n'explique l'absence", async () => {
    vi.mocked(listSlotsForShop).mockResolvedValue(listing([], 0));

    renderPage();

    expect(await screen.findByText('Aucun créneau ce jour.')).toBeTruthy();
    expect(screen.queryByText(/après ce délai/)).toBeNull();
  });

  it('gère un créneau devenu trop proche : message métier, Kadhia et note conservées, créneaux rechargés', async () => {
    vi.mocked(submitKadhia).mockRejectedValue({
      response: { data: { detail: 'PICKUP_SLOT_MINIMUM_LEAD_TIME_NOT_MET' } },
    });

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: 'Envoyer la commande' }));

    expect(
      await screen.findByText(
        'Ce rendez-vous est devenu trop proche pour le temps de préparation demandé par la supérette. Choisis un autre créneau ; ta Kadhia a été conservée.',
      ),
    ).toBeTruthy();
    // No raw backend constant leaked to the UI.
    expect(screen.queryByText(/PICKUP_SLOT_MINIMUM_LEAD_TIME_NOT_MET/)).toBeNull();
    // The Kadhia is preserved: no discard, note untouched, no catalog restart CTA.
    expect(discardKadhia).not.toHaveBeenCalled();
    expect(
      (screen.getByRole('textbox') as HTMLTextAreaElement).value,
    ).toBe('Si un produit est absent, remplacer par une marque proche.');
    expect(screen.queryByRole('button', { name: 'Revenir au catalogue' })).toBeNull();
    // Slots reloaded after the rejection (initial load + reload).
    expect(vi.mocked(listSlotsForShop).mock.calls.length).toBeGreaterThanOrEqual(2);
  });

  it('affiche le message de créneau trop proche en arabe', async () => {
    localStorage.setItem('client:lang', 'ar');
    vi.mocked(submitKadhia).mockRejectedValue({
      response: { data: { detail: 'PICKUP_SLOT_MINIMUM_LEAD_TIME_NOT_MET' } },
    });

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: 'إرسال الطلب' }));

    expect(
      await screen.findByText(/أصبح هذا الموعد قريبًا جدًا/),
    ).toBeTruthy();
  });
});
