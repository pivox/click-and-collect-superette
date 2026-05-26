import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StoreSearchCombobox } from '@/components/store/StoreSearchCombobox';
import * as storeSearchService from '@/lib/services/store-search.service';

const mockPush = vi.fn();
vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: mockPush }),
}));

vi.mock('@/lib/services/store-search.service', () => ({
  searchStores: vi.fn(),
}));

function wrapper({ children }: { children: React.ReactNode }) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={qc}>{children}</QueryClientProvider>;
}

describe('StoreSearchCombobox', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the search input', () => {
    render(<StoreSearchCombobox />, { wrapper });
    expect(screen.getByRole('combobox')).toBeInTheDocument();
  });

  it('does not show dropdown when input is empty', () => {
    render(<StoreSearchCombobox />, { wrapper });
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('does not show dropdown when query is 1 character', async () => {
    render(<StoreSearchCombobox />, { wrapper });
    const input = screen.getByRole('combobox');
    fireEvent.focus(input);
    fireEvent.change(input, { target: { value: 'a' } });
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('shows "aucune supérette" message when API returns empty results', async () => {
    vi.mocked(storeSearchService.searchStores).mockResolvedValue({
      items: [],
      total: 0,
    });
    render(<StoreSearchCombobox />, { wrapper });
    const input = screen.getByRole('combobox');
    fireEvent.focus(input);
    fireEvent.change(input, { target: { value: 'xyz' } });
    // waitFor retries until the debounce fires (400ms) and the API promise resolves
    await waitFor(
      () => expect(screen.getByText(/aucune supérette trouvée/i)).toBeInTheDocument(),
      { timeout: 2000 },
    );
  });

  it('displays store name and city for each result', async () => {
    vi.mocked(storeSearchService.searchStores).mockResolvedValue({
      items: [
        {
          store_id: 'uuid-1',
          name: 'Marjé El Amel',
          slug: 'marje-el-amel',
          city: 'Tunis',
          country: 'TN',
          is_active: true,
        },
      ],
      total: 1,
    });
    render(<StoreSearchCombobox />, { wrapper });
    const input = screen.getByRole('combobox');
    fireEvent.focus(input);
    fireEvent.change(input, { target: { value: 'mar' } });
    // waitFor retries until the debounce fires (400ms) and the API promise resolves
    await waitFor(
      () => expect(screen.getByText('Marjé El Amel')).toBeInTheDocument(),
      { timeout: 2000 },
    );
    expect(screen.getByText('Tunis')).toBeInTheDocument();
  });

  it('calls router.push with correct store path when a suggestion is selected', async () => {
    vi.mocked(storeSearchService.searchStores).mockResolvedValue({
      items: [
        {
          store_id: 'uuid-1',
          name: 'Marjé El Amel',
          slug: 'marje-el-amel',
          city: 'Tunis',
          country: 'TN',
          is_active: true,
        },
      ],
      total: 1,
    });
    render(<StoreSearchCombobox />, { wrapper });
    const input = screen.getByRole('combobox');
    fireEvent.focus(input);
    fireEvent.change(input, { target: { value: 'mar' } });
    // Wait for suggestion to appear after debounce + API call
    await waitFor(
      () => expect(screen.getByText('Marjé El Amel')).toBeInTheDocument(),
      { timeout: 2000 },
    );
    fireEvent.mouseDown(screen.getByText('Marjé El Amel').closest('button')!);
    expect(mockPush).toHaveBeenCalledWith('/stores/uuid-1');
  });
});
