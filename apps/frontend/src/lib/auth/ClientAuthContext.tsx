'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import {
  clientLogin as apiClientLogin,
  decodeJwtPayload,
  type ClientUser,
} from '@/lib/services/auth.service';

interface ClientAuthContextValue {
  user: ClientUser | null;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

const ClientAuthContext = createContext<ClientAuthContextValue | null>(null);

export function ClientAuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<ClientUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    const token = localStorage.getItem('jwt_token');
    if (token) {
      try {
        const payload = decodeJwtPayload(token);
        setUser({
          token,
          email: typeof payload.email === 'string' ? payload.email : '',
          name: typeof payload.name === 'string' ? payload.name : '',
        });
      } catch {
        localStorage.removeItem('jwt_token');
      }
    }
    setIsLoading(false);
  }, []);

  const login = async (email: string, password: string) => {
    const clientUser = await apiClientLogin(email, password);
    localStorage.setItem('jwt_token', clientUser.token);
    setUser(clientUser);
    router.push('/');
  };

  const logout = () => {
    localStorage.removeItem('jwt_token');
    setUser(null);
    router.push('/login');
  };

  return (
    <ClientAuthContext.Provider value={{ user, isLoading, login, logout }}>
      {children}
    </ClientAuthContext.Provider>
  );
}

export function useClientAuth(): ClientAuthContextValue {
  const ctx = useContext(ClientAuthContext);
  if (!ctx) throw new Error('useClientAuth must be used inside ClientAuthProvider');
  return ctx;
}
