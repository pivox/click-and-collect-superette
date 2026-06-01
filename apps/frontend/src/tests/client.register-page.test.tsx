import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ClientRegisterPage from '@/app/(client)/register/page';
import { clientRegister } from '@/lib/services/auth.service';

const routerPush = vi.fn();

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: routerPush, replace: vi.fn() }),
}));

vi.mock('@/lib/services/auth.service', () => ({
  clientRegister: vi.fn(),
}));

describe('ClientRegisterPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('soumet les valeurs présentes dans le DOM même sans événements change React', async () => {
    vi.mocked(clientRegister).mockResolvedValueOnce(undefined);
    render(<ClientRegisterPage />);

    const submitButton = await screen.findByRole('button', {
      name: 'Créer mon compte',
    });
    expect(submitButton).toBeEnabled();

    (screen.getByLabelText('Nom') as HTMLInputElement).value = 'Fatma Ben Ali';
    (screen.getByLabelText('Email') as HTMLInputElement).value = 'fatma@example.tn';
    (screen.getByLabelText('Mot de passe') as HTMLInputElement).value = 'secret123';

    fireEvent.click(submitButton);

    await waitFor(() =>
      expect(clientRegister).toHaveBeenCalledWith(
        'fatma@example.tn',
        'secret123',
        'Fatma Ben Ali',
      ),
    );
    expect(routerPush).toHaveBeenCalledWith('/login');
  });
});
