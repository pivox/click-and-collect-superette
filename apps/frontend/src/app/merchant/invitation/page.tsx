'use client';

import Link from 'next/link';
import { Suspense, useEffect, useMemo, useState, type FormEvent } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import {
  completeMerchantInvitation,
  verifyMerchantInvitation,
} from '@/lib/services/merchant-invitation.service';

type VerificationState = 'loading' | 'valid' | 'error';

function responseDetail(err: unknown): string | undefined {
  return typeof err === 'object' && err !== null && 'response' in err
    ? (err as { response?: { data?: { detail?: string } } }).response?.data?.detail
    : undefined;
}

function invitationErrorMessage(detail?: string): string {
  switch (detail) {
    case 'MERCHANT_INVITATION_TOKEN_EXPIRED':
      return 'Ce lien d’invitation a expiré.';
    case 'MERCHANT_INVITATION_TOKEN_ALREADY_USED':
      return 'Ce lien d’invitation a déjà été utilisé.';
    case 'MERCHANT_INVITATION_TOKEN_REVOKED':
      return 'Ce lien a été remplacé par une invitation plus récente.';
    case 'MERCHANT_INVITATION_TOKEN_INVALID':
      return 'Ce lien d’invitation est invalide.';
    case 'AUTH_WEAK_PASSWORD':
    case 'MERCHANT_INVITATION_PASSWORD_CONFIRMATION_MISMATCH':
      return 'Mot de passe invalide ou confirmation incorrecte.';
    default:
      return 'Impossible de finaliser l’invitation. Réessayez.';
  }
}

function formatExpiration(expiresAt: string | null): string | null {
  if (!expiresAt) return null;
  const date = new Date(expiresAt);
  if (Number.isNaN(date.getTime())) return null;

  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(date);
}

function MerchantInvitationForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const token = useMemo(() => searchParams.get('token')?.trim() ?? '', [searchParams]);
  const [verificationState, setVerificationState] = useState<VerificationState>(
    token ? 'loading' : 'error',
  );
  const [expiresAt, setExpiresAt] = useState<string | null>(null);
  const [newPassword, setNewPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [error, setError] = useState<string | null>(
    token ? null : 'Ce lien d’invitation est invalide.',
  );
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    if (!token) return;

    let cancelled = false;
    setVerificationState('loading');
    setError(null);

    void verifyMerchantInvitation(token)
      .then((verification) => {
        if (cancelled) return;
        setExpiresAt(verification.expiresAt);
        setVerificationState('valid');
      })
      .catch((err) => {
        if (cancelled) return;
        setError(invitationErrorMessage(responseDetail(err)));
        setVerificationState('error');
      });

    return () => {
      cancelled = true;
    };
  }, [token]);

  const handleSubmit = async (event: FormEvent) => {
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
      await completeMerchantInvitation({
        token,
        newPassword,
        newPasswordConfirmation: confirm,
      });
      setNewPassword('');
      setConfirm('');
      setSuccess(true);
      router.push('/merchant/login');
    } catch (err) {
      setError(invitationErrorMessage(responseDetail(err)));
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!token || verificationState === 'error') {
    return (
      <InvitationCard title="Lien invalide">
        <p role="alert" className="mb-4 text-sm text-muted">
          {error ?? 'Ce lien d’invitation est invalide.'}
        </p>
        <Link
          href="/merchant/login"
          className="block text-center text-sm font-extrabold text-primary hover:underline"
        >
          Aller à la connexion marchand
        </Link>
      </InvitationCard>
    );
  }

  if (verificationState === 'loading') {
    return (
      <InvitationCard title="Vérification de l’invitation">
        <p className="text-sm text-muted">
          Nous vérifions votre lien d’activation marchand.
        </p>
      </InvitationCard>
    );
  }

  return (
    <InvitationCard title="Activer votre espace marchand">
      <p className="mb-4 text-sm text-muted">
        Définissez votre mot de passe définitif pour accéder à votre espace
        marchand.
      </p>
      {formatExpiration(expiresAt) && (
        <p className="mb-4 rounded-md bg-soft px-3 py-2 text-sm text-muted">
          Ce lien expire le {formatExpiration(expiresAt)}.
        </p>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <Field
          id="merchant-invitation-new-password"
          label="Nouveau mot de passe"
          value={newPassword}
          onChange={setNewPassword}
        />
        <Field
          id="merchant-invitation-confirm-password"
          label="Confirmer le nouveau mot de passe"
          value={confirm}
          onChange={setConfirm}
        />

        {error && (
          <p role="alert" aria-atomic="true" className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </p>
        )}
        {success && <p className="text-sm font-semibold text-green-700">Mot de passe défini.</p>}

        <Button full type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Activation…' : 'Définir mon mot de passe'}
        </Button>
      </form>
    </InvitationCard>
  );
}

function InvitationCard({
  title,
  children,
}: {
  title: string;
  children: React.ReactNode;
}) {
  return (
    <Card className="w-full max-w-md p-6 shadow-card">
      <div className="mb-6 text-center">
        <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
          Kadhia Marchand
        </span>
        <h1 className="mt-1 text-2xl font-black text-ink">{title}</h1>
      </div>
      {children}
    </Card>
  );
}

function Field({
  id,
  label,
  value,
  onChange,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
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
        autoComplete="new-password"
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
      />
    </div>
  );
}

export default function MerchantInvitationPage() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-bg px-4 py-8">
      <Suspense
        fallback={
          <InvitationCard title="Vérification de l’invitation">
            <p className="text-sm text-muted">
              Nous vérifions votre lien d’activation marchand.
            </p>
          </InvitationCard>
        }
      >
        <MerchantInvitationForm />
      </Suspense>
    </main>
  );
}
