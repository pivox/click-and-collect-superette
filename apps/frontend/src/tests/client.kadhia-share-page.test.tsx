import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push }),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

vi.mock('@/lib/services', () => ({
  joinKadhiaShareLink: vi.fn(),
}));

import KadhiaSharePage from '@/app/(client)/kadhia/share/[token]/page';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { joinKadhiaShareLink } from '@/lib/services';
import { ClientLocaleProvider } from '@/lib/i18n/ClientLocaleContext';

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client' };

function renderPage(token = 'share-token') {
  return render(
    <ClientLocaleProvider>
      <KadhiaSharePage params={{ token }} />
    </ClientLocaleProvider>,
  );
}

describe('KadhiaSharePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('redirige vers login avec redirect si le client est anonyme', async () => {
    vi.mocked(useClientAuth).mockReturnValue({
      user: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
    } as unknown as ReturnType<typeof useClientAuth>);

    renderPage('tok-1');

    await waitFor(() => {
      expect(push).toHaveBeenCalledWith('/login?redirect=/kadhia/share/tok-1');
    });
    expect(joinKadhiaShareLink).not.toHaveBeenCalled();
  });

  it('rejoint la Kadhia puis redirige vers son détail si le client est connecté', async () => {
    vi.mocked(useClientAuth).mockReturnValue({
      user: MOCK_USER,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
    } as unknown as ReturnType<typeof useClientAuth>);
    vi.mocked(joinKadhiaShareLink).mockResolvedValue({
      kadhiaId: 'k-1',
      storeId: 'store-1',
      joined: true,
    });

    renderPage('tok-1');

    await waitFor(() => {
      expect(joinKadhiaShareLink).toHaveBeenCalledWith('tok-1');
      expect(push).toHaveBeenCalledWith('/kadhia/k-1');
    });
  });

  it('affiche un message de lien invalide ou expiré sur 404', async () => {
    vi.mocked(useClientAuth).mockReturnValue({
      user: MOCK_USER,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
    } as unknown as ReturnType<typeof useClientAuth>);
    vi.mocked(joinKadhiaShareLink).mockRejectedValue({ response: { status: 404 } });

    renderPage('tok-expired');

    await waitFor(() => {
      expect(screen.getByText('Lien de partage invalide ou expiré.')).toBeTruthy();
    });
  });
});
