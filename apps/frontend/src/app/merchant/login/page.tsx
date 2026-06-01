'use client';

import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { useHydrated } from '@/lib/hooks/useHydrated';

export default function MerchantLoginPage() {
  const { login } = useMerchantAuth();
  const isHydrated = useHydrated();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      await login(email, password);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Identifiants marchand incorrects');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-bg px-4">
      <div className="w-full max-w-sm rounded-md bg-card p-8 shadow-card">
        <div className="mb-6 text-center">
          <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
            Kadhia Marchand
          </span>
          <h1 className="mt-1 text-h2 font-black">Connexion marchand</h1>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="merchant-email">
              Email
            </label>
            <input
              id="merchant-email"
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="merchant-password">
              Mot de passe
            </label>
            <input
              id="merchant-password"
              type="password"
              required
              autoComplete="current-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          {error && (
            <p role="alert" className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
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
