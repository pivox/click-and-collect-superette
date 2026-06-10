'use client';

import Link from 'next/link';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { Button } from '@/components/ui/Button';
import {
  changeMerchantPassword,
  updateMerchantAccount,
} from '@/lib/services/merchant-account.service';

// MS-003 (MVP-3): the merchant manages their own account — display name and
// email (with uniqueness) — and changes their password (current + new).

function responseStatus(err: unknown): number | undefined {
  return typeof err === 'object' && err !== null && 'response' in err
    ? (err as { response?: { status?: number } }).response?.status
    : undefined;
}

export default function MerchantAccountPage() {
  const { merchant, refresh } = useMerchantAuth();

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div className="space-y-2">
        <Link
          href="/merchant/parametres"
          className="inline-flex items-center gap-1 text-sm font-bold text-primary hover:underline"
        >
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          Paramètres
        </Link>
        <h1 className="text-2xl font-black text-ink">Compte</h1>
        <p className="text-sm text-muted">Gérez vos informations de connexion.</p>
      </div>

      <AccountForm
        initialName={merchant?.name ?? ''}
        initialEmail={merchant?.email ?? ''}
        onSaved={refresh}
      />
      <PasswordForm />
    </div>
  );
}

function AccountForm({
  initialName,
  initialEmail,
  onSaved,
}: {
  initialName: string;
  initialEmail: string;
  onSaved: () => Promise<void>;
}) {
  const [name, setName] = useState(initialName);
  const [email, setEmail] = useState(initialEmail);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (name.trim() === '') {
      setError('Le nom est obligatoire.');
      return;
    }
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      await updateMerchantAccount({ name: name.trim(), email: email.trim() });
      setSaved(true);
      try {
        await onSaved();
      } catch {
        // best-effort context refresh
      }
    } catch (err) {
      setError(
        responseStatus(err) === 409
          ? 'Cet email est déjà utilisé par un autre compte.'
          : "Impossible d'enregistrer. Vérifiez les valeurs saisies.",
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4 rounded-lg border border-line bg-card p-4">
      <h2 className="font-bold text-ink">Informations</h2>
      <Field
        id="account-name"
        label="Nom affiché"
        required
        value={name}
        onChange={(v) => {
          setName(v);
          setSaved(false);
        }}
        maxLength={100}
      />
      <Field
        id="account-email"
        label="Email"
        type="email"
        required
        value={email}
        onChange={(v) => {
          setEmail(v);
          setSaved(false);
        }}
        maxLength={180}
      />
      {error && <p role="alert" aria-atomic="true" className="text-sm text-red-600">{error}</p>}
      {saved && <p className="text-sm text-green-700">Informations enregistrées.</p>}
      <Button type="submit" disabled={saving}>
        {saving ? 'Enregistrement…' : 'Enregistrer'}
      </Button>
    </form>
  );
}

function PasswordForm() {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const reset = () => {
    setCurrentPassword('');
    setNewPassword('');
    setConfirm('');
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (newPassword.length < 8) {
      setError('Le nouveau mot de passe doit contenir au moins 8 caractères.');
      return;
    }
    if (newPassword !== confirm) {
      setError('La confirmation ne correspond pas au nouveau mot de passe.');
      return;
    }
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      await changeMerchantPassword({ currentPassword, newPassword });
      setSaved(true);
      reset();
    } catch (err) {
      setError(
        responseStatus(err) === 422
          ? 'Mot de passe actuel incorrect.'
          : 'Impossible de changer le mot de passe.',
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4 rounded-lg border border-line bg-card p-4">
      <h2 className="font-bold text-ink">Mot de passe</h2>
      <Field
        id="current-password"
        label="Mot de passe actuel"
        type="password"
        required
        value={currentPassword}
        onChange={setCurrentPassword}
      />
      <Field
        id="new-password"
        label="Nouveau mot de passe"
        type="password"
        required
        value={newPassword}
        onChange={setNewPassword}
      />
      <Field
        id="confirm-password"
        label="Confirmer le nouveau mot de passe"
        type="password"
        required
        value={confirm}
        onChange={setConfirm}
      />
      {error && <p role="alert" aria-atomic="true" className="text-sm text-red-600">{error}</p>}
      {saved && <p className="text-sm text-green-700">Mot de passe mis à jour.</p>}
      <Button type="submit" disabled={saving}>
        {saving ? 'Mise à jour…' : 'Changer le mot de passe'}
      </Button>
    </form>
  );
}

function Field({
  id,
  label,
  value,
  onChange,
  type = 'text',
  required = false,
  maxLength,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  type?: string;
  required?: boolean;
  maxLength?: number;
}) {
  return (
    <div>
      <label className="mb-1 block text-sm font-semibold" htmlFor={id}>
        {label}
        {required && <span className="text-red-600"> *</span>}
      </label>
      <input
        id={id}
        type={type}
        value={value}
        required={required}
        maxLength={maxLength}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
      />
    </div>
  );
}
