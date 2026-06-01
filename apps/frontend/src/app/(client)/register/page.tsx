'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { useHydrated } from '@/lib/hooks/useHydrated';
import { clientRegister } from '@/lib/services/auth.service';

export default function ClientRegisterPage() {
  const router = useRouter();
  const isHydrated = useHydrated();
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const submitLabel = !isHydrated
    ? 'Préparation du formulaire…'
    : isSubmitting
      ? 'Inscription…'
      : "Créer mon compte";

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);

    const formData = new FormData(e.currentTarget as HTMLFormElement);
    const name = String(formData.get('name') ?? '').trim();
    const email = String(formData.get('email') ?? '').trim();
    const password = String(formData.get('password') ?? '');

    if (!name) { setError('Le nom est requis.'); return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setError("Veuillez saisir une adresse email valide.");
      return;
    }
    if (password.trim().length < 8) { setError('Le mot de passe doit contenir au moins 8 caractères.'); return; }
    setIsSubmitting(true);
    try {
      await clientRegister(email, password, name);
      router.push('/login');
    } catch (err) {
      const status = (err as { response?: { status?: number } }).response?.status;
      if (status === 409) {
        setError('Un compte existe déjà avec cet email.');
      } else if (status === 422) {
        setError('Les informations saisies sont invalides.');
      } else if (!status) {
        setError('Impossible de joindre le serveur. Vérifie ta connexion.');
      } else {
        setError("Erreur lors de l'inscription. Réessaie dans quelques instants.");
      }
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
          <h1 className="mt-1 text-h2 font-black">Créer un compte</h1>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="name">
              Nom
            </label>
            <input
              id="name"
              name="name"
              type="text"
              autoComplete="name"
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="reg-email">
              Email
            </label>
            <input
              id="reg-email"
              name="email"
              type="email"
              autoComplete="email"
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="reg-password">
              Mot de passe
            </label>
            <input
              id="reg-password"
              name="password"
              type="password"
              autoComplete="new-password"
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          {error && (
            <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
              {error}
            </p>
          )}

          <Button full type="submit" disabled={!isHydrated || isSubmitting}>
            {submitLabel}
          </Button>
        </form>

        <p className="mt-4 text-center text-sm text-muted">
          Déjà un compte ?{' '}
          <Link href="/login" className="font-extrabold text-primary">
            Se connecter
          </Link>
        </p>
      </Card>
    </div>
  );
}
