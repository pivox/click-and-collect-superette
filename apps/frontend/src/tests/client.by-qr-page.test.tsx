import { render, waitFor } from '@testing-library/react';
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

describe('ByQrPage', () => {
  beforeEach(() => {
    replace.mockClear();
    selectStore.mockClear();
    vi.clearAllMocks();
  });

  it('redirige vers le catalogue et auto-sélectionne le store', async () => {
    vi.mocked(getShopBySlug).mockResolvedValue({
      id: 'store-1',
      name: 'Supérette El Amen',
      slug: 'superette-el-amen',
      city: 'Tunis',
      isActive: true,
      address: null,
      phone: null,
    });
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
  });
});
