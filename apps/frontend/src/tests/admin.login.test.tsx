import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminLoginPage from '@/app/admin/login/page';

const login = vi.fn();

vi.mock('@/lib/auth/AdminAuthContext', () => ({
  useAdminAuth: () => ({ login }),
}));

function axiosError(status?: number) {
  return {
    isAxiosError: true,
    response: status ? { status } : undefined,
  };
}

async function submitLoginForm() {
  render(<AdminLoginPage />);

  fireEvent.change(screen.getByLabelText('Email'), {
    target: { value: 'admin@kadhia.tn' },
  });
  fireEvent.change(screen.getByLabelText('Mot de passe'), {
    target: { value: 'wrong-password' },
  });
  fireEvent.click(screen.getByRole('button', { name: 'Se connecter' }));

  await waitFor(() => expect(login).toHaveBeenCalledWith('admin@kadhia.tn', 'wrong-password'));
}

describe('AdminLoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('affiche un message métier sur échec 401', async () => {
    login.mockRejectedValueOnce(axiosError(401));

    await submitLoginForm();

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Email ou mot de passe incorrect.',
    );
    expect(screen.queryByText('Request failed with status code 401')).not.toBeInTheDocument();
  });

  it('affiche un message réservé admin sur échec 403', async () => {
    login.mockRejectedValueOnce(axiosError(403));

    await submitLoginForm();

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Accès réservé aux administrateurs.',
    );
  });
});
