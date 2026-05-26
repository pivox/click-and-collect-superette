import type { Metadata } from "next";
import { MobileShell } from "@/components/layout/MobileShell";
import { DesktopShell } from "@/components/layout/DesktopShell";
import { BottomNav } from "@/components/layout/BottomNav";

export const metadata: Metadata = {
  title: "Kadhia · Click & Collect",
};

export default function ClientLayout({ children }: { children: React.ReactNode }) {
  return (
    <>
      <div className="md:hidden">
        <MobileShell>
          {children}
          <BottomNav />
        </MobileShell>
      </div>
      <div className="hidden md:block">
        <DesktopShell>{children}</DesktopShell>
      </div>
    </>
  );
}
