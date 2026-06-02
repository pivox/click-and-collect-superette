"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { LogOut, User } from "lucide-react";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Button, getButtonClassName } from "@/components/ui/Button";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import { updateProfile } from "@/lib/services/auth.service";

const MAX_NAME_LENGTH = 100;

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

function splitName(name: string): { firstName: string; lastName: string } {
  const idx = name.indexOf(" ");
  if (idx === -1) return { firstName: name, lastName: "" };
  return { firstName: name.slice(0, idx), lastName: name.slice(idx + 1) };
}

function ProfileEditForm({
  currentName,
  onSaved,
  onCancel,
}: {
  currentName: string;
  onSaved: (name: string) => void;
  onCancel: () => void;
}) {
  const { firstName: initFirst, lastName: initLast } = splitName(currentName);
  const [firstName, setFirstName] = useState(initFirst);
  const [lastName, setLastName] = useState(initLast);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const valid = firstName.trim().length > 0 && lastName.trim().length > 0;

  const handleSave = async () => {
    if (!valid) return;
    setSaving(true);
    setError(null);
    try {
      const result = await updateProfile(firstName.trim(), lastName.trim());
      onSaved(result.name);
    } catch {
      setError("Impossible de mettre à jour le profil. Réessaie.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="grid gap-3">
      <div className="grid gap-1.5">
        <label htmlFor="firstName" className="text-xs font-extrabold text-muted uppercase tracking-wide">
          Prénom
        </label>
        <input
          id="firstName"
          type="text"
          value={firstName}
          onChange={(e) => setFirstName(e.target.value)}
          maxLength={MAX_NAME_LENGTH}
          placeholder="Prénom"
          className="rounded-md border border-line bg-soft px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
        />
      </div>
      <div className="grid gap-1.5">
        <label htmlFor="lastName" className="text-xs font-extrabold text-muted uppercase tracking-wide">
          Nom
        </label>
        <input
          id="lastName"
          type="text"
          value={lastName}
          onChange={(e) => setLastName(e.target.value)}
          maxLength={MAX_NAME_LENGTH}
          placeholder="Nom"
          className="rounded-md border border-line bg-soft px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
        />
      </div>
      {error && <p className="text-sm text-red-600">{error}</p>}
      <div className="flex gap-2">
        <Button variant="ghost" size="md" onClick={onCancel} disabled={saving}>
          Annuler
        </Button>
        <Button size="md" onClick={() => void handleSave()} disabled={saving || !valid}>
          {saving ? "Enregistrement…" : "Enregistrer"}
        </Button>
      </div>
    </div>
  );
}

export default function ProfilePage() {
  const { user, logout, isLoading, updateUser } = useClientAuth();
  const router = useRouter();
  const [editing, setEditing] = useState(false);
  const [savedMessage, setSavedMessage] = useState(false);

  function handleLogout() {
    logout();
    router.push("/");
  }

  const handleSaved = (name: string) => {
    updateUser(name);
    setEditing(false);
    setSavedMessage(true);
    setTimeout(() => setSavedMessage(false), 3000);
  };

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
            <Link href="/login" className={getButtonClassName({ full: true })}>
              Se connecter
            </Link>
            <Link
              href="/register"
              className={getButtonClassName({ full: true, variant: "ghost" })}
            >
              Créer un compte
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

      <section className="mt-4">
        <div className="mb-2.5 flex items-center justify-between">
          <h3 className="text-h3 font-extrabold">Mes informations</h3>
          {!editing && (
            <button
              type="button"
              onClick={() => setEditing(true)}
              className="text-xs font-extrabold text-primary underline"
            >
              Modifier
            </button>
          )}
        </div>
        <Card>
          {savedMessage && (
            <p className="mb-3 rounded-md bg-green-50 px-3 py-2 text-sm font-bold text-green-700">
              Profil mis à jour.
            </p>
          )}
          {editing ? (
            <ProfileEditForm
              currentName={user.name}
              onSaved={handleSaved}
              onCancel={() => setEditing(false)}
            />
          ) : (
            <div className="grid gap-2 text-sm">
              <div className="flex items-center justify-between">
                <span className="text-muted">Prénom</span>
                <span className="font-extrabold">{splitName(user.name).firstName || "—"}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted">Nom</span>
                <span className="font-extrabold">{splitName(user.name).lastName || "—"}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted">Email</span>
                <span className="font-extrabold text-muted">{user.email}</span>
              </div>
            </div>
          )}
        </Card>
      </section>

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
