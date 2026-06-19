'use client';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import { MerchantCrmSection } from '@/components/admin/marchands/MerchantCrmSection';
import { Button } from '@/components/ui/Button';
import {
  createMerchantOnboarding,
  resendMerchantInvitation,
  resetMerchantTemporaryPassword,
  updateMerchant,
} from '@/lib/services/admin/merchants.service';
import { listProductGroups } from '@/lib/services/admin/product-groups.service';
import type { FirstLoginMode, Merchant, MerchantOnboardingResponse } from '@/lib/types/admin/merchants.types';
import type { ProductGroup } from '@/lib/types/admin/referentiel.types';

interface MerchantDrawerProps {
  open: boolean;
  onClose: () => void;
  merchant: Merchant | null;
  onSaved: () => void;
  // Called after each CRM mutation so the list stays in sync even if the drawer is closed without saving
  onCrmChanged?: () => void;
}

export function MerchantDrawer({ open, onClose, merchant, onSaved, onCrmChanged }: MerchantDrawerProps) {
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [shopName, setShopName] = useState('');
  const [shopAddress, setShopAddress] = useState('');
  const [shopCity, setShopCity] = useState('');
  const [shopPhone, setShopPhone] = useState('');
  const [firstLoginMode, setFirstLoginMode] = useState<FirstLoginMode>('temporary_password');
  const [productGroups, setProductGroups] = useState<ProductGroup[]>([]);
  const [selectedProductGroupIds, setSelectedProductGroupIds] = useState<string[]>([]);
  const [productGroupError, setProductGroupError] = useState<string | null>(null);
  const [onboardingResult, setOnboardingResult] = useState<MerchantOnboardingResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [temporaryPassword, setTemporaryPassword] = useState<string | null>(null);
  const [isResendingInvitation, setIsResendingInvitation] = useState(false);
  const [invitationResendError, setInvitationResendError] = useState<string | null>(null);
  const [resetError, setResetError] = useState<string | null>(null);
  const [isResettingPassword, setIsResettingPassword] = useState(false);
  const [copyMessage, setCopyMessage] = useState<string | null>(null);
  const resetRequestSeq = useRef(0);
  const currentMerchantIdRef = useRef<string | null>(null);
  const renderedMerchantId = open && merchant ? merchant.id : null;

  if (currentMerchantIdRef.current !== renderedMerchantId) {
    currentMerchantIdRef.current = renderedMerchantId;
    resetRequestSeq.current += 1;
  }

  useEffect(() => {
    if (merchant) {
      setFirstName(merchant.first_name ?? '');
      setLastName(merchant.last_name ?? '');
      setEmail(merchant.email);
      setPhone(merchant.phone ?? '');
    } else {
      setFirstName('');
      setLastName('');
      setEmail('');
      setPhone('');
    }
    setShopName('');
    setShopAddress('');
    setShopCity('');
    setShopPhone('');
    setFirstLoginMode('temporary_password');
    setSelectedProductGroupIds([]);
    setProductGroupError(null);
    setOnboardingResult(null);
    setError(null);
    setTemporaryPassword(null);
    setIsResendingInvitation(false);
    setInvitationResendError(null);
    setResetError(null);
    setCopyMessage(null);
    setIsResettingPassword(false);
  }, [merchant, open]);

  useEffect(() => {
    if (!open || merchant) return;

    let cancelled = false;
    setProductGroupError(null);
    void loadPublishedMerchantProductGroups()
      .then((groups) => {
        if (cancelled) return;
        setProductGroups(groups);
      })
      .catch(() => {
        if (cancelled) return;
        setProductGroups([]);
        setProductGroupError('Impossible de charger les groupements produits.');
      });

    return () => { cancelled = true; };
  }, [merchant, open]);

  const handleSubmit = async () => {
    if (!merchant && onboardingResult) {
      handleClose();
      return;
    }
    if (!firstName.trim()) { setError('Le prénom est obligatoire.'); return; }
    if (!lastName.trim()) { setError('Le nom est obligatoire.'); return; }
    if (!merchant && !email.trim()) { setError("L'email est obligatoire."); return; }
    if (!merchant && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      setError("L'email est invalide.");
      return;
    }
    if (!merchant && !shopName.trim()) { setError('Le nom de la supérette est obligatoire.'); return; }

    setIsSubmitting(true);
    setError(null);
    setResetError(null);
    setCopyMessage(null);
    try {
      if (merchant) {
        // email not updatable (not in AdminUpdateMerchantInput)
        await updateMerchant(merchant.id, {
          first_name: firstName.trim(),
          last_name: lastName.trim(),
          phone: phone.trim() || undefined,
        });
        setTemporaryPassword(null);
        onSaved();
      } else {
        const result = await createMerchantOnboarding({
          merchant: {
            email: email.trim(),
            first_name: firstName.trim(),
            last_name: lastName.trim(),
            phone: phone.trim() || undefined,
          },
          shop: {
            name: shopName.trim(),
            address: shopAddress.trim() || undefined,
            city: shopCity.trim() || undefined,
            phone: shopPhone.trim() || undefined,
          },
          first_login_mode: firstLoginMode,
          product_group_ids: selectedProductGroupIds,
        });
        setOnboardingResult(result);
        setTemporaryPassword(result.first_login.temporary_password ?? null);
        setInvitationResendError(null);
      }
    } catch (e) {
      const detail = axios.isAxiosError(e) ? String(e.response?.data?.detail ?? '') : '';
      setError(
        axios.isAxiosError(e) && (e.response?.status === 409 || detail.includes('ADMIN_MERCHANT_EMAIL_ALREADY_EXISTS'))
          ? 'Un compte avec cet email existe déjà.'
          : 'Une erreur est survenue. Réessayez.',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleClose = () => {
    resetRequestSeq.current += 1;
    setTemporaryPassword(null);
    setOnboardingResult(null);
    setIsResendingInvitation(false);
    setInvitationResendError(null);
    setResetError(null);
    setCopyMessage(null);
    setIsResettingPassword(false);
    if (!merchant && onboardingResult) {
      onSaved();
      return;
    }
    onClose();
  };

  const toggleProductGroup = (groupId: string) => {
    setSelectedProductGroupIds((current) => (
      current.includes(groupId)
        ? current.filter((id) => id !== groupId)
        : [...current, groupId]
    ));
  };

  const handleResetTemporaryPassword = async () => {
    if (!merchant) return;
    const confirmed = window.confirm(
      'Réinitialiser le mot de passe de ce marchand ? L’ancien mot de passe ne fonctionnera plus.',
    );
    if (!confirmed) return;

    setIsResettingPassword(true);
    setTemporaryPassword(null);
    setResetError(null);
    setCopyMessage(null);
    const requestedMerchantId = merchant.id;
    const requestSeq = resetRequestSeq.current + 1;
    resetRequestSeq.current = requestSeq;
    try {
      const response = await resetMerchantTemporaryPassword(merchant.id);
      if (
        resetRequestSeq.current !== requestSeq ||
        currentMerchantIdRef.current !== requestedMerchantId
      ) {
        return;
      }
      setTemporaryPassword(response.temporary_password);
    } catch {
      if (
        resetRequestSeq.current !== requestSeq ||
        currentMerchantIdRef.current !== requestedMerchantId
      ) {
        return;
      }
      setTemporaryPassword(null);
      setResetError('Impossible de réinitialiser le mot de passe.');
    } finally {
      if (
        resetRequestSeq.current === requestSeq &&
        currentMerchantIdRef.current === requestedMerchantId
      ) {
        setIsResettingPassword(false);
      }
    }
  };

  const handleCopyTemporaryPassword = async () => {
    if (!temporaryPassword) return;

    try {
      await navigator.clipboard.writeText(temporaryPassword);
      setCopyMessage('Mot de passe copié.');
    } catch {
      setCopyMessage('Copie impossible.');
    }
  };

  const handleResendInvitation = async () => {
    if (!onboardingResult || onboardingResult.first_login.invitation_status !== 'delivery_failed') {
      return;
    }

    setIsResendingInvitation(true);
    setInvitationResendError(null);
    try {
      await resendMerchantInvitation(onboardingResult.merchant.id);
      setOnboardingResult({
        ...onboardingResult,
        first_login: {
          ...onboardingResult.first_login,
          invitation_status: 'sent',
        },
      });
    } catch {
      setInvitationResendError("Impossible de renvoyer l'invitation email.");
    } finally {
      setIsResendingInvitation(false);
    }
  };

  const inputClass =
    'w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20';
  const opsJournal = merchant?.ops_journal ?? null;
  const isSubscriptionSuspended = merchant?.subscription_lifecycle === 'suspended';
  const isInvitationDeliveryFailed = onboardingResult?.first_login.mode === 'email_invitation'
    && onboardingResult.first_login.invitation_status === 'delivery_failed';
  const onboardingSummaryClassName = isInvitationDeliveryFailed
    ? 'rounded-md border border-amber-200 bg-amber-50 px-4 py-3'
    : 'rounded-md border border-green-200 bg-green-50 px-4 py-3';
  const onboardingHeadingClassName = isInvitationDeliveryFailed
    ? 'text-sm font-black text-amber-950'
    : 'text-sm font-black text-green-900';
  const onboardingBodyClassName = isInvitationDeliveryFailed
    ? 'mt-1 text-sm text-amber-900'
    : 'mt-1 text-sm text-green-800';
  const onboardingMetricsClassName = isInvitationDeliveryFailed
    ? 'mt-3 flex flex-wrap gap-2 text-sm font-semibold text-amber-900'
    : 'mt-3 flex flex-wrap gap-2 text-sm font-semibold text-green-900';

  return (
    <AdminDrawer
      open={open}
      onClose={handleClose}
      title={merchant ? 'Modifier le marchand' : 'Nouveau marchand'}
      onSubmit={handleSubmit}
      isSubmitting={isSubmitting}
      size={merchant ? 'md' : 'lg'}
    >
      <div className="space-y-4">
        {error && (
          <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </div>
        )}
        {onboardingResult && (
          <section className={onboardingSummaryClassName} aria-live="polite">
            <h3 className={onboardingHeadingClassName}>
              {isInvitationDeliveryFailed ? 'Onboarding créé, action requise' : 'Onboarding créé'}
            </h3>
            <p className={onboardingBodyClassName}>
              {onboardingResult.merchant.email} est rattaché à {onboardingResult.shop.name}.
            </p>
            {onboardingResult.first_login.mode === 'email_invitation' && onboardingResult.first_login.invitation_status === 'sent' && (
              <p className="mt-2 text-sm font-semibold text-green-900">
                Invitation email envoyée
              </p>
            )}
            {onboardingResult.first_login.mode === 'email_invitation' && onboardingResult.first_login.invitation_status === 'delivery_failed' && (
              <div className="mt-2 space-y-2">
                <p className="text-sm font-semibold text-amber-900">
                  Invitation email non envoyée
                </p>
                <p className="text-sm text-amber-900">
                  Le marchand et la supérette sont créés, mais l’email d’invitation n’a pas été envoyé.
                </p>
                <Button
                  type="button"
                  variant="ghost"
                  size="md"
                  onClick={() => void handleResendInvitation()}
                  disabled={isResendingInvitation}
                >
                  {isResendingInvitation ? 'Renvoi en cours…' : 'Renvoyer l’invitation'}
                </Button>
                {invitationResendError && (
                  <p className="text-sm text-status-cancel">{invitationResendError}</p>
                )}
              </div>
            )}
            <div className={onboardingMetricsClassName}>
              <span>{formatCount(onboardingResult.catalog_preload.added_count, 'ajouté', 'ajoutés')}</span>
              <span>{formatCount(onboardingResult.catalog_preload.already_existing_count, 'déjà présent', 'déjà présents')}</span>
              <span>{formatCount(onboardingResult.catalog_preload.ignored_count, 'ignoré', 'ignorés')}</span>
            </div>
          </section>
        )}
        {!merchant && temporaryPassword && (
          <section className="rounded-md border border-amber-200 bg-amber-50 px-3 py-3" aria-live="polite">
            <p className="text-xs font-black uppercase text-amber-900">Mot de passe temporaire</p>
            <div className="mt-2 flex flex-wrap items-center gap-2">
              <code className="rounded border border-amber-200 bg-white px-2 py-1 text-sm font-bold text-ink">
                {temporaryPassword}
              </code>
              <Button
                type="button"
                variant="ghost"
                size="md"
                onClick={() => void handleCopyTemporaryPassword()}
              >
                Copier
              </Button>
            </div>
            <p className="mt-2 text-sm text-amber-900">
              Ce mot de passe ne sera plus affiché après fermeture.
            </p>
            {copyMessage && <p className="mt-2 text-sm text-muted">{copyMessage}</p>}
          </section>
        )}
        <section className="space-y-3">
          {!merchant && <h3 className="text-sm font-black text-ink">Informations marchand</h3>}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label htmlFor="merchant-first-name" className="mb-1 block text-sm font-semibold">Prénom *</label>
              <input
                id="merchant-first-name"
                type="text"
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                maxLength={100}
                className={inputClass}
                disabled={!!onboardingResult}
              />
            </div>
            <div>
              <label htmlFor="merchant-last-name" className="mb-1 block text-sm font-semibold">Nom *</label>
              <input
                id="merchant-last-name"
                type="text"
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                maxLength={100}
                className={inputClass}
                disabled={!!onboardingResult}
              />
            </div>
          </div>
          {!merchant && (
            <div>
              <label htmlFor="merchant-email" className="mb-1 block text-sm font-semibold">Email *</label>
              <input
                id="merchant-email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                maxLength={200}
                className={inputClass}
                disabled={!!onboardingResult}
              />
            </div>
          )}
          {merchant && (
            <div>
              <label className="mb-1 block text-sm font-semibold text-muted">Email</label>
              <p className="rounded-md border border-line bg-soft px-3 py-2 text-sm text-muted">
                {merchant.email}
              </p>
            </div>
          )}
          <div>
            <label htmlFor="merchant-phone" className="mb-1 block text-sm font-semibold">Téléphone</label>
            <input
              id="merchant-phone"
              type="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              maxLength={30}
              placeholder="+216 XX XXX XXX"
              className={inputClass}
              disabled={!!onboardingResult}
            />
          </div>
        </section>
        {!merchant && (
          <>
            <section className="space-y-3 border-t border-line pt-4">
              <h3 className="text-sm font-black text-ink">Informations supérette</h3>
              <div>
                <label htmlFor="shop-name" className="mb-1 block text-sm font-semibold">Nom supérette *</label>
                <input
                  id="shop-name"
                  type="text"
                  value={shopName}
                  onChange={(e) => setShopName(e.target.value)}
                  maxLength={160}
                  className={inputClass}
                  disabled={!!onboardingResult}
                />
              </div>
              <div>
                <label htmlFor="shop-address" className="mb-1 block text-sm font-semibold">Adresse supérette</label>
                <input
                  id="shop-address"
                  type="text"
                  value={shopAddress}
                  onChange={(e) => setShopAddress(e.target.value)}
                  maxLength={255}
                  className={inputClass}
                  disabled={!!onboardingResult}
                />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label htmlFor="shop-city" className="mb-1 block text-sm font-semibold">Ville</label>
                  <input
                    id="shop-city"
                    type="text"
                    value={shopCity}
                    onChange={(e) => setShopCity(e.target.value)}
                    maxLength={100}
                    className={inputClass}
                    disabled={!!onboardingResult}
                  />
                </div>
                <div>
                  <label htmlFor="shop-phone" className="mb-1 block text-sm font-semibold">Téléphone supérette</label>
                  <input
                    id="shop-phone"
                    type="tel"
                    value={shopPhone}
                    onChange={(e) => setShopPhone(e.target.value)}
                    maxLength={20}
                    className={inputClass}
                    disabled={!!onboardingResult}
                  />
                </div>
              </div>
            </section>
            <section className="space-y-3 border-t border-line pt-4">
              <h3 className="text-sm font-black text-ink">Première connexion</h3>
              <label className="flex items-start gap-2 rounded-md border border-line bg-soft px-3 py-2 text-sm">
                <input
                  type="radio"
                  name="first-login-mode"
                  aria-label="Mot de passe provisoire"
                  checked={firstLoginMode === 'temporary_password'}
                  onChange={() => setFirstLoginMode('temporary_password')}
                  disabled={!!onboardingResult}
                  className="mt-1"
                />
                <span>
                  <span className="block font-semibold text-ink">Mot de passe provisoire</span>
                  <span className="block text-muted">Affiché une seule fois après création.</span>
                </span>
              </label>
              <label className="flex items-start gap-2 rounded-md border border-line bg-soft px-3 py-2 text-sm">
                <input
                  type="radio"
                  name="first-login-mode"
                  aria-label="Invitation email"
                  checked={firstLoginMode === 'email_invitation'}
                  onChange={() => setFirstLoginMode('email_invitation')}
                  disabled={!!onboardingResult}
                  className="mt-1"
                />
                <span>
                  <span className="block font-semibold text-ink">Invitation email</span>
                  <span className="block text-muted">Le marchand reçoit un lien expirant à usage unique.</span>
                </span>
              </label>
            </section>
            <section className="space-y-3 border-t border-line pt-4">
              <h3 className="text-sm font-black text-ink">Préchargement catalogue</h3>
              {productGroupError && (
                <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
                  {productGroupError}
                </div>
              )}
              {productGroups.length === 0 && !productGroupError && (
                <p className="text-sm text-muted">Aucun groupement publié disponible.</p>
              )}
              {productGroups.map((group) => (
                <label
                  key={group.id}
                  className="flex items-center justify-between gap-3 rounded-md border border-line px-3 py-2 text-sm"
                >
                  <span className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      aria-label={group.name_fr}
                      checked={selectedProductGroupIds.includes(group.id)}
                      onChange={() => toggleProductGroup(group.id)}
                      disabled={!!onboardingResult}
                    />
                    <span className="font-semibold text-ink">{group.name_fr}</span>
                  </span>
                  <span className="text-xs text-muted">{group.items_count} produits</span>
                </label>
              ))}
            </section>
          </>
        )}
        {isSubscriptionSuspended && (
          <section className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3">
            <h3 className="text-sm font-black text-amber-900">Suspension douce abonnement</h3>
            <p className="mt-1 text-sm text-amber-800">
              Les nouvelles Kadhia sont bloquées, le catalogue et l’historique restent conservés.
            </p>
          </section>
        )}
        {merchant && (
          <section className="rounded-md border border-line bg-soft px-4 py-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h3 className="text-sm font-black text-ink">Accès marchand</h3>
                <p className="mt-1 text-sm text-muted">
                  L’admin ne voit jamais le mot de passe actuel.
                </p>
              </div>
              <Button
                type="button"
                variant="ghost"
                size="md"
                onClick={() => void handleResetTemporaryPassword()}
                disabled={isResettingPassword}
              >
                {isResettingPassword ? 'Réinitialisation…' : 'Réinitialiser le mot de passe'}
              </Button>
            </div>
            {resetError && (
              <div className="mt-3 rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
                {resetError}
              </div>
            )}
            {temporaryPassword && (
              <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-3" aria-live="polite">
                <p className="text-xs font-black uppercase text-amber-900">Mot de passe temporaire</p>
                <div className="mt-2 flex flex-wrap items-center gap-2">
                  <code className="rounded border border-amber-200 bg-white px-2 py-1 text-sm font-bold text-ink">
                    {temporaryPassword}
                  </code>
                  <Button
                    type="button"
                    variant="ghost"
                    size="md"
                    onClick={() => void handleCopyTemporaryPassword()}
                  >
                    Copier
                  </Button>
                </div>
                <p className="mt-2 text-sm text-amber-900">
                  Ce mot de passe ne sera plus affiché après fermeture.
                </p>
                {copyMessage && <p className="mt-2 text-sm text-muted">{copyMessage}</p>}
              </div>
            )}
          </section>
        )}
        {merchant && (
          <MerchantCrmSection
            merchantId={merchant.id}
            initialCrm={merchant.crm ?? null}
            onCrmChanged={onCrmChanged}
          />
        )}
        {merchant && opsJournal && (
          <section className="rounded-md border border-line bg-soft px-4 py-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h3 className="text-sm font-black text-ink">Journal opérationnel</h3>
              <HealthBadge status={opsJournal.health_status} />
            </div>
            <div className="mt-3 grid gap-3 sm:grid-cols-3">
              <Metric
                label="Retards"
                value={opsJournal.overdue_orders_count.toString()}
                tone={opsJournal.overdue_orders_count > 0 ? 'danger' : 'neutral'}
              />
              <Metric
                label="Annulations"
                value={opsJournal.cancelled_orders_count.toString()}
                tone={opsJournal.cancelled_orders_count > 0 ? 'warning' : 'neutral'}
              />
              <Metric
                label="Dernière activité"
                value={formatLastActivity(opsJournal.last_activity_status)}
                detail={formatLastActivityDate(opsJournal.last_activity_at)}
              />
            </div>
            <div className="mt-4 border-t border-line pt-3">
              <h4 className="text-xs font-black uppercase text-muted">Vue santé</h4>
              <div className="mt-3 grid gap-3 sm:grid-cols-3">
                <Metric label="Commandes reçues" value={opsJournal.received_orders_count.toString()} />
                <Metric
                  label="Acceptées"
                  value={opsJournal.accepted_orders_count.toString()}
                  tone={opsJournal.accepted_orders_count > 0 ? 'success' : 'neutral'}
                />
                <Metric
                  label="Refusées"
                  value={opsJournal.rejected_orders_count.toString()}
                  tone={opsJournal.rejected_orders_count > 0 ? 'danger' : 'neutral'}
                />
                <Metric label="Délai moyen" value={formatAverageResponse(opsJournal.average_response_minutes)} />
                <Metric
                  label="Incidents"
                  value={`${opsJournal.open_incidents_count} / ${opsJournal.incidents_count}`}
                  tone={opsJournal.open_incidents_count > 0 ? 'danger' : opsJournal.incidents_count > 0 ? 'warning' : 'neutral'}
                />
                <Metric
                  label="Relances paiement"
                  value={opsJournal.payment_reminders_count.toString()}
                  tone={opsJournal.payment_reminders_count > 0 ? 'warning' : 'neutral'}
                />
                <Metric label="Actions admin" value={opsJournal.admin_actions_count.toString()} />
              </div>
            </div>
          </section>
        )}
      </div>
    </AdminDrawer>
  );
}

function Metric({
  label,
  value,
  detail,
  tone = 'neutral',
}: {
  label: string;
  value: string;
  detail?: string;
  tone?: 'neutral' | 'success' | 'warning' | 'danger';
}) {
  const valueColor = {
    neutral: 'text-ink',
    success: 'text-green-700',
    warning: 'text-amber-700',
    danger: 'text-status-cancel',
  }[tone];

  return (
    <div>
      <div className="text-xs font-semibold uppercase text-muted">{label}</div>
      <div className={`mt-1 text-lg font-black ${valueColor}`}>{value}</div>
      {detail && <div className="mt-1 text-xs text-muted">{detail}</div>}
    </div>
  );
}

function HealthBadge({ status }: { status: 'healthy' | 'watch' | 'risk' }) {
  const config = {
    healthy: { label: 'Sain', className: 'bg-green-50 text-green-700' },
    watch: { label: 'À surveiller', className: 'bg-amber-50 text-amber-800' },
    risk: { label: 'Risque', className: 'bg-status-cancel-bg text-status-cancel' },
  }[status];

  return (
    <span className={`rounded-full px-2 py-0.5 text-xs font-bold ${config.className}`}>
      {config.label}
    </span>
  );
}

function formatAverageResponse(value: number | null): string {
  if (null === value) {
    return '—';
  }

  return `${value} min`;
}

function formatCount(value: number, singular: string, plural: string): string {
  return `${value} ${value > 1 ? plural : singular}`;
}

async function loadPublishedMerchantProductGroups(): Promise<ProductGroup[]> {
  const limit = 50;
  let page = 1;
  let total = 0;
  const groups: ProductGroup[] = [];

  do {
    const response = await listProductGroups({ status: 'published', limit, page });
    groups.push(...response.items.filter((group) => group.visibility === 'merchant'));
    total = response.total;
    page += 1;
  } while ((page - 1) * limit < total);

  return groups;
}

function formatLastActivity(status: string | null): string {
  if (!status) {
    return 'Aucune activité';
  }

  return {
    draft: 'Brouillon',
    submitted: 'Envoyée',
    accepted: 'Acceptée',
    partially_accepted: 'Partielle',
    rejected: 'Refusée',
    preparing: 'Préparation',
    ready: 'Prête',
    pickup_pending: 'Retrait',
    completed: 'Terminée',
    cancelled: 'Annulée',
  }[status] ?? status;
}

function formatLastActivityDate(value: string | null): string | undefined {
  if (!value) {
    return undefined;
  }

  return new Date(value).toLocaleString('fr-TN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}
