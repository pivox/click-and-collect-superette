import type { Metadata } from "next";
import { DesktopNav } from "@/components/layout/DesktopNav";
import { BottomNav } from "@/components/layout/BottomNav";
import { GlobalSearchBar } from "@/components/layout/GlobalSearchBar";
import { ClientAuthProvider } from "@/lib/auth/ClientAuthContext";
import { ReactQueryProvider } from "@/lib/providers/ReactQueryProvider";

export const metadata: Metadata = {
  title: "Kadhia · Click & Collect",
};

export default function ClientLayout({ children }: { children: React.ReactNode }) {
  return (
    <ReactQueryProvider>
      <ClientAuthProvider>
        {/* Responsive grid: sidebar (md+) + main. Children rendered once. */}
        <div className="min-h-screen md:grid md:grid-cols-[280px_1fr]">
          <DesktopNav />
          <div data-testid="client-content-column" className="flex min-w-0 flex-col">
            {/* Desktop-only topbar with global search */}
            <header className="hidden md:flex items-center gap-4 border-b border-line bg-white/80 backdrop-blur-md px-7 py-3 sticky top-0 z-10">
              <GlobalSearchBar />
              <span className="shrink-0 rounded-full bg-soft px-3 py-1.5 text-xs font-extrabold text-primary-dark">
                🇹🇳 TND
              </span>
            </header>
            <main className="relative min-w-0 px-4 pt-4 pb-40 md:p-7">
              {children}
            </main>
          </div>
        </div>
        {/* Bottom navigation — hidden on desktop via BottomNav's own md:hidden */}
        <BottomNav />
      </ClientAuthProvider>
    </ReactQueryProvider>
  );
}
