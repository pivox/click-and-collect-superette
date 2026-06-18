'use client';

import { useEffect } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { MerchantShell } from '@/components/merchant/MerchantShell';
import { MerchantAuthProvider, useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { MerchantLocaleProvider } from '@/lib/i18n/MerchantLocaleContext';

function MerchantContent({ children }: { children: React.ReactNode }) {
  const { merchant, isLoading, error } = useMerchantAuth();
  const pathname = usePathname();
  const router = useRouter();
  const isLogin = pathname === '/merchant/login';
  const isFirstLogin = pathname === '/merchant/premiere-connexion';

  useEffect(() => {
    if (!isLoading && !merchant && !error && !isLogin) {
      router.push('/merchant/login');
    }
  }, [merchant, isLoading, error, isLogin, router]);

  useEffect(() => {
    if (isLoading || !merchant) return;
    if (merchant.password_change_required && !isFirstLogin) {
      router.push('/merchant/premiere-connexion');
    }
    if (!merchant.password_change_required && isFirstLogin) {
      router.push('/merchant');
    }
  }, [merchant, isLoading, isFirstLogin, router]);

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-bg">
        <span className="text-sm text-muted">Chargement de l&apos;espace marchand…</span>
      </div>
    );
  }

  if (isLogin || isFirstLogin) return <>{children}</>;

  if (error) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-bg p-6">
        <div className="max-w-md rounded-md bg-card p-6 shadow-card">
          <h1 className="text-h2 font-black">Espace marchand indisponible</h1>
          <p className="mt-3 text-sm text-muted">{error}</p>
        </div>
      </div>
    );
  }

  if (!merchant) return null;

  if (merchant.password_change_required) return null;

  return <MerchantShell>{children}</MerchantShell>;
}

export default function MerchantClientLayout({ children }: { children: React.ReactNode }) {
  return (
    <MerchantAuthProvider>
      <MerchantLocaleProvider>
        <MerchantContent>{children}</MerchantContent>
      </MerchantLocaleProvider>
    </MerchantAuthProvider>
  );
}
