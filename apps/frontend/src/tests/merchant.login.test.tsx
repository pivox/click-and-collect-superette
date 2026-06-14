import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantLoginPage from '@/app/merchant/login/page';

const login = vi.fn();

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({ login }),
}));

describe('MerchantLoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('propose un lien « Mot de passe oublié » vers /forgot-password', () => {
    render(<MerchantLoginPage />);

    const link = screen.getByRole('link', { name: 'Mot de passe oublié ?' });
    expect(link).toHaveAttribute('href', '/forgot-password?portal=merchant');
  });
});
