import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));

vi.mock('@/lib/services/auth.service', () => ({
  clientLogin: vi.fn(),
  clientRegister: vi.fn(),
  getClientProfile: vi.fn(),
  decodeJwtPayload: vi.fn(),
}));

import { ClientAuthProvider, useClientAuth } from '@/lib/auth/ClientAuthContext';
import { clientLogin, decodeJwtPayload, getClientProfile } from '@/lib/services/auth.service';

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
    vi.mocked(getClientProfile).mockRejectedValue(new Error('offline'));
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

it('does not restore another role into the client portal',async()=>{
 localStorage.setItem('jwt_token','wrong-role');
 vi.mocked(decodeJwtPayload).mockReturnValue({roles:['ROLE_ADMIN'],exp:Date.now()/1000+3600,email:'admin@example.tn'});
 render(<ClientAuthProvider><TestConsumer/></ClientAuthProvider>);
 await waitFor(()=>expect(screen.getByTestId('user').textContent).toBe('none'));
 expect(localStorage.getItem('jwt_token')).toBeNull();
});

it('restores the profile name from the server when the JWT contains no name',async()=>{
 localStorage.setItem('jwt_token','token-without-name');
 localStorage.setItem('profile_name_override','Old cached name');
 vi.mocked(decodeJwtPayload).mockReturnValue({roles:['ROLE_CUSTOMER'],exp:Date.now()/1000+3600,email:'client@example.tn'});
 vi.mocked(getClientProfile).mockResolvedValue({email:'client@example.tn',name:'Recette Web'});
 function ProfileName(){const {user}=useClientAuth();return <span>{user?.name??'none'}</span>;}
 render(<ClientAuthProvider><ProfileName/></ClientAuthProvider>);
 expect(await screen.findByText('Recette Web')).toBeTruthy();
 expect(getClientProfile).toHaveBeenCalled();
});

it('ignores a late profile response after logout',async()=>{
 localStorage.setItem('jwt_token','client-token');
 vi.mocked(decodeJwtPayload).mockReturnValue({roles:['ROLE_CUSTOMER'],exp:Date.now()/1000+3600,email:'client@example.tn'});
 let finish!:(profile:{name:string;email:string})=>void;
 vi.mocked(getClientProfile).mockReturnValue(new Promise(resolve=>{finish=resolve;}));
 function LogoutWhileLoading(){const {user,logout}=useClientAuth();return <><span>{user?.name??'none'}</span><button onClick={logout}>stop</button></>;}
 render(<ClientAuthProvider><LogoutWhileLoading/></ClientAuthProvider>);
 act(()=>screen.getByText('stop').click());
 await act(async()=>{finish({name:'Ancien compte',email:'client@example.tn'});});
 expect(screen.getByText('none')).toBeTruthy();
 expect(localStorage.getItem('jwt_token')).toBeNull();
});
