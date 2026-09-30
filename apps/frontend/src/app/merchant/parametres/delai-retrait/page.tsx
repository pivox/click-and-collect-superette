'use client';

import Link from 'next/link';
import { ArrowLeft } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { useMerchantLocale } from '@/lib/i18n/MerchantLocaleContext';
import { formatDateTime } from '@/lib/date-formatter';
import {
  getMerchantOrderingPolicy,
  updateMerchantOrderingPolicy,
} from '@/lib/services/merchant-ordering-policy.service';

// ORDER-LEAD-003: merchant configuration of the minimum pickup lead time.

const MAX_LEAD_TIME_MINUTES = 10080; // 7 days
const HIGH_VALUE_WARNING_MINUTES = 2880; // 48 h
const TUNIS_TIMEZONE = 'Africa/Tunis';

const PRESETS: Array<{ minutes: number; labelKey: string }> = [
  { minutes: 0, labelKey: 'merchant.settings.orderingPolicy.presets.none' },
  { minutes: 60, labelKey: 'merchant.settings.orderingPolicy.presets.h1' },
  { minutes: 120, labelKey: 'merchant.settings.orderingPolicy.presets.h2' },
  { minutes: 240, labelKey: 'merchant.settings.orderingPolicy.presets.h4' },
  { minutes: 360, labelKey: 'merchant.settings.orderingPolicy.presets.h6' },
  { minutes: 720, labelKey: 'merchant.settings.orderingPolicy.presets.h12' },
  { minutes: 1440, labelKey: 'merchant.settings.orderingPolicy.presets.h24' },
  { minutes: 2880, labelKey: 'merchant.settings.orderingPolicy.presets.h48' },
];

function fill(template: string, values: Record<string, string>): string {
  return Object.entries(values).reduce(
    (acc, [name, value]) => acc.replaceAll(`{${name}}`, value),
    template,
  );
}

function responseStatus(err: unknown): number | undefined {
  return (err as { response?: { status?: number } }).response?.status;
}

export default function MerchantOrderingPolicyPage() {
  const { merchant, refresh } = useMerchantAuth();
  const { t, locale } = useMerchantLocale();
  const storeId = merchant?.store.id ?? '';

  const [initialMinutes, setInitialMinutes] = useState<number | null>(null);
  const [updatedAt, setUpdatedAt] = useState<string | null>(null);
  const [selectedPreset, setSelectedPreset] = useState<number | 'custom'>(0);
  const [customHours, setCustomHours] = useState('0');
  const [customMinutes, setCustomMinutes] = useState('0');
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [saveError, setSaveError] = useState<string | null>(null);

  const applyServerValue = useCallback((minutes: number, serverUpdatedAt: string | null) => {
    setInitialMinutes(minutes);
    setUpdatedAt(serverUpdatedAt);
    if (PRESETS.some((preset) => preset.minutes === minutes)) {
      setSelectedPreset(minutes);
    } else {
      setSelectedPreset('custom');
    }
    setCustomHours(String(Math.floor(minutes / 60)));
    setCustomMinutes(String(minutes % 60));
  }, []);

  const load = useCallback(async () => {
    if (!storeId) return;
    setLoadError(null);
    try {
      const policy = await getMerchantOrderingPolicy(storeId);
      applyServerValue(policy.minimumPickupLeadTimeMinutes, policy.updatedAt);
    } catch {
      setLoadError(t('merchant.settings.orderingPolicy.errorLoad'));
    }
  }, [storeId, applyServerValue, t]);

  useEffect(() => {
    void load();
  }, [load]);

  // Effective value in minutes, or null when the custom input is invalid.
  const effectiveMinutes = useMemo<number | null>(() => {
    if (selectedPreset !== 'custom') return selectedPreset;
    if (!/^\d+$/.test(customHours.trim()) || !/^\d+$/.test(customMinutes.trim())) return null;
    return Number.parseInt(customHours, 10) * 60 + Number.parseInt(customMinutes, 10);
  }, [selectedPreset, customHours, customMinutes]);

  const outOfRange = effectiveMinutes !== null && effectiveMinutes > MAX_LEAD_TIME_MINUTES;
  const validationError = effectiveMinutes === null || outOfRange;
  const dirty = initialMinutes !== null && effectiveMinutes !== initialMinutes;

  const preview = useMemo(() => {
    if (effectiveMinutes === null || outOfRange) return null;
    const now = new Date();
    const target = new Date(now.getTime() + effectiveMinutes * 60_000);
    const intlLocale = locale === 'ar' ? 'ar-TN' : 'fr-FR';
    const from = new Intl.DateTimeFormat(intlLocale, {
      timeZone: TUNIS_TIMEZONE,
      hour: '2-digit',
      minute: '2-digit',
    }).format(now);
    const to = new Intl.DateTimeFormat(intlLocale, {
      timeZone: TUNIS_TIMEZONE,
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    }).format(target);
    return fill(t('merchant.settings.orderingPolicy.preview'), { from, to });
  }, [effectiveMinutes, outOfRange, locale, t]);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!storeId || saving || effectiveMinutes === null || outOfRange || !dirty) return;
    setSaving(true);
    setSaveError(null);
    setSaved(false);
    try {
      const policy = await updateMerchantOrderingPolicy(storeId, effectiveMinutes);
      applyServerValue(policy.minimumPickupLeadTimeMinutes, policy.updatedAt);
      setSaved(true);
    } catch (err) {
      const status = responseStatus(err);
      if (status === 403) {
        setSaveError(t('merchant.settings.orderingPolicy.errorForbidden'));
        // Membership may have been revoked while the screen was open.
        void refresh();
      } else if (status === 422) {
        setSaveError(t('merchant.settings.orderingPolicy.errorValidation'));
      } else {
        setSaveError(t('merchant.settings.orderingPolicy.errorSave'));
      }
    } finally {
      setSaving(false);
    }
  };

  const handleCancel = () => {
    if (initialMinutes === null) return;
    applyServerValue(initialMinutes, updatedAt);
    setSaveError(null);
    setSaved(false);
  };

  if (initialMinutes === null) {
    return (
      <div className="flex min-h-40 flex-col items-center justify-center gap-3">
        {loadError ? (
          <>
            <p role="alert" aria-atomic="true" className="text-sm text-red-600">
              {loadError}
            </p>
            <Button type="button" onClick={() => void load()}>
              {t('merchant.settings.orderingPolicy.retry')}
            </Button>
          </>
        ) : (
          <p className="text-sm text-muted">{t('merchant.settings.orderingPolicy.loading')}</p>
        )}
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
          {t('merchant.settings.orderingPolicy.back')}
        </Link>
        <h1 className="text-2xl font-black text-ink">
          {t('merchant.settings.orderingPolicy.cardTitle')}
        </h1>
        <p className="text-sm text-muted">{t('merchant.settings.orderingPolicy.pageSubtitle')}</p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-4 rounded-lg border border-line bg-card p-4">
        <fieldset className="space-y-2">
          <legend className="text-sm font-bold text-ink">
            {t('merchant.settings.orderingPolicy.presetsLegend')}
          </legend>
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
            {PRESETS.map((preset) => (
              <label
                key={preset.minutes}
                className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm ${
                  selectedPreset === preset.minutes
                    ? 'border-primary bg-soft font-bold text-ink'
                    : 'border-line text-muted'
                }`}
              >
                <input
                  type="radio"
                  name="lead-time-preset"
                  className="accent-primary"
                  checked={selectedPreset === preset.minutes}
                  onChange={() => {
                    setSelectedPreset(preset.minutes);
                    setSaved(false);
                    setSaveError(null);
                  }}
                />
                {t(preset.labelKey)}
              </label>
            ))}
            <label
              className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm ${
                selectedPreset === 'custom'
                  ? 'border-primary bg-soft font-bold text-ink'
                  : 'border-line text-muted'
              }`}
            >
              <input
                type="radio"
                name="lead-time-preset"
                className="accent-primary"
                checked={selectedPreset === 'custom'}
                onChange={() => {
                  setSelectedPreset('custom');
                  setSaved(false);
                  setSaveError(null);
                }}
              />
              {t('merchant.settings.orderingPolicy.presets.custom')}
            </label>
          </div>
        </fieldset>

        {selectedPreset === 'custom' && (
          <div className="flex flex-wrap items-end gap-3">
            <div className="space-y-1">
              <label htmlFor="custom-hours" className="block text-sm font-bold text-ink">
                {t('merchant.settings.orderingPolicy.customHours')}
              </label>
              <input
                id="custom-hours"
                type="number"
                min={0}
                max={168}
                inputMode="numeric"
                className="w-24 rounded-md border border-line px-3 py-2 text-sm"
                value={customHours}
                onChange={(event) => {
                  setCustomHours(event.target.value);
                  setSaved(false);
                  setSaveError(null);
                }}
              />
            </div>
            <div className="space-y-1">
              <label htmlFor="custom-minutes" className="block text-sm font-bold text-ink">
                {t('merchant.settings.orderingPolicy.customMinutes')}
              </label>
              <input
                id="custom-minutes"
                type="number"
                min={0}
                max={59}
                inputMode="numeric"
                className="w-24 rounded-md border border-line px-3 py-2 text-sm"
                value={customMinutes}
                onChange={(event) => {
                  setCustomMinutes(event.target.value);
                  setSaved(false);
                  setSaveError(null);
                }}
              />
            </div>
            {effectiveMinutes !== null && !outOfRange && (
              <p className="pb-2 text-sm text-muted">
                {fill(t('merchant.settings.orderingPolicy.customEquivalent'), {
                  minutes: String(effectiveMinutes),
                })}
              </p>
            )}
          </div>
        )}

        {validationError && (
          <p role="alert" aria-atomic="true" className="text-sm text-red-600">
            {t('merchant.settings.orderingPolicy.validationRange')}
          </p>
        )}

        {!validationError && preview && (
          <div className="space-y-1 rounded-md bg-soft p-3">
            <p className="text-sm text-ink">{preview}</p>
            <p className="text-xs text-muted">
              {t('merchant.settings.orderingPolicy.previewNote')}
            </p>
          </div>
        )}

        {!validationError &&
          effectiveMinutes !== null &&
          effectiveMinutes >= HIGH_VALUE_WARNING_MINUTES && (
            <p className="rounded-md border border-amber-300 bg-amber-50 p-2 text-sm text-amber-800">
              {t('merchant.settings.orderingPolicy.highValueWarning')}
            </p>
          )}

        <p className="text-sm text-muted">
          {t('merchant.settings.orderingPolicy.nonRetroactive')}
        </p>

        {saveError && (
          <p role="alert" aria-atomic="true" className="text-sm text-red-600">
            {saveError}
          </p>
        )}
        {saved && (
          <p role="status" className="text-sm text-green-700">
            {t('merchant.settings.orderingPolicy.saved')}
          </p>
        )}
        {updatedAt && (
          <p className="text-xs text-muted">
            {fill(t('merchant.settings.orderingPolicy.lastUpdated'), {
              date: formatDateTime(updatedAt, locale),
            })}
          </p>
        )}

        <div className="flex items-center gap-3">
          <Button type="submit" disabled={saving || validationError || !dirty}>
            {saving
              ? t('merchant.settings.orderingPolicy.saving')
              : t('merchant.settings.orderingPolicy.save')}
          </Button>
          {dirty && !saving && (
            <button
              type="button"
              onClick={handleCancel}
              className="text-sm font-bold text-muted hover:underline"
            >
              {t('merchant.settings.orderingPolicy.cancel')}
            </button>
          )}
        </div>
      </form>
    </div>
  );
}
