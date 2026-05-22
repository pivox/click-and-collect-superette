import type { Metadata } from "next";
import { MobileShell } from "@/components/layout/MobileShell";
import { BottomNav } from "@/components/layout/BottomNav";

export const metadata: Metadata = {
  title: "Kadhia · Parcours client",
};

/**
 * Client-side mobile shell layout. Wraps every customer-facing screen in
 * the phone frame and adds the bottom tab bar. The home, store, catalog,
 * cart, slot, tracking and pickup pages all share this layout.
 *
 * Switch `bareLayout` to `true` to drop the bezel for PWA / native builds.
 */
export default function ClientLayout({ children }: { children: React.ReactNode }) {
  return (
    <MobileShell>
      {children}
      <BottomNav />
    </MobileShell>
  );
}
