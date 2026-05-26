import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));

vi.mock('@/lib/services/auth.service', () => ({
  clientLogin: vi.fn(),
  clientRegister: vi.fn(),
  decodeJwtPayload: vi.fn(),
}));

import { ClientAuthProvider, useClientAuth } from '@/lib/auth/ClientAuthContext';
import { clientLogin, decodeJwtPayload } from '@/lib/services/auth.service';

function TestConsumer() {
  const auth = useClientAuth();
  if (auth.isLoading) return <span>loading</span>;
  return (
    <div>
      <span data-testid="user">{auth.user?.email ?? 'none'}</span>
      <button onClick={() => auth.login('a@b.com', 'pass')}>login</button>
      <button onClick={() => auth.logout()}>logout</button>
    </div>
  );
}

describe('ClientAuthContext', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  it('user est null sans token en localStorage', async () => {
    render(
      <ClientAuthProvider>
        <TestConsumer />
      </ClientAuthProvider>,
    );
    await waitFor(() => screen.getByTestId('user'));
    expect(screen.getByTestId('user').textContent).toBe('none');
  });

  it('restore le user depuis localStorage au montage', async () => {
    localStorage.setItem('jwt_token', 'tok');
    vi.mocked(decodeJwtPayload).mockReturnValue({
      email: 'u@test.com',
      name: 'User Test',
      roles: ['ROLE_CUSTOMER'],
      exp: Math.floor(Date.now() / 1000) + 3600,
    });
    render(
      <ClientAuthProvider>
        <TestConsumer />
      </ClientAuthProvider>,
    );
    await waitFor(() =>
      expect(screen.getByTestId('user').textContent).toBe('u@test.com'),
    );
  });

  it('login stocke le token et met à jour le user', async () => {
    vi.mocked(clientLogin).mockResolvedValue({
      token: 'new-tok',
      email: 'login@test.com',
      name: 'Login User',
    });
    render(
      <ClientAuthProvider>
        <TestConsumer />
      </ClientAuthProvider>,
    );
    await waitFor(() => screen.getByRole('button', { name: 'login' }));
    await act(async () => {
      screen.getByRole('button', { name: 'login' }).click();
    });
    await waitFor(() =>
      expect(screen.getByTestId('user').textContent).toBe('login@test.com'),
    );
    expect(localStorage.getItem('jwt_token')).toBe('new-tok');
  });

  it('logout vide le token et met user à null', async () => {
    localStorage.setItem('jwt_token', 'tok');
    vi.mocked(decodeJwtPayload).mockReturnValue({
      email: 'u@test.com',
      name: 'U',
      roles: ['ROLE_CUSTOMER'],
      exp: Math.floor(Date.now() / 1000) + 3600,
    });
    render(
      <ClientAuthProvider>
        <TestConsumer />
      </ClientAuthProvider>,
    );
    await waitFor(() =>
      expect(screen.getByTestId('user').textContent).toBe('u@test.com'),
    );
    act(() => screen.getByRole('button', { name: 'logout' }).click());
    await waitFor(() =>
      expect(screen.getByTestId('user').textContent).toBe('none'),
    );
    expect(localStorage.getItem('jwt_token')).toBeNull();
  });
});
