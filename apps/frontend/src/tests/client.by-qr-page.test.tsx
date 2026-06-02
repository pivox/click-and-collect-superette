import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const replace = vi.fn();
const selectStore = vi.fn();

vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace }),
}));

vi.mock('@/lib/services', () => ({
  getShopBySlug: vi.fn(),
  recordStoreVisit: vi.fn(),
}));

vi.mock('@/lib/store/SelectedStoreContext', () => ({
  useSelectedStore: () => ({ selectStore, selectedStore: null, clearStore: vi.fn() }),
}));

import ByQrPage from '@/app/(client)/stores/by-qr/[qrToken]/page';
import { getShopBySlug, recordStoreVisit } from '@/lib/services';

const SHOP = {
  id: 'store-1',
  name: 'Supérette El Amen',
  slug: 'superette-el-amen',
  city: 'Tunis',
  isActive: true,
  address: null,
  phone: null,
};

describe('ByQrPage', () => {
  beforeEach(() => {
    replace.mockClear();
    selectStore.mockClear();
    vi.clearAllMocks();
  });

  it('redirige vers le catalogue et auto-sélectionne le store', async () => {
    vi.mocked(getShopBySlug).mockResolvedValue(SHOP);
    vi.mocked(recordStoreVisit).mockReturnValue(new Promise(() => {}));

    render(<ByQrPage params={{ qrToken: 'demo-superette-el-amen' }} />);

    await waitFor(() => {
      expect(replace).toHaveBeenCalledWith('/stores/store-1/catalog');
    });
    expect(selectStore).toHaveBeenCalledWith({
      id: 'store-1',
      name: 'Supérette El Amen',
      logoLetter: undefined,
    });
    expect(recordStoreVisit).toHaveBeenCalledWith('store-1', 'qr_code');
  });

  it("affiche l'erreur si getShopBySlug résout null (QR inconnu)", async () => {
    vi.mocked(getShopBySlug).mockResolvedValue(null);
    vi.mocked(recordStoreVisit).mockResolvedValue(undefined);

    render(<ByQrPage params={{ qrToken: 'token-inconnu' }} />);

    await waitFor(() =>
      expect(screen.getByText('QR code non reconnu ou supérette indisponible.')).toBeTruthy(),
    );
    expect(replace).not.toHaveBeenCalled();
    expect(selectStore).not.toHaveBeenCalled();
  });

  it("affiche l'erreur si getShopBySlug rejette (erreur réseau)", async () => {
    vi.mocked(getShopBySlug).mockRejectedValue(new Error('network error'));
    vi.mocked(recordStoreVisit).mockResolvedValue(undefined);

    render(<ByQrPage params={{ qrToken: 'token-erreur' }} />);

    await waitFor(() =>
      expect(screen.getByText('QR code non reconnu ou supérette indisponible.')).toBeTruthy(),
    );
    expect(replace).not.toHaveBeenCalled();
  });
});
