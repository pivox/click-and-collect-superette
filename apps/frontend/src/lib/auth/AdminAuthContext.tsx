'use client';
import React, { createContext, useContext, useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { adminLogin, decodeJwtPayload, type AdminUser } from '@/lib/services/auth.service';

interface AdminAuthContextValue {
  user: AdminUser | null;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

const AdminAuthContext = createContext<AdminAuthContextValue | null>(null);

export function AdminAuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AdminUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    const token = localStorage.getItem('admin_token');
    if (token) {
      try {
        const payload = decodeJwtPayload(token);
        setUser({
          token,
          email: typeof payload.email === 'string' ? payload.email : '',
          name:
            typeof payload.name === 'string'
              ? payload.name
              : typeof payload.email === 'string'
                ? payload.email
                : '',
        });
      } catch {
        localStorage.removeItem('admin_token');
      }
    }
    setIsLoading(false);
  }, []);

  const login = async (email: string, password: string) => {
    const adminUser = await adminLogin(email, password);
    localStorage.setItem('admin_token', adminUser.token);
    document.cookie = `admin_token=${adminUser.token}; path=/admin; SameSite=Lax; Max-Age=${60 * 60 * 8}`;
    setUser(adminUser);
    router.push('/admin/dashboard');
  };

  const logout = () => {
    localStorage.removeItem('admin_token');
    document.cookie = 'admin_token=; path=/admin; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    setUser(null);
    router.push('/admin/login');
  };

  return (
    <AdminAuthContext.Provider value={{ user, isLoading, login, logout }}>
      {children}
    </AdminAuthContext.Provider>
  );
}

export function useAdminAuth(): AdminAuthContextValue {
  const ctx = useContext(AdminAuthContext);
  if (!ctx) throw new Error('useAdminAuth must be used inside AdminAuthProvider');
  return ctx;
}
