'use client';
import { AdminSidebar } from './AdminSidebar';
import { useAdminAuth } from '@/lib/auth/AdminAuthContext';
import { Button } from '@/components/ui/Button';

export function AdminShell({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAdminAuth();

  return (
    <div className="flex h-screen overflow-hidden bg-bg">
      <AdminSidebar />
      <div className="flex flex-1 flex-col overflow-hidden">
        <header className="flex h-14 shrink-0 items-center justify-between border-b border-line bg-card px-6">
          <span className="text-sm text-muted">{user?.email}</span>
          <Button variant="ghost" size="md" onClick={logout}>
            Déconnexion
          </Button>
        </header>
        <main className="flex-1 overflow-auto p-6">{children}</main>
      </div>
    </div>
  );
}
