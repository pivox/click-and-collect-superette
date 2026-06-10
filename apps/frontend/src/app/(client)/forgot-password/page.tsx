'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { useHydrated } from '@/lib/hooks/useHydrated';
import { requestPasswordReset } from '@/lib/services/auth.service';

export default function ForgotPasswordPage() {
  const isHydrated = useHydrated();
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      await requestPasswordReset(email.trim());
      setSubmitted(true);
    } catch {
      setError('Une erreur est survenue. Réessaie plus tard.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-[80vh] items-center justify-center px-4">
      <Card className="w-full max-w-sm">
        <div className="mb-6 text-center">
          <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
            Kadhia
          </span>
          <h1 className="mt-1 text-h2 font-black">Mot de passe oublié</h1>
        </div>

        {submitted ? (
          <div className="space-y-4 text-center">
            <p className="text-sm text-muted">
              Si cet email est associé à un compte, un lien de réinitialisation
              a été envoyé. Vérifie ta boîte mail.
            </p>
            <Link
              href="/login"
              className="block text-sm font-extrabold text-primary hover:underline"
            >
              Retour à la connexion
            </Link>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="mb-1 block text-sm font-semibold" htmlFor="email">
                Adresse email
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

            {error && (
              <p role="alert" aria-atomic="true" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
                {error}
              </p>
            )}

            <Button full type="submit" disabled={!isHydrated || isSubmitting}>
              {isSubmitting ? 'Envoi…' : 'Envoyer le lien'}
            </Button>

            <p className="text-center text-sm text-muted">
              <Link href="/login" className="font-extrabold text-primary hover:underline">
                Retour à la connexion
              </Link>
            </p>
          </form>
        )}
      </Card>
    </div>
  );
}
