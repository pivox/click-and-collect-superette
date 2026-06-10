"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { LogOut, User } from "lucide-react";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Button, getButtonClassName } from "@/components/ui/Button";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import { LanguageToggle } from "@/components/layout/LanguageToggle";
import { updateProfile, deleteAccount } from "@/lib/services/auth.service";

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
  const { t } = useClientLocale();
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
      setError(t("client.profile.updateError"));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="grid gap-3">
      <div className="grid gap-1.5">
        <label htmlFor="firstName" className="text-xs font-extrabold text-muted uppercase tracking-wide">
          {t("client.profile.firstName")}
        </label>
        <input
          id="firstName"
          type="text"
          value={firstName}
          onChange={(e) => setFirstName(e.target.value)}
          maxLength={MAX_NAME_LENGTH}
          placeholder={t("client.profile.firstName")}
          className="rounded-md border border-line bg-soft px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
        />
      </div>
      <div className="grid gap-1.5">
        <label htmlFor="lastName" className="text-xs font-extrabold text-muted uppercase tracking-wide">
          {t("client.profile.lastName")}
        </label>
        <input
          id="lastName"
          type="text"
          value={lastName}
          onChange={(e) => setLastName(e.target.value)}
          maxLength={MAX_NAME_LENGTH}
          placeholder={t("client.profile.lastName")}
          className="rounded-md border border-line bg-soft px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
        />
      </div>
      {error && <p role="alert" aria-atomic="true" className="text-sm text-red-600">{error}</p>}
      <div className="flex gap-2">
        <Button variant="ghost" size="md" onClick={onCancel} disabled={saving}>
          {t("client.profile.cancel")}
        </Button>
        <Button size="md" onClick={() => void handleSave()} disabled={saving || !valid}>
          {saving ? t("client.profile.saving") : t("client.profile.save")}
        </Button>
      </div>
    </div>
  );
}

const CONFIRM_WORD = "SUPPRIMER";

function DeleteAccountSection({ onDeleted }: { onDeleted: () => void }) {
  const { t } = useClientLocale();
  const [open, setOpen] = useState(false);
  const [confirmText, setConfirmText] = useState("");
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const canConfirm = confirmText === CONFIRM_WORD;

  const handleConfirm = async () => {
    if (!canConfirm) return;
    setDeleting(true);
    setError(null);
    try {
      await deleteAccount();
      onDeleted();
    } catch {
      setError(t("client.profile.deleteError"));
      setDeleting(false);
    }
  };

  const handleClose = () => {
    setOpen(false);
    setConfirmText("");
    setError(null);
  };

  return (
    <>
      <Card className="mt-4 border border-red-200">
        <button
          type="button"
          onClick={() => setOpen(true)}
          className="w-full text-left text-sm font-extrabold text-red-600 hover:text-red-700"
        >
          {t("client.profile.deleteAccount")}
        </button>
      </Card>

      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center px-4">
          <div className="absolute inset-0 bg-black/40" onClick={handleClose} />
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-account-title"
            className="relative w-full max-w-sm rounded-xl bg-card p-6 shadow-floating"
          >
            <h3 id="delete-account-title" className="mb-2 font-black text-ink">
              {t("client.profile.deleteAccount")}
            </h3>
            <p className="mb-4 text-sm text-muted">
              {t("client.profile.deleteConfirmMessage")}
            </p>
            <div className="mb-5 grid gap-1.5">
              <label htmlFor="confirm-delete" className="text-xs font-extrabold text-muted uppercase tracking-wide">
                {t("client.profile.deleteConfirmPrefix")}{" "}
                <span className="text-red-600">{CONFIRM_WORD}</span>{" "}
                {t("client.profile.deleteConfirmSuffix")}
              </label>
              <input
                id="confirm-delete"
                type="text"
                value={confirmText}
                onChange={(e) => setConfirmText(e.target.value)}
                placeholder={CONFIRM_WORD}
                autoComplete="off"
                className="rounded-md border border-line bg-soft px-3 py-2 text-sm focus:border-red-400 focus:outline-none focus:ring-1 focus:ring-red-400"
              />
            </div>
            {error && <p role="alert" aria-atomic="true" className="mb-3 text-sm text-red-600">{error}</p>}
            <div className="flex gap-3">
              <Button
                variant="danger"
                size="md"
                onClick={() => void handleConfirm()}
                disabled={!canConfirm || deleting}
                className="flex-1"
              >
                {deleting ? t("client.profile.deleting") : t("client.profile.deleteConfirm")}
              </Button>
              <Button variant="ghost" size="md" onClick={handleClose} disabled={deleting} className="flex-1">
                {t("client.profile.cancel")}
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

export default function ProfilePage() {
  const { user, logout, isLoading, updateUser } = useClientAuth();
  const { t } = useClientLocale();
  const router = useRouter();
  const [editing, setEditing] = useState(false);
  const [savedMessage, setSavedMessage] = useState(false);
  const [deletedMessage, setDeletedMessage] = useState(false);

  function handleLogout() {
    logout();
    router.push("/");
  }

  function handleDeleted() {
    logout();
    setDeletedMessage(true);
    setTimeout(() => {
      router.push("/");
    }, 1500);
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
        <TopBar title={t("client.profile.title")} />
        <Card className="text-center">
          <div className="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full bg-soft">
            <User size={28} className="text-primary" />
          </div>
          <h2 className="m-0 text-h2 font-black">{t("client.profile.welcome")}</h2>
          <p className="mt-2 text-sm text-muted">
            {t("client.profile.notLoggedMessage")}
          </p>
          <div className="mt-6 grid gap-3">
            <Link href="/login" className={getButtonClassName({ full: true })}>
              {t("client.nav.signIn")}
            </Link>
            <Link
              href="/register"
              className={getButtonClassName({ full: true, variant: "ghost" })}
            >
              {t("client.nav.register")}
            </Link>
          </div>
        </Card>
      </>
    );
  }

  const avatarLetters = initials(user.name, user.email);

  return (
    <>
      <TopBar title={t("client.profile.title")} backHref="/" />

      <Card>
        <div className="flex items-center gap-4">
          <div className="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-primary text-lg font-black text-white">
            {avatarLetters}
          </div>
          <div className="min-w-0">
            <strong className="block truncate text-base">
              {t("client.profile.welcome")}{user.name ? `, ${user.name}` : ""} 👋
            </strong>
            <span className="block truncate text-sm text-muted">{user.email}</span>
          </div>
        </div>
      </Card>

      <section className="mt-4">
        <div className="mb-2.5 flex items-center justify-between">
          <h3 className="text-h3 font-extrabold">{t("client.profile.myInfo")}</h3>
          {!editing && (
            <button
              type="button"
              onClick={() => setEditing(true)}
              className="text-xs font-extrabold text-primary underline"
            >
              {t("client.profile.edit")}
            </button>
          )}
        </div>
        <Card>
          {savedMessage && (
            <p className="mb-3 rounded-md bg-green-50 px-3 py-2 text-sm font-bold text-green-700">
              {t("client.profile.saved")}
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
                <span className="text-muted">{t("client.profile.firstName")}</span>
                <span className="font-extrabold">{splitName(user.name).firstName || "—"}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted">{t("client.profile.lastName")}</span>
                <span className="font-extrabold">{splitName(user.name).lastName || "—"}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-muted">{t("client.profile.email")}</span>
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
            {t("client.profile.myOrders")}
          </Link>
          <Link
            href="/kadhia"
            className="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-extrabold hover:bg-soft"
          >
            {t("client.profile.myKadhia")}
          </Link>
        </nav>
      </Card>

      <Card className="mt-4">
        <div className="flex items-center justify-between gap-3">
          <div className="min-w-0">
            <strong className="block text-sm font-extrabold">{t("client.language.label")}</strong>
            <span className="block text-xs text-muted">{t("client.language.description")}</span>
          </div>
          <LanguageToggle />
        </div>
      </Card>

      <Card className="mt-4">
        <button
          type="button"
          onClick={handleLogout}
          className="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm font-extrabold text-red-600 hover:bg-red-50"
        >
          <LogOut size={16} />
          {t("client.profile.logout")}
        </button>
      </Card>

      {deletedMessage ? (
        <Card className="mt-4 border border-red-200 bg-red-50">
          <p className="text-sm font-bold text-red-700">{t("client.profile.deleted")}</p>
        </Card>
      ) : (
        <DeleteAccountSection onDeleted={handleDeleted} />
      )}
    </>
  );
}
