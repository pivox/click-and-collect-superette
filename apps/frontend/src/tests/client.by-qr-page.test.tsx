import { render, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const replace = vi.fn();

vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace }),
}));

vi.mock('@/lib/services', () => ({
  getShopBySlug: vi.fn(),
  recordStoreVisit: vi.fn(),
}));

import ByQrPage from '@/app/(client)/stores/by-qr/[qrToken]/page';
import { getShopBySlug, recordStoreVisit } from '@/lib/services';

describe('ByQrPage', () => {
  beforeEach(() => {
    replace.mockClear();
    vi.clearAllMocks();
  });

  it("redirige vers le catalogue sans attendre l'enregistrement de visite", async () => {
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
    expect(recordStoreVisit).toHaveBeenCalledWith('store-1', 'qr_code');
  });
});
