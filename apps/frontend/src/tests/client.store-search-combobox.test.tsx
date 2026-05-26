import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StoreSearchCombobox } from '@/components/store/StoreSearchCombobox';
import * as storeSearchService from '@/lib/services/store-search.service';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn() }),
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
    expect(screen.getByRole('searchbox')).toBeInTheDocument();
  });

  it('does not show dropdown when input is empty', () => {
    render(<StoreSearchCombobox />, { wrapper });
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
  });

  it('does not show dropdown when query is 1 character', async () => {
    render(<StoreSearchCombobox />, { wrapper });
    const input = screen.getByRole('searchbox');
    fireEvent.focus(input);
    fireEvent.change(input, { target: { value: 'a' } });
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
  });

  it('shows "aucune supérette" message when API returns empty results', async () => {
    vi.mocked(storeSearchService.searchStores).mockResolvedValue({
      items: [],
      total: 0,
    });
    render(<StoreSearchCombobox />, { wrapper });
    const input = screen.getByRole('searchbox');
    fireEvent.focus(input);
    fireEvent.change(input, { target: { value: 'xyz' } });
    await waitFor(() =>
      expect(screen.getByText(/aucune supérette trouvée/i)).toBeInTheDocument(),
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
    const input = screen.getByRole('searchbox');
    fireEvent.focus(input);
    fireEvent.change(input, { target: { value: 'mar' } });
    await waitFor(() =>
      expect(screen.getByText('Marjé El Amel')).toBeInTheDocument(),
    );
    expect(screen.getByText('Tunis')).toBeInTheDocument();
  });
});
