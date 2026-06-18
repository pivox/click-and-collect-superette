'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import {
  getMerchantMe,
  loginMerchant,
} from '@/lib/services/merchant-auth.service';
import type { MerchantMe } from '@/lib/types/merchant.types';

interface MerchantAuthContextValue {
  merchant: MerchantMe | null;
  isLoading: boolean;
  error: string | null;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
  refresh: () => Promise<MerchantMe>;
}

const MerchantAuthContext = createContext<MerchantAuthContextValue | null>(null);

function clearMerchantToken(): void {
  localStorage.removeItem('merchant_token');
  document.cookie = 'merchant_token=; path=/merchant; expires=Thu, 01 Jan 1970 00:00:00 GMT';
}

function responseStatus(err: unknown): number | undefined {
  return typeof err === 'object' && err !== null && 'response' in err
    ? (err as { response?: { status?: number } }).response?.status
    : undefined;
}

export function MerchantAuthProvider({ children }: { children: React.ReactNode }) {
  const [merchant, setMerchant] = useState<MerchantMe | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const router = useRouter();
  const pathname = usePathname();

  const refresh = async () => {
    const context = await getMerchantMe();
    setMerchant(context);
    setError(null);
    return context;
  };

  useEffect(() => {
    const token = localStorage.getItem('merchant_token');
    if (!token) {
      setIsLoading(false);
      return;
    }

    void refresh()
      .catch((err: unknown) => {
        const status = responseStatus(err);
        if (status === 401 || status === 403) {
          clearMerchantToken();
        }
        setMerchant(null);
        setError(merchantErrorMessage(status));
      })
      .finally(() => setIsLoading(false));
  }, []);

  const login = async (email: string, password: string) => {
    let user;
    try {
      user = await loginMerchant({ email, password });
    } catch (err) {
      const status = responseStatus(err);
      throw new Error(
        status === 401 || status === 403
          ? 'Identifiants marchand incorrects.'
          : 'La connexion a échoué. Réessayez.',
      );
    }
    localStorage.setItem('merchant_token', user.token);
    document.cookie = `merchant_token=${user.token}; path=/merchant; SameSite=Lax; Max-Age=${60 * 60 * 8}`;
    try {
      const context = await refresh();
      router.push(context.password_change_required ? '/merchant/premiere-connexion' : '/merchant');
    } catch (err) {
      clearMerchantToken();
      throw new Error(merchantErrorMessage(responseStatus(err)));
    }
  };

  const logout = () => {
    clearMerchantToken();
    setMerchant(null);
    setError(null);
    router.push('/merchant/login');
  };

  useEffect(() => {
    if (!merchant) return;
    if (merchant.password_change_required && pathname !== '/merchant/premiere-connexion') {
      router.push('/merchant/premiere-connexion');
      return;
    }
    if (!merchant.password_change_required && pathname === '/merchant/premiere-connexion') {
      router.push('/merchant');
    }
  }, [merchant, pathname, router]);

  return (
    <MerchantAuthContext.Provider value={{ merchant, isLoading, error, login, logout, refresh }}>
      {children}
    </MerchantAuthContext.Provider>
  );
}

export function merchantErrorMessage(status?: number): string {
  if (status === 403) return 'Accès réservé aux marchands.';
  if (status === 404) return 'Aucune supérette active associée à ce compte marchand.';
  if (status === 409) return 'Plusieurs supérettes actives sont associées à ce compte.';
  return 'Impossible de charger votre espace marchand.';
}

export function useMerchantAuth(): MerchantAuthContextValue {
  const ctx = useContext(MerchantAuthContext);
  if (!ctx) throw new Error('useMerchantAuth must be used inside MerchantAuthProvider');
  return ctx;
}
