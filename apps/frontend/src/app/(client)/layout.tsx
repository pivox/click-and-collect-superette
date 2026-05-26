import type { Metadata } from "next";
import { DesktopNav } from "@/components/layout/DesktopNav";
import { BottomNav } from "@/components/layout/BottomNav";
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
          <main className="relative px-4 pt-4 pb-24 md:p-7">
            {children}
          </main>
        </div>
        {/* Bottom navigation — hidden on desktop via BottomNav's own md:hidden */}
        <BottomNav />
      </ClientAuthProvider>
    </ReactQueryProvider>
  );
}
