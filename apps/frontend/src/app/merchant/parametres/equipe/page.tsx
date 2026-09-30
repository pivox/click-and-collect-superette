'use client';

import Link from 'next/link';
import { ArrowLeft, UserPlus } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { useMerchantLocale } from '@/lib/i18n/MerchantLocaleContext';
import { formatDateTime } from '@/lib/date-formatter';
import {
  getMerchantTeam,
  inviteMerchantTeamAccount,
  resendMerchantTeamInvitation,
  revokeMerchantTeamAccount,
  type MerchantTeam,
  type MerchantTeamAccount,
} from '@/lib/services/merchant-team.service';

// MERCHANT-TEAM-006: team screen. The API is reserved to the primary account:
// a 403 MERCHANT_TEAM_MANAGEMENT_FORBIDDEN switches to the read-only member view.

function responseStatus(err: unknown): number | undefined {
  return (err as { response?: { status?: number } }).response?.status;
}

function responseDetail(err: unknown): string {
  return (err as { response?: { data?: { detail?: string } } }).response?.data?.detail ?? '';
}

export default function MerchantTeamPage() {
  const { merchant } = useMerchantAuth();
  const { t, locale } = useMerchantLocale();
  const storeId = merchant?.store.id ?? '';

  const [team, setTeam] = useState<MerchantTeam | null>(null);
  const [isSecondary, setIsSecondary] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [loaded, setLoaded] = useState(false);

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [inviting, setInviting] = useState(false);
  const [inviteError, setInviteError] = useState<string | null>(null);
  const [inviteSuccess, setInviteSuccess] = useState<string | null>(null);

  const [confirmTarget, setConfirmTarget] = useState<MerchantTeamAccount | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionBusy, setActionBusy] = useState(false);

  const load = useCallback(async () => {
    if (!storeId) return;
    setLoadError(null);
    try {
      setTeam(await getMerchantTeam(storeId));
      setIsSecondary(false);
    } catch (err) {
      if (responseStatus(err) === 403) {
        setIsSecondary(true);
      } else {
        setLoadError(t('merchant.settings.team.errorLoad'));
      }
    } finally {
      setLoaded(true);
    }
  }, [storeId, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleInvite = async (event: React.FormEvent) => {
    event.preventDefault();
    if (inviting || !storeId) return;
    setInviteError(null);
    setInviteSuccess(null);

    if ('' === firstName.trim() || '' === lastName.trim() || '' === email.trim()) {
      setInviteError(t('merchant.settings.team.inviteValidation'));
      return;
    }

    setInviting(true);
    try {
      const account = await inviteMerchantTeamAccount(storeId, {
        firstName: firstName.trim(),
        lastName: lastName.trim(),
        email: email.trim().toLowerCase(),
        phone: phone.trim() || null,
      });
      setInviteSuccess(
        account.invitationStatus === 'delivery_failed'
          ? t('merchant.settings.team.inviteDeliveryFailed')
          : t('merchant.settings.team.inviteSent'),
      );
      setFirstName('');
      setLastName('');
      setEmail('');
      setPhone('');
      await load();
    } catch (err) {
      const detail = responseDetail(err);
      if (detail === 'MERCHANT_ACCOUNT_EMAIL_ALREADY_USED') {
        setInviteError(t('merchant.settings.team.errorEmailUsed'));
      } else if (detail === 'MERCHANT_ACCOUNT_LIMIT_REACHED') {
        setInviteError(t('merchant.settings.team.errorQuota'));
      } else if (responseStatus(err) === 429) {
        setInviteError(t('merchant.settings.team.errorRateLimited'));
      } else {
        setInviteError(t('merchant.settings.team.errorInvite'));
      }
    } finally {
      setInviting(false);
    }
  };

  const handleResend = async (account: MerchantTeamAccount) => {
    if (actionBusy || !storeId) return;
    setActionError(null);
    setActionBusy(true);
    try {
      const updated = await resendMerchantTeamInvitation(storeId, account.accountId);
      setInviteSuccess(
        updated.invitationStatus === 'delivery_failed'
          ? t('merchant.settings.team.inviteDeliveryFailed')
          : t('merchant.settings.team.resendSent'),
      );
    } catch (err) {
      setActionError(
        responseStatus(err) === 429
          ? t('merchant.settings.team.errorRateLimited')
          : t('merchant.settings.team.errorResend'),
      );
    } finally {
      setActionBusy(false);
    }
  };

  const handleRevoke = async () => {
    if (actionBusy || !storeId || !confirmTarget) return;
    setActionError(null);
    setActionBusy(true);
    try {
      await revokeMerchantTeamAccount(storeId, confirmTarget.accountId);
      setConfirmTarget(null);
      await load();
    } catch {
      setActionError(t('merchant.settings.team.errorRevoke'));
    } finally {
      setActionBusy(false);
    }
  };

  if (!loaded) {
    return (
      <div className="flex min-h-40 items-center justify-center">
        <p className="text-sm text-muted">{t('merchant.settings.team.loading')}</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div className="space-y-2">
        <Link
          href="/merchant/parametres"
          className="inline-flex items-center gap-1 text-sm font-bold text-primary hover:underline"
        >
          <ArrowLeft className="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
          {t('merchant.settings.team.back')}
        </Link>
        <h1 className="text-2xl font-black text-ink">{t('merchant.settings.team.title')}</h1>
        <p className="text-sm text-muted">{t('merchant.settings.team.pageSubtitle')}</p>
      </div>

      {loadError && (
        <div className="space-y-2 rounded-lg border border-danger/30 bg-danger/10 p-4">
          <p role="alert" aria-atomic="true" className="text-sm text-red-600">
            {loadError}
          </p>
          <Button type="button" onClick={() => void load()}>
            {t('merchant.settings.team.retry')}
          </Button>
        </div>
      )}

      {isSecondary && (
        <div className="space-y-1 rounded-lg border border-line bg-card p-4">
          <p className="font-bold text-ink">{t('merchant.settings.team.memberTitle')}</p>
          <p className="text-sm text-muted">
            {t('merchant.settings.team.memberBody').replace('{store}', merchant?.store.name ?? '')}
          </p>
        </div>
      )}

      {team && (
        <>
          <section aria-labelledby="team-list" className="space-y-3">
            <div className="flex items-baseline justify-between">
              <h2 id="team-list" className="text-sm font-bold uppercase tracking-wide text-muted">
                {t('merchant.settings.team.listTitle')}
              </h2>
              <p className="text-sm text-muted">
                {t('merchant.settings.team.quota')
                  .replace('{count}', String(team.activeOrInvitedCount))
                  .replace('{limit}', String(team.limit))}
              </p>
            </div>

            <ul className="space-y-2">
              {team.items.map((account) => {
                const isSelf = account.email === merchant?.email;
                const displayName =
                  [account.firstName, account.lastName].filter(Boolean).join(' ') || account.email;
                return (
                  <li
                    key={account.accountId}
                    className="flex flex-wrap items-start justify-between gap-2 rounded-lg border border-line bg-card p-4"
                  >
                    <div className="min-w-0">
                      <p className="font-bold text-ink">
                        {displayName}
                        {account.isPrimary && (
                          <span className="ms-2 rounded bg-soft px-1.5 py-0.5 text-xs font-bold text-primary">
                            {t('merchant.settings.team.primaryBadge')}
                          </span>
                        )}
                      </p>
                      <p className="break-all text-sm text-muted">{account.email}</p>
                      {account.phone && <p className="text-sm text-muted">{account.phone}</p>}
                      <p className="mt-1 text-xs text-muted">
                        <span>{t(`merchant.settings.team.status.${account.status}`)}</span>
                        {account.invitedAt &&
                          ` · ${t('merchant.settings.team.invitedAt').replace('{date}', formatDateTime(account.invitedAt, locale))}`}
                        {account.acceptedAt &&
                          ` · ${t('merchant.settings.team.acceptedAt').replace('{date}', formatDateTime(account.acceptedAt, locale))}`}
                      </p>
                    </div>
                    <div className="flex shrink-0 gap-2">
                      {account.status === 'invited' && (
                        <button
                          type="button"
                          disabled={actionBusy}
                          onClick={() => void handleResend(account)}
                          className="rounded-md border border-line px-3 py-1.5 text-sm font-bold text-ink hover:border-primary"
                        >
                          {t('merchant.settings.team.resend')}
                        </button>
                      )}
                      {!account.isPrimary && !isSelf && account.status !== 'revoked' && (
                        <button
                          type="button"
                          disabled={actionBusy}
                          onClick={() => {
                            setActionError(null);
                            setConfirmTarget(account);
                          }}
                          className="rounded-md border border-danger/40 px-3 py-1.5 text-sm font-bold text-red-600 hover:bg-danger/10"
                        >
                          {t('merchant.settings.team.revoke')}
                        </button>
                      )}
                    </div>
                  </li>
                );
              })}
            </ul>

            {actionError && (
              <p role="alert" aria-atomic="true" className="text-sm text-red-600">
                {actionError}
              </p>
            )}
          </section>

          {confirmTarget && (
            <div
              role="alertdialog"
              aria-modal="false"
              aria-labelledby="revoke-title"
              className="space-y-3 rounded-lg border border-danger/40 bg-danger/10 p-4"
            >
              <p id="revoke-title" className="font-bold text-ink">
                {t('merchant.settings.team.revokeConfirmTitle').replace(
                  '{name}',
                  [confirmTarget.firstName, confirmTarget.lastName].filter(Boolean).join(' ') ||
                    confirmTarget.email,
                )}
              </p>
              <p className="text-sm text-ink">{confirmTarget.email}</p>
              <p className="text-sm text-muted">{t('merchant.settings.team.revokeConfirmBody')}</p>
              <p className="text-sm text-muted">{t('merchant.settings.team.revokeHistoryKept')}</p>
              <div className="flex gap-3">
                <button
                  type="button"
                  disabled={actionBusy}
                  onClick={() => void handleRevoke()}
                  className="rounded-md bg-red-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
                >
                  {actionBusy
                    ? t('merchant.settings.team.revoking')
                    : t('merchant.settings.team.revokeConfirmAction')}
                </button>
                <button
                  type="button"
                  onClick={() => setConfirmTarget(null)}
                  className="text-sm font-bold text-muted hover:underline"
                >
                  {t('merchant.settings.team.cancel')}
                </button>
              </div>
            </div>
          )}

          <section aria-labelledby="team-invite" className="space-y-3">
            <h2
              id="team-invite"
              className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-muted"
            >
              <UserPlus className="h-4 w-4" aria-hidden="true" />
              {t('merchant.settings.team.inviteTitle')}
            </h2>
            <form onSubmit={handleInvite} className="space-y-4 rounded-lg border border-line bg-card p-4">
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1">
                  <label htmlFor="invite-first-name" className="block text-sm font-bold text-ink">
                    {t('merchant.settings.team.firstName')}
                  </label>
                  <input
                    id="invite-first-name"
                    className="w-full rounded-md border border-line px-3 py-2 text-sm"
                    value={firstName}
                    onChange={(event) => setFirstName(event.target.value)}
                  />
                </div>
                <div className="space-y-1">
                  <label htmlFor="invite-last-name" className="block text-sm font-bold text-ink">
                    {t('merchant.settings.team.lastName')}
                  </label>
                  <input
                    id="invite-last-name"
                    className="w-full rounded-md border border-line px-3 py-2 text-sm"
                    value={lastName}
                    onChange={(event) => setLastName(event.target.value)}
                  />
                </div>
                <div className="space-y-1">
                  <label htmlFor="invite-email" className="block text-sm font-bold text-ink">
                    {t('merchant.settings.team.email')}
                  </label>
                  <input
                    id="invite-email"
                    type="email"
                    className="w-full rounded-md border border-line px-3 py-2 text-sm"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                  />
                </div>
                <div className="space-y-1">
                  <label htmlFor="invite-phone" className="block text-sm font-bold text-ink">
                    {t('merchant.settings.team.phone')}
                  </label>
                  <input
                    id="invite-phone"
                    className="w-full rounded-md border border-line px-3 py-2 text-sm"
                    value={phone}
                    onChange={(event) => setPhone(event.target.value)}
                  />
                </div>
              </div>

              {inviteError && (
                <p role="alert" aria-atomic="true" className="text-sm text-red-600">
                  {inviteError}
                </p>
              )}
              {inviteSuccess && (
                <p role="status" className="text-sm text-green-700">
                  {inviteSuccess}
                </p>
              )}

              <Button type="submit" disabled={inviting}>
                {inviting
                  ? t('merchant.settings.team.inviting')
                  : t('merchant.settings.team.invite')}
              </Button>
            </form>
          </section>
        </>
      )}
    </div>
  );
}
