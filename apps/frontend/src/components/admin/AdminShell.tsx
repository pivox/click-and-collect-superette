'use client';
import { useEffect, useState } from 'react';
import { Menu, X } from 'lucide-react';
import { usePathname } from 'next/navigation';
import { AdminSidebar } from './AdminSidebar';
import { useAdminAuth } from '@/lib/auth/AdminAuthContext';
import { Button } from '@/components/ui/Button';

export function AdminShell({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAdminAuth();
  const pathname = usePathname();
  const [isMobileNavOpen, setIsMobileNavOpen] = useState(false);

  useEffect(() => {
    setIsMobileNavOpen(false);
  }, [pathname]);

  return (
    <div className="flex min-h-screen bg-bg">
      <AdminSidebar className="hidden md:flex" />
      {isMobileNavOpen && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label="Navigation admin"
          className="fixed inset-0 z-40 md:hidden"
        >
          <button
            type="button"
            aria-label="Fermer la navigation admin"
            className="absolute inset-0 bg-[#111814]/55"
            onClick={() => setIsMobileNavOpen(false)}
          />
          <div className="relative z-10 h-full w-72 max-w-[85vw] shadow-floating">
            <AdminSidebar className="h-full w-full" onNavigate={() => setIsMobileNavOpen(false)} />
            <button
              type="button"
              aria-label="Fermer la navigation admin"
              className="absolute right-3 top-3 inline-flex h-10 w-10 items-center justify-center rounded-md border border-white/10 bg-white/10 text-white transition-colors hover:bg-white/20"
              onClick={() => setIsMobileNavOpen(false)}
            >
              <X className="h-5 w-5" aria-hidden="true" />
            </button>
          </div>
        </div>
      )}
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex min-h-14 shrink-0 items-center justify-between gap-3 border-b border-line bg-card px-4 md:px-6">
          <div className="flex min-w-0 items-center gap-3">
            <Button
              type="button"
              variant="ghost"
              size="md"
              className="h-11 w-11 shrink-0 p-0 md:hidden"
              aria-label="Ouvrir la navigation admin"
              onClick={() => setIsMobileNavOpen(true)}
            >
              <Menu className="h-5 w-5" aria-hidden="true" />
            </Button>
            <div className="min-w-0">
              <p className="text-xs font-extrabold uppercase tracking-widest text-primary md:hidden">
                Kadhia Admin
              </p>
              <span className="block truncate text-sm text-muted">{user?.email}</span>
            </div>
          </div>
          <Button variant="ghost" size="md" className="shrink-0 px-3 sm:px-4" onClick={logout}>
            Déconnexion
          </Button>
        </header>
        <main className="flex-1 overflow-auto p-4 md:p-6">{children}</main>
      </div>
    </div>
  );
}
