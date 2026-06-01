import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { renderToString } from 'react-dom/server';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CatalogPage from '@/app/(client)/stores/[shopId]/catalog/page';
import {
  createKadhia,
  getCurrentKadhia,
  getShop,
  listCatalog,
} from '@/lib/services';
import type { Kadhia, Shop } from '@/types';

vi.mock('@/lib/services', () => ({
  activateKadhia: vi.fn(),
  addLine: vi.fn(),
  createKadhia: vi.fn(),
  getCurrentKadhia: vi.fn(),
  getShop: vi.fn(),
  listCatalog: vi.fn(),
}));

const SHOP = {
  id: 'store-1',
  name: 'Supérette El Amal',
  slug: 'superette-el-amal',
  address: null,
  city: 'Tunis',
  phone: null,
  isActive: true,
} satisfies Shop;

const EMPTY_KADHIA = {
  id: 'kadhia-1',
  shopId: 'store-1',
  status: 'draft' as const,
  lines: [],
  totalTnd: '0.000',
  orderId: null,
  notes: null,
} satisfies Kadhia;

describe('CatalogPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listCatalog).mockResolvedValue([]);
    vi.mocked(getCurrentKadhia).mockResolvedValue({ type: 'none' });
    vi.mocked(getShop).mockResolvedValue(SHOP);
  });

  it('désactive Commencer dans le HTML serveur avant hydratation', () => {
    const container = document.createElement('div');
    container.innerHTML = renderToString(
      <CatalogPage params={{ shopId: 'store-1' }} />,
    );

    const startButton = Array.from(container.querySelectorAll('button'))
      .find((button) => button.textContent?.includes('Préparation'));

    expect(startButton).toBeInstanceOf(HTMLButtonElement);
    expect(startButton).toBeDisabled();
  });

  it('permet de commencer une Kadhia après hydratation', async () => {
    vi.mocked(createKadhia).mockResolvedValue(EMPTY_KADHIA);
    render(<CatalogPage params={{ shopId: 'store-1' }} />);

    const startButton = await screen.findByRole('button', { name: 'Commencer' });
    expect(startButton).toBeEnabled();

    fireEvent.click(startButton);

    await waitFor(() => expect(createKadhia).toHaveBeenCalledWith('store-1'));
  });
});
