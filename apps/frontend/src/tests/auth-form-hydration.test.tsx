import { renderToString } from 'react-dom/server';
import { createElement, type ReactElement } from 'react';
import { describe, expect, it, vi } from 'vitest';
import ClientLoginPage from '@/app/(client)/login/page';
import ClientRegisterPage from '@/app/(client)/register/page';
import AdminLoginPage from '@/app/admin/login/page';
import MerchantLoginPage from '@/app/merchant/login/page';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: () => ({ login: vi.fn() }),
}));

vi.mock('@/lib/auth/AdminAuthContext', () => ({
  useAdminAuth: () => ({ login: vi.fn() }),
}));

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({ login: vi.fn() }),
}));

function getSubmitButtonsFromServerHtml(ui: ReactElement): HTMLButtonElement[] {
  const container = document.createElement('div');
  container.innerHTML = renderToString(ui);

  return Array.from(container.querySelectorAll('button[type="submit"]'));
}

describe('auth forms hydration guard', () => {
  it.each([
    ['client login', () => createElement(ClientLoginPage)],
    ['client register', () => createElement(ClientRegisterPage)],
    ['admin login', () => createElement(AdminLoginPage)],
    ['merchant login', () => createElement(MerchantLoginPage)],
  ])('désactive le bouton submit avant hydratation pour %s', (_label, createUi) => {
    const buttons = getSubmitButtonsFromServerHtml(createUi());

    expect(buttons).toHaveLength(1);
    expect(buttons[0]).toBeDisabled();
  });
});
