'use client';

import { Suspense, useState, type FormEvent } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { isAxiosError } from 'axios';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { useHydrated } from '@/lib/hooks/useHydrated';
import { confirmPasswordReset } from '@/lib/services/auth.service';

function ResetPasswordForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const isHydrated = useHydrated();

  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!token) {
    return (
      <Card className="w-full max-w-sm">
        <div className="mb-6 text-center">
          <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
            Kadhia
          </span>
          <h1 className="mt-1 text-h2 font-black">Lien invalide</h1>
        </div>
        <p className="mb-4 text-sm text-muted">
          Ce lien de réinitialisation est invalide ou a expiré.
        </p>
        <Link
          href="/forgot-password"
          className="block text-center text-sm font-extrabold text-primary hover:underline"
        >
          Demander un nouveau lien
        </Link>
      </Card>
    );
  }

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);

    if (password.length < 8) {
      setError('Le mot de passe doit contenir au moins 8 caractères.');
      return;
    }
    if (password !== confirm) {
      setError('Les mots de passe ne correspondent pas.');
      return;
    }

    setIsSubmitting(true);
    try {
      await confirmPasswordReset(token, password);
      sessionStorage.setItem('login:flash', 'Mot de passe mis à jour. Tu peux te connecter.');
      router.push('/login');
    } catch (err) {
      if (isAxiosError(err)) {
        const status = err.response?.status;
        if (status === 422 || status === 400) {
          setError(
            'Ce lien est invalide ou a expiré. Demande un nouveau lien de réinitialisation.',
          );
        } else {
          setError('Une erreur est survenue. Réessaie plus tard.');
        }
      } else {
        setError('Une erreur est survenue. Réessaie plus tard.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Card className="w-full max-w-sm">
      <div className="mb-6 text-center">
        <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
          Kadhia
        </span>
        <h1 className="mt-1 text-h2 font-black">Nouveau mot de passe</h1>
      </div>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="mb-1 block text-sm font-semibold" htmlFor="password">
            Nouveau mot de passe
          </label>
          <input
            id="password"
            type="password"
            required
            autoComplete="new-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <div>
          <label className="mb-1 block text-sm font-semibold" htmlFor="confirm">
            Confirmer le mot de passe
          </label>
          <input
            id="confirm"
            type="password"
            required
            autoComplete="new-password"
            value={confirm}
            onChange={(e) => setConfirm(e.target.value)}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>

        {error && (
          <p role="alert" aria-atomic="true" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
            {error}
          </p>
        )}

        <Button full type="submit" disabled={!isHydrated || isSubmitting}>
          {isSubmitting ? 'Enregistrement…' : 'Enregistrer le mot de passe'}
        </Button>

        <p className="text-center text-sm">
          <Link
            href="/forgot-password"
            className="font-semibold text-muted hover:text-primary hover:underline"
          >
            Demander un nouveau lien
          </Link>
        </p>
      </form>
    </Card>
  );
}

export default function ResetPasswordPage() {
  return (
    <div className="flex min-h-[80vh] items-center justify-center px-4">
      <Suspense>
        <ResetPasswordForm />
      </Suspense>
    </div>
  );
}
