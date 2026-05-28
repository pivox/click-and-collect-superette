"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { LogOut, User } from "lucide-react";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";

function initials(name: string, email: string): string {
  const src = name.trim() || email;
  return src
    .split(/[\s@._-]+/)
    .filter(Boolean)
    .map((w) => w[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
}

export default function ProfilePage() {
  const { user, logout, isLoading } = useClientAuth();
  const router = useRouter();

  function handleLogout() {
    logout();
    router.push("/");
  }

  if (isLoading) return null;

  if (!user) {
    return (
      <>
        <TopBar title="Mon compte" />
        <Card className="text-center">
          <div className="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full bg-soft">
            <User size={28} className="text-primary" />
          </div>
          <h2 className="m-0 text-h2 font-black">Bienvenue</h2>
          <p className="mt-2 text-sm text-muted">
            Connecte-toi pour accéder à ta Kadhia et suivre tes commandes.
          </p>
          <div className="mt-6 grid gap-3">
            <Link href="/login">
              <Button full>Se connecter</Button>
            </Link>
            <Link href="/register">
              <Button full variant="ghost">Créer un compte</Button>
            </Link>
          </div>
        </Card>
      </>
    );
  }

  const avatarLetters = initials(user.name, user.email);

  return (
    <>
      <TopBar title="Mon compte" backHref="/" />

      <Card>
        <div className="flex items-center gap-4">
          <div className="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-primary text-lg font-black text-white">
            {avatarLetters}
          </div>
          <div className="min-w-0">
            <strong className="block truncate text-base">
              Bienvenue{user.name ? `, ${user.name}` : ""} 👋
            </strong>
            <span className="block truncate text-sm text-muted">{user.email}</span>
          </div>
        </div>
      </Card>

      <Card className="mt-4">
        <nav className="grid gap-1">
          <Link
            href="/orders"
            className="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-extrabold hover:bg-soft"
          >
            Mes commandes
          </Link>
          <Link
            href="/kadhia"
            className="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-extrabold hover:bg-soft"
          >
            Ma Kadhia
          </Link>
        </nav>
      </Card>

      <Card className="mt-4">
        <button
          type="button"
          onClick={handleLogout}
          className="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm font-extrabold text-red-600 hover:bg-red-50"
        >
          <LogOut size={16} />
          Se déconnecter
        </button>
      </Card>
    </>
  );
}
