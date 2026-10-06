import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {requestPasswordReset} from '@/lib/services/auth.service';
import ForgotPasswordPage from '@/app/(auth)/forgot-password/page';

const searchParams = { value: new URLSearchParams() };

vi.mock('next/navigation', () => ({
  useSearchParams: () => searchParams.value,
}));

vi.mock('@/lib/services/auth.service', () => ({
  requestPasswordReset: vi.fn(),
}));

describe('ForgotPasswordPage', () => {
  beforeEach(() => {
    searchParams.value = new URLSearchParams();
  });

  it('renvoie vers le login client par défaut', () => {
    render(<ForgotPasswordPage />);

    expect(screen.getByRole('link', { name: 'Retour à la connexion' })).toHaveAttribute(
      'href',
      '/login',
    );
  });

  it('renvoie vers le login marchand quand portal=merchant', () => {
    searchParams.value = new URLSearchParams('portal=merchant');
    render(<ForgotPasswordPage />);

    expect(screen.getByRole('link', { name: 'Retour à la connexion' })).toHaveAttribute(
      'href',
      '/merchant/login',
    );
  });

  it('renvoie vers le login admin quand portal=admin', () => {
    searchParams.value = new URLSearchParams('portal=admin');
    render(<ForgotPasswordPage />);

    expect(screen.getByRole('link', { name: 'Retour à la connexion' })).toHaveAttribute(
      'href',
      '/admin/login',
    );
  });
});

it('reports reset request quota explicitly without claiming success',async()=>{
 vi.mocked(requestPasswordReset).mockRejectedValueOnce({isAxiosError:true,response:{status:429}});
 render(<ForgotPasswordPage/>);
 fireEvent.change(screen.getByLabelText('Adresse email'),{target:{value:'client@example.tn'}});
 fireEvent.click(screen.getByRole('button',{name:'Envoyer le lien'}));
 expect(await screen.findByRole('alert')).toHaveTextContent('Trop de tentatives');
 expect(screen.queryByText(/un lien de réinitialisation a été envoyé/)).toBeNull();
});
