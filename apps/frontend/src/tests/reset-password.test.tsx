import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ResetPasswordPage from '@/app/(auth)/reset-password/page';

const searchParams = { value: new URLSearchParams() };

vi.mock('next/navigation', () => ({
  useSearchParams: () => searchParams.value,
}));

const confirmPasswordReset = vi.fn();

vi.mock('@/lib/services/auth.service', () => ({
  confirmPasswordReset: (...args: unknown[]) => confirmPasswordReset(...args),
}));

async function submitNewPassword() {
  fireEvent.change(screen.getByLabelText('Nouveau mot de passe'), {
    target: { value: 'newSecret123' },
  });
  fireEvent.change(screen.getByLabelText('Confirmer le mot de passe'), {
    target: { value: 'newSecret123' },
  });
  fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le mot de passe' }));
}

describe('ResetPasswordPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    searchParams.value = new URLSearchParams('token=tok');
  });

  it('après succès, propose un lien de connexion vers le bon portail', async () => {
    searchParams.value = new URLSearchParams('token=tok&portal=merchant');
    confirmPasswordReset.mockResolvedValueOnce(undefined);

    render(<ResetPasswordPage />);
    await submitNewPassword();

    const link = await screen.findByRole('link', { name: 'Aller à la connexion' });
    expect(link).toHaveAttribute('href', '/merchant/login');
  });

  it('après succès sans portal, renvoie vers le login client', async () => {
    confirmPasswordReset.mockResolvedValueOnce(undefined);

    render(<ResetPasswordPage />);
    await submitNewPassword();

    await waitFor(() =>
      expect(screen.getByRole('link', { name: 'Aller à la connexion' })).toHaveAttribute(
        'href',
        '/login',
      ),
    );
  });
});
