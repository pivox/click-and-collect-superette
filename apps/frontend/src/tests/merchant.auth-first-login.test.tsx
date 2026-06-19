import { act, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantClientLayout from '@/app/merchant/MerchantClientLayout';
import { MerchantAuthProvider, useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { getMerchantMe, loginMerchant } from '@/lib/services/merchant-auth.service';

const push = vi.fn();
let pathname = '/merchant';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push }),
  usePathname: () => pathname,
}));

vi.mock('@/components/merchant/MerchantShell', () => ({
  MerchantShell: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="merchant-shell">{children}</div>
  ),
}));

vi.mock('@/lib/services/merchant-auth.service', () => ({
  loginMerchant: vi.fn(),
  getMerchantMe: vi.fn(),
}));

function merchantContext(passwordChangeRequired: boolean) {
  return {
    user_id: 'user-1',
    email: 'marchand@kadhia.tn',
    name: 'Ali Ben Salah',
    first_name: 'Ali',
    last_name: 'Ben Salah',
    phone: '+21620111222',
    store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
    onboarding_completed: true,
    password_change_required: passwordChangeRequired,
  };
}

function LoginTrigger() {
  const { login } = useMerchantAuth();

  return (
    <button type="button" onClick={() => void login('marchand@kadhia.tn', 'temporarySecret123')}>
      Connexion
    </button>
  );
}

describe('MerchantAuthContext première connexion', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    pathname = '/merchant';
    vi.mocked(loginMerchant).mockResolvedValue({
      token: 'merchant-jwt',
      email: 'marchand@kadhia.tn',
    });
  });

  it('redirige vers la première connexion après login marchand avec password_change_required', async () => {
    vi.mocked(getMerchantMe).mockResolvedValue(merchantContext(true));

    render(
      <MerchantAuthProvider>
        <LoginTrigger />
      </MerchantAuthProvider>,
    );

    await act(async () => {
      screen.getByRole('button', { name: 'Connexion' }).click();
    });

    await waitFor(() => {
      expect(push).toHaveBeenCalledWith('/merchant/premiere-connexion');
    });
    expect(localStorage.getItem('merchant_token')).toBe('merchant-jwt');
  });

  it('redirige un accès direct dashboard avec password_change_required vers la première connexion', async () => {
    localStorage.setItem('merchant_token', 'merchant-jwt');
    vi.mocked(getMerchantMe).mockResolvedValue(merchantContext(true));

    render(<MerchantClientLayout>Dashboard marchand</MerchantClientLayout>);

    await waitFor(() => {
      expect(push).toHaveBeenCalledWith('/merchant/premiere-connexion');
    });
    expect(screen.queryByText('Dashboard marchand')).not.toBeInTheDocument();
  });

  it('laisse la page invitation marchand publique sans redirection login', async () => {
    pathname = '/merchant/invitation';
    vi.mocked(getMerchantMe).mockRejectedValue(new Error('No token'));

    render(<MerchantClientLayout>Invitation marchand</MerchantClientLayout>);

    expect(screen.getByText('Invitation marchand')).toBeInTheDocument();
    await waitFor(() => {
      expect(push).not.toHaveBeenCalledWith('/merchant/login');
    });
  });
});
