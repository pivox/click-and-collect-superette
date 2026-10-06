'use client';

import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import {
  clientLogin as apiClientLogin,
  decodeJwtPayload,
  getClientProfile,
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
  const generation = useRef(0);

  const loadProfile = useCallback(async (fallback: ClientUser, current: number) => {
    let resolved = fallback;
    try {
      const profile = await getClientProfile();
      resolved = { ...fallback, name: profile.name, email: profile.email };
    } catch (error) {
      if (current !== generation.current) return;
      const status = (error as { response?: { status?: number } })?.response?.status;
      if (status === 401 || status === 403) {
        localStorage.removeItem('jwt_token');
        localStorage.removeItem(PROFILE_NAME_KEY);
        setUser(null);
        throw error;
      }
      // A transient profile request failure must not destroy a usable session.
    }
    if (current !== generation.current) return;
    setUser(resolved);
  }, []);

  useEffect(() => {
    const current = ++generation.current;
    const token = localStorage.getItem('jwt_token');
    if (!token) {
      setIsLoading(false);
      return;
    }
    try {
      const payload = decodeJwtPayload(token);
      const exp = typeof payload.exp === 'number' ? payload.exp : 0;
      if (exp <= Date.now() / 1000 || !Array.isArray(payload.roles) || !payload.roles.includes('ROLE_CUSTOMER')) {
        throw new Error('Invalid customer session');
      }
      const fallback = {
        token,
        email: typeof payload.email === 'string' ? payload.email : '',
        name: localStorage.getItem(PROFILE_NAME_KEY) ?? (typeof payload.name === 'string' ? payload.name : ''),
      };
      void loadProfile(fallback, current)
        .catch(() => undefined)
        .finally(() => { if (current === generation.current) setIsLoading(false); });
    } catch {
      localStorage.removeItem('jwt_token');
      localStorage.removeItem(PROFILE_NAME_KEY);
      setIsLoading(false);
    }
    return () => { generation.current += 1; };
  }, [loadProfile]);

  const login = async (email: string, password: string) => {
    const current = ++generation.current;
    const clientUser = await apiClientLogin(email, password);
    if (current !== generation.current) return;
    localStorage.setItem('jwt_token', clientUser.token);
    localStorage.removeItem(PROFILE_NAME_KEY);
    try {
      await loadProfile(clientUser, current);
    } finally {
      if (current === generation.current) setIsLoading(false);
    }
  };

  const logout = () => {
    generation.current += 1;
    localStorage.removeItem('jwt_token');
    localStorage.removeItem(PROFILE_NAME_KEY);
    setUser(null);
    setIsLoading(false);
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
