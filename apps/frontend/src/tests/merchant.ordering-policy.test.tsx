import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantOrderingPolicyPage from '@/app/merchant/parametres/delai-retrait/page';
import { MerchantLocaleProvider } from '@/lib/i18n/MerchantLocaleContext';
import {
  getMerchantOrderingPolicy,
  updateMerchantOrderingPolicy,
} from '@/lib/services/merchant-ordering-policy.service';

const refresh = vi.fn();

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({
    merchant: {
      id: 'merchant-1',
      email: 'merchant@example.test',
      store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
    },
    refresh,
  }),
}));

vi.mock('@/lib/services/merchant-ordering-policy.service', () => ({
  getMerchantOrderingPolicy: vi.fn(),
  updateMerchantOrderingPolicy: vi.fn(),
}));

function renderPage() {
  return render(
    <MerchantLocaleProvider>
      <MerchantOrderingPolicyPage />
    </MerchantLocaleProvider>,
  );
}

async function renderLoaded() {
  renderPage();
  await screen.findByRole('button', { name: /^Enregistrer$/i });
}

describe('MerchantOrderingPolicyPage (ORDER-LEAD-003)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    vi.mocked(getMerchantOrderingPolicy).mockResolvedValue({
      storeId: 'store-1',
      minimumPickupLeadTimeMinutes: 0,
      updatedAt: null,
    });
    vi.mocked(updateMerchantOrderingPolicy).mockResolvedValue({
      storeId: 'store-1',
      minimumPickupLeadTimeMinutes: 720,
      updatedAt: '2026-09-30T10:00:00+00:00',
    });
  });

  it('loads the current value and disables save while nothing changed', async () => {
    await renderLoaded();

    expect(getMerchantOrderingPolicy).toHaveBeenCalledWith('store-1');
    expect(screen.getByRole('radio', { name: /Aucun délai/i })).toBeChecked();
    expect(screen.getByRole('button', { name: /^Enregistrer$/i })).toBeDisabled();
  });

  it('saves a preset value and shows the persisted state', async () => {
    await renderLoaded();

    fireEvent.click(screen.getByRole('radio', { name: /12 heures/i }));
    const save = screen.getByRole('button', { name: /^Enregistrer$/i });
    expect(save).toBeEnabled();
    fireEvent.click(save);

    await waitFor(() => expect(updateMerchantOrderingPolicy).toHaveBeenCalledWith('store-1', 720));
    expect(await screen.findByText(/Réglage enregistré/i)).toBeInTheDocument();
    expect(screen.getByText(/Dernière modification/i)).toBeInTheDocument();
    // Once persisted, the value is no longer dirty.
    expect(screen.getByRole('button', { name: /^Enregistrer$/i })).toBeDisabled();
  });

  it('selects the custom choice when the stored value is not a preset', async () => {
    vi.mocked(getMerchantOrderingPolicy).mockResolvedValue({
      storeId: 'store-1',
      minimumPickupLeadTimeMinutes: 90,
      updatedAt: '2026-09-29T08:00:00+00:00',
    });

    await renderLoaded();

    expect(screen.getByRole('radio', { name: /Personnalisé/i })).toBeChecked();
    expect(screen.getByLabelText('Heures')).toHaveValue(1);
    expect(screen.getByLabelText('Minutes')).toHaveValue(30);
  });

  it('converts custom hours and minutes into total minutes', async () => {
    await renderLoaded();

    fireEvent.click(screen.getByRole('radio', { name: /Personnalisé/i }));
    fireEvent.change(screen.getByLabelText('Heures'), { target: { value: '2' } });
    fireEvent.change(screen.getByLabelText('Minutes'), { target: { value: '15' } });

    expect(screen.getByText(/Soit 135 minutes/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /^Enregistrer$/i }));
    await waitFor(() => expect(updateMerchantOrderingPolicy).toHaveBeenCalledWith('store-1', 135));
  });

  it('accepts the maximum value of 7 days', async () => {
    await renderLoaded();

    fireEvent.click(screen.getByRole('radio', { name: /Personnalisé/i }));
    fireEvent.change(screen.getByLabelText('Heures'), { target: { value: '168' } });
    fireEvent.change(screen.getByLabelText('Minutes'), { target: { value: '0' } });

    fireEvent.click(screen.getByRole('button', { name: /^Enregistrer$/i }));
    await waitFor(() =>
      expect(updateMerchantOrderingPolicy).toHaveBeenCalledWith('store-1', 10080),
    );
  });

  it('blocks values above 7 days with a business message and no API call', async () => {
    await renderLoaded();

    fireEvent.click(screen.getByRole('radio', { name: /Personnalisé/i }));
    fireEvent.change(screen.getByLabelText('Heures'), { target: { value: '169' } });

    expect(
      screen.getByText(/Le délai doit être compris entre 0 minute et 7 jours/i),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Enregistrer$/i })).toBeDisabled();
    expect(updateMerchantOrderingPolicy).not.toHaveBeenCalled();
  });

  it('shows the Tunis preview with the non-retroactivity note', async () => {
    await renderLoaded();

    fireEvent.click(screen.getByRole('radio', { name: /12 heures/i }));

    const preview = screen.getByText(/si une commande est envoyée aujourd’hui à/i);
    expect(preview.textContent).not.toContain('{from}');
    expect(preview.textContent).not.toContain('{to}');
    expect(screen.getByText(/borne théorique/i)).toBeInTheDocument();
    expect(
      screen.getByText(/Les commandes et rendez-vous déjà réservés ne seront pas modifiés/i),
    ).toBeInTheDocument();
  });

  it('warns for high values without blocking them', async () => {
    await renderLoaded();

    fireEvent.click(screen.getByRole('radio', { name: /48 heures/i }));

    expect(screen.getByText(/Délai élevé/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Enregistrer$/i })).toBeEnabled();
  });

  it('prevents double submit while a save is in flight', async () => {
    let resolveRequest: (value: {
      storeId: string;
      minimumPickupLeadTimeMinutes: number;
      updatedAt: string | null;
    }) => void = () => {};
    vi.mocked(updateMerchantOrderingPolicy).mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveRequest = resolve;
        }),
    );

    await renderLoaded();

    fireEvent.click(screen.getByRole('radio', { name: '2 heures' }));
    fireEvent.click(screen.getByRole('button', { name: /^Enregistrer$/i }));

    const savingButton = await screen.findByRole('button', { name: /Enregistrement…/i });
    expect(savingButton).toBeDisabled();
    fireEvent.click(savingButton);
    expect(updateMerchantOrderingPolicy).toHaveBeenCalledTimes(1);

    resolveRequest({ storeId: 'store-1', minimumPickupLeadTimeMinutes: 120, updatedAt: null });
    await screen.findByText(/Réglage enregistré/i);
  });

  it('keeps the saved value visible after a 422 error', async () => {
    vi.mocked(updateMerchantOrderingPolicy).mockRejectedValue({ response: { status: 422 } });

    await renderLoaded();

    fireEvent.click(screen.getByRole('radio', { name: /1 heure$/i }));
    fireEvent.click(screen.getByRole('button', { name: /^Enregistrer$/i }));

    expect(await screen.findByText(/La valeur envoyée est invalide/i)).toBeInTheDocument();
    expect(screen.queryByText(/Réglage enregistré/i)).not.toBeInTheDocument();

    // Cancel restores the last saved server value.
    fireEvent.click(screen.getByRole('button', { name: /Annuler/i }));
    expect(screen.getByRole('radio', { name: /Aucun délai/i })).toBeChecked();
  });

  it('shows a dedicated message and refreshes the session on 403', async () => {
    vi.mocked(updateMerchantOrderingPolicy).mockRejectedValue({ response: { status: 403 } });

    await renderLoaded();

    fireEvent.click(screen.getByRole('radio', { name: /1 heure$/i }));
    fireEvent.click(screen.getByRole('button', { name: /^Enregistrer$/i }));

    expect(await screen.findByText(/n’est plus actif/i)).toBeInTheDocument();
    expect(refresh).toHaveBeenCalled();
  });

  it('shows a network error message and a retry action when loading fails', async () => {
    vi.mocked(getMerchantOrderingPolicy).mockRejectedValueOnce(new Error('network'));

    renderPage();

    expect(await screen.findByRole('alert')).toHaveTextContent(/Impossible de charger/i);
    fireEvent.click(screen.getByRole('button', { name: /Réessayer/i }));
    await screen.findByRole('button', { name: /^Enregistrer$/i });
  });

  it('renders in Arabic with RTL direction', async () => {
    localStorage.setItem('merchant:lang', 'ar');

    const { container } = renderPage();
    await screen.findByRole('button', { name: /حفظ/i });

    expect(container.querySelector('[dir="rtl"]')).not.toBeNull();
    expect(screen.getByText(/الوقت الأدنى اللازم قبل الاستلام/i)).toBeInTheDocument();
  });
});
