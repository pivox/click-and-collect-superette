'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { LogOut } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { changeMerchantFirstLoginPassword } from '@/lib/services/merchant-account.service';

function responseStatus(err: unknown): number | undefined {
  return typeof err === 'object' && err !== null && 'response' in err
    ? (err as { response?: { status?: number } }).response?.status
    : undefined;
}

export default function MerchantFirstLoginPage() {
  const router = useRouter();
  const { logout, refresh } = useMerchantAuth();
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  const resetFields = () => {
    setCurrentPassword('');
    setNewPassword('');
    setConfirm('');
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError(null);
    setSuccess(false);

    if (newPassword.length < 8) {
      setError('Le nouveau mot de passe doit contenir au moins 8 caractères.');
      return;
    }
    if (newPassword !== confirm) {
      setError('La confirmation ne correspond pas au nouveau mot de passe.');
      return;
    }

    setIsSubmitting(true);
    try {
      await changeMerchantFirstLoginPassword({
        currentPassword,
        newPassword,
        newPasswordConfirmation: confirm,
      });
      resetFields();
      setSuccess(true);
      try {
        await refresh();
      } catch {
        // The password is already changed; the dashboard will reload context if needed.
      }
      router.push('/merchant');
    } catch (err) {
      setError(
        responseStatus(err) === 422
          ? 'Mot de passe provisoire incorrect ou nouveau mot de passe invalide.'
          : 'Impossible de définir le mot de passe. Réessayez.',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <main className="flex min-h-screen items-center justify-center bg-bg px-4 py-8">
      <section className="w-full max-w-md rounded-md border border-line bg-card p-6 shadow-card">
        <div className="mb-6 space-y-2">
          <p className="text-xs font-extrabold uppercase tracking-widest text-primary">
            Kadhia Marchand
          </p>
          <h1 className="text-2xl font-black text-ink">Définir votre mot de passe</h1>
          <p className="text-sm text-muted">
            Votre mot de passe provisoire doit être remplacé avant d’accéder à votre
            espace marchand.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <Field
            id="first-login-current-password"
            label="Mot de passe provisoire actuel"
            value={currentPassword}
            onChange={setCurrentPassword}
            autoComplete="current-password"
          />
          <Field
            id="first-login-new-password"
            label="Nouveau mot de passe"
            value={newPassword}
            onChange={setNewPassword}
            autoComplete="new-password"
          />
          <Field
            id="first-login-confirm-password"
            label="Confirmer le nouveau mot de passe"
            value={confirm}
            onChange={setConfirm}
            autoComplete="new-password"
          />

          {error && (
            <p role="alert" aria-atomic="true" className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
              {error}
            </p>
          )}
          {success && <p className="text-sm font-semibold text-green-700">Mot de passe défini.</p>}

          <div className="flex flex-col gap-3 sm:flex-row">
            <Button type="submit" disabled={isSubmitting} className="flex-1">
              {isSubmitting ? 'Enregistrement…' : 'Définir mon mot de passe'}
            </Button>
            <Button type="button" variant="ghost" onClick={logout}>
              <LogOut className="h-4 w-4" aria-hidden="true" />
              Se déconnecter
            </Button>
          </div>
        </form>
      </section>
    </main>
  );
}

function Field({
  id,
  label,
  value,
  onChange,
  autoComplete,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  autoComplete: string;
}) {
  return (
    <div>
      <label className="mb-1 block text-sm font-semibold" htmlFor={id}>
        {label}
        <span className="text-red-600"> *</span>
      </label>
      <input
        id={id}
        type="password"
        required
        value={value}
        autoComplete={autoComplete}
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
      />
    </div>
  );
}
