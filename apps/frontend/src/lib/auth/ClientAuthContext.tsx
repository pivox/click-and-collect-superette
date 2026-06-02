'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';
import {
  clientLogin as apiClientLogin,
  decodeJwtPayload,
  type ClientUser,
} from '@/lib/services/auth.service';

const PROFILE_NAME_KEY = 'profile_name_override';

interface ClientAuthContextValue {
  user: ClientUser | null;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
  updateUser: (name: string) => void;
}

const ClientAuthContext = createContext<ClientAuthContextValue | null>(null);

export function ClientAuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<ClientUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('jwt_token');
    if (token) {
      try {
        const payload = decodeJwtPayload(token);
        const exp = typeof payload.exp === 'number' ? payload.exp : 0;
        if (exp <= 0 || Date.now() / 1000 >= exp) {
          localStorage.removeItem('jwt_token');
          localStorage.removeItem(PROFILE_NAME_KEY);
        } else {
          const nameOverride = localStorage.getItem(PROFILE_NAME_KEY);
          setUser({
            token,
            email: typeof payload.email === 'string' ? payload.email : '',
            name: nameOverride ?? (typeof payload.name === 'string' ? payload.name : ''),
          });
        }
      } catch {
        localStorage.removeItem('jwt_token');
        localStorage.removeItem(PROFILE_NAME_KEY);
      }
    }
    setIsLoading(false);
  }, []);

  const login = async (email: string, password: string) => {
    const clientUser = await apiClientLogin(email, password);
    localStorage.setItem('jwt_token', clientUser.token);
    localStorage.removeItem(PROFILE_NAME_KEY);
    setUser(clientUser);
  };

  const logout = () => {
    localStorage.removeItem('jwt_token');
    localStorage.removeItem(PROFILE_NAME_KEY);
    setUser(null);
  };

  const updateUser = (name: string) => {
    localStorage.setItem(PROFILE_NAME_KEY, name);
    setUser((prev) => (prev ? { ...prev, name } : prev));
  };

  return (
    <ClientAuthContext.Provider value={{ user, isLoading, login, logout, updateUser }}>
      {children}
    </ClientAuthContext.Provider>
  );
}

export function useClientAuth(): ClientAuthContextValue {
  const ctx = useContext(ClientAuthContext);
  if (!ctx) throw new Error('useClientAuth must be used inside ClientAuthProvider');
  return ctx;
}
