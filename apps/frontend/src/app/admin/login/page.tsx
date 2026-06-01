'use client';
import { isAxiosError } from 'axios';
import { useState, type FormEvent } from 'react';
import { useAdminAuth } from '@/lib/auth/AdminAuthContext';
import { Button } from '@/components/ui/Button';
import { useHydrated } from '@/lib/hooks/useHydrated';

function getAdminLoginErrorMessage(err: unknown): string {
  if (isAxiosError(err)) {
    const status = err.response?.status;

    if (status === 401) {
      return 'Email ou mot de passe incorrect.';
    }
    if (status === 403) {
      return 'Accès réservé aux administrateurs.';
    }
    if (status === 429) {
      return 'Trop de tentatives. Réessaie dans quelques minutes.';
    }

    return 'Une erreur est survenue. Réessaie plus tard.';
  }

  if (err instanceof Error && err.message === "Accès réservé à l'administration") {
    return 'Accès réservé aux administrateurs.';
  }

  return 'Une erreur est survenue. Réessaie plus tard.';
}

export default function AdminLoginPage() {
  const { login } = useAdminAuth();
  const isHydrated = useHydrated();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      await login(email, password);
    } catch (err) {
      setError(getAdminLoginErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-bg">
      <div className="w-full max-w-sm rounded-xl bg-card p-8 shadow-card">
        <div className="mb-6 text-center">
          <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
            Kadhia
          </span>
          <h1 className="mt-1 text-h2 font-black">Administration</h1>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="email">
              Email
            </label>
            <input
              id="email"
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="password">
              Mot de passe
            </label>
            <input
              id="password"
              type="password"
              required
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          {error && (
            <p
              role="alert"
              className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel"
            >
              {error}
            </p>
          )}

          <Button full type="submit" disabled={!isHydrated || isSubmitting}>
            {isSubmitting ? 'Connexion…' : 'Se connecter'}
          </Button>
        </form>
      </div>
    </div>
  );
}
