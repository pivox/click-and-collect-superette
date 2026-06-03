import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantStoreProfilePage from '@/app/merchant/parametres/profil/page';
import {
  getMerchantStoreProfile,
  updateMerchantStoreProfile,
} from '@/lib/services/merchant-store-profile.service';

const refresh = vi.fn().mockResolvedValue(undefined);

const merchantContext = {
  merchant: {
    store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
  },
  refresh,
};

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => merchantContext,
}));

vi.mock('@/lib/services/merchant-store-profile.service', () => ({
  getMerchantStoreProfile: vi.fn(),
  updateMerchantStoreProfile: vi.fn(),
}));

const profile = {
  id: 'store-1',
  name: 'Supérette Ezzahra',
  address: '12 Avenue Habib Bourguiba',
  city: 'Ezzahra',
  phone: '+216 71 000 000',
  logoUrl: null,
  coverUrl: null,
  active: true,
};

describe('MerchantStoreProfilePage (MS-002)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(getMerchantStoreProfile).mockResolvedValue({ ...profile });
    vi.mocked(updateMerchantStoreProfile).mockImplementation(async (_storeId, input) => ({
      ...profile,
      ...input,
    }));
  });

  it('loads and prefills the current profile', async () => {
    render(React.createElement(MerchantStoreProfilePage));

    const nameInput = await screen.findByLabelText(/Nom de la supérette/i);
    expect(nameInput).toHaveValue('Supérette Ezzahra');
    expect(screen.getByLabelText(/Adresse/i)).toHaveValue('12 Avenue Habib Bourguiba');
  });

  it('saves trimmed values and clears empty optional fields to null', async () => {
    render(React.createElement(MerchantStoreProfilePage));

    const nameInput = await screen.findByLabelText(/Nom de la supérette/i);
    fireEvent.change(nameInput, { target: { value: '  Supérette Ezzahra Centre  ' } });

    const addressInput = screen.getByLabelText(/Adresse/i);
    fireEvent.change(addressInput, { target: { value: '   ' } });

    fireEvent.click(screen.getByRole('button', { name: /Enregistrer/i }));

    await waitFor(() => {
      expect(updateMerchantStoreProfile).toHaveBeenCalledWith(
        'store-1',
        expect.objectContaining({
          name: 'Supérette Ezzahra Centre',
          address: null,
        }),
      );
    });
    expect(await screen.findByText(/Profil enregistré/i)).toBeInTheDocument();
    // Refresh the merchant context so the shell reflects the new name immediately.
    expect(refresh).toHaveBeenCalled();
  });

  it('blocks submission when the name is empty', async () => {
    render(React.createElement(MerchantStoreProfilePage));

    const nameInput = await screen.findByLabelText(/Nom de la supérette/i);
    fireEvent.change(nameInput, { target: { value: '   ' } });
    fireEvent.click(screen.getByRole('button', { name: /Enregistrer/i }));

    await waitFor(() => {
      expect(screen.getByText(/Le nom de la supérette est obligatoire/i)).toBeInTheDocument();
    });
    expect(updateMerchantStoreProfile).not.toHaveBeenCalled();
  });
});
