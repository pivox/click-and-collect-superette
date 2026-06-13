'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { MessageSquare, Send, X } from 'lucide-react';
import { usePathname } from 'next/navigation';
import { Button } from '@/components/ui/Button';
import { cn } from '@/lib/cn';
import {
  createFeedback,
  getCurrentFeedbackSettings,
} from '@/lib/services/feedback.service';
import type { FeedbackAppArea, FeedbackType } from '@/lib/types/feedback.types';

interface FeedbackProviderProps {
  appArea: FeedbackAppArea;
  enabled: boolean;
  shopId?: string | null;
  locale?: string;
}

const AUTH_ROUTES = new Set([
  '/login',
  '/register',
  '/forgot-password',
  '/reset-password',
  '/merchant/login',
  '/admin/login',
  '/offline',
]);

const FEEDBACK_LABELS = {
  fr: {
    button: 'Retour',
    title: 'Votre retour',
    helper: 'Signalez un bug, une incompréhension ou une idée depuis cette page.',
    closeOverlay: 'Fermer le retour',
    close: 'Fermer',
    success: 'Merci, votre retour a bien été envoyé.',
    type: 'Type de retour',
    message: 'Message',
    contactConsent: 'J’accepte d’être recontacté si besoin',
    submit: 'Envoyer',
    minLengthError: 'Votre retour doit contenir au moins 5 caractères.',
    submitError: 'Impossible d’envoyer votre retour. Réessayez.',
    typeLabels: {
      bug: 'Bug',
      idea: 'Idée',
      confusing: 'Incompréhension',
      other: 'Autre',
    },
  },
  ar: {
    button: 'ملاحظات',
    title: 'ملاحظتك',
    helper: 'أرسل خللا أو نقطة غير واضحة أو فكرة من هذه الصفحة.',
    closeOverlay: 'إغلاق الملاحظة',
    close: 'إغلاق',
    success: 'تم إرسال ملاحظتك بنجاح.',
    type: 'نوع الملاحظة',
    message: 'الرسالة',
    contactConsent: 'أوافق على أن يتم التواصل معي عند الحاجة',
    submit: 'إرسال',
    minLengthError: 'يجب أن تحتوي ملاحظتك على 5 أحرف على الأقل.',
    submitError: 'تعذر إرسال ملاحظتك. حاول مرة أخرى.',
    typeLabels: {
      bug: 'خلل',
      idea: 'فكرة',
      confusing: 'غير واضح',
      other: 'آخر',
    },
  },
} satisfies Record<string, {
  button: string;
  title: string;
  helper: string;
  closeOverlay: string;
  close: string;
  success: string;
  type: string;
  message: string;
  contactConsent: string;
  submit: string;
  minLengthError: string;
  submitError: string;
  typeLabels: Record<FeedbackType, string>;
}>;

function labelsForLocale(locale: string): (typeof FEEDBACK_LABELS)['fr'] {
  return locale === 'ar' ? FEEDBACK_LABELS.ar : FEEDBACK_LABELS.fr;
}

function directionForLocale(locale: string): 'ltr' | 'rtl' {
  return locale === 'ar' ? 'rtl' : 'ltr';
}

export function FeedbackProvider({
  appArea,
  enabled,
  shopId,
  locale = 'fr',
}: FeedbackProviderProps) {
  const pathname = usePathname() ?? '/';
  const [isAvailable, setIsAvailable] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [type, setType] = useState<FeedbackType>('bug');
  const [message, setMessage] = useState('');
  const [contactConsent, setContactConsent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const appSubArea = useMemo(() => routeToSubArea(pathname), [pathname]);
  const labels = labelsForLocale(locale);

  useEffect(() => {
    let cancelled = false;
    setIsAvailable(false);
    if (!enabled || AUTH_ROUTES.has(pathname)) {
      return () => {
        cancelled = true;
      };
    }

    void getCurrentFeedbackSettings({ appArea, appSubArea })
      .then((settings) => {
        if (!cancelled) {
          setIsAvailable(settings.enabled);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setIsAvailable(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [appArea, appSubArea, enabled, pathname]);

  const close = useCallback(() => {
    setIsOpen(false);
    setError(null);
    setSuccess(false);
    setMessage('');
    setContactConsent(false);
    setType('bug');
  }, []);

  const submit = async () => {
    if (message.trim().length < 5) {
      setError(labels.minLengthError);
      return;
    }
    setIsSending(true);
    setError(null);
    try {
      await createFeedback({
        type,
        message: message.trim(),
        appArea,
        appSubArea,
        pageUrl: pathname,
        routeName: appSubArea,
        pageTitle: typeof document === 'undefined' ? null : document.title,
        locale,
        viewportWidth: typeof window === 'undefined' ? null : window.innerWidth,
        viewportHeight: typeof window === 'undefined' ? null : window.innerHeight,
        shopId,
        contactConsent,
      });
      setSuccess(true);
      setMessage('');
      setContactConsent(false);
    } catch (err) {
      console.error('[feedback] submit failed', err);
      setError(labels.submitError);
    } finally {
      setIsSending(false);
    }
  };

  if (!isAvailable) {
    return null;
  }

  return (
    <>
      <button
        type="button"
        aria-label={labels.button}
        onClick={() => setIsOpen(true)}
        className={cn(
          'fixed z-30 inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-[#1f6f54] px-4 text-sm font-black text-white shadow-floating transition-colors hover:bg-[#185943]',
          'bottom-24 right-4 md:bottom-auto md:right-0 md:top-1/2 md:-translate-y-1/2 md:translate-x-[38%] md:rotate-90',
        )}
      >
        <MessageSquare className="h-4 w-4 md:-rotate-90" aria-hidden="true" />
        <span>{labels.button}</span>
      </button>

      {isOpen && (
        <div className="fixed inset-0 z-50">
          <button
            type="button"
            aria-label={labels.closeOverlay}
            className="absolute inset-0 bg-black/45"
            onClick={close}
          />
          <section
            role="dialog"
            aria-modal="true"
            aria-label={labels.title}
            dir={directionForLocale(locale)}
            lang={locale === 'ar' ? 'ar' : 'fr'}
            className="absolute inset-x-0 bottom-0 max-h-[92vh] overflow-auto rounded-t-md bg-card p-5 shadow-xl md:inset-y-0 md:left-auto md:w-full md:max-w-md md:rounded-none"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-black text-ink">{labels.title}</h2>
                <p className="mt-1 text-sm text-muted">
                  {labels.helper}
                </p>
              </div>
              <button
                type="button"
                aria-label={labels.close}
                className="inline-flex h-10 w-10 items-center justify-center rounded-md border border-line bg-white text-ink"
                onClick={close}
              >
                <X className="h-4 w-4" aria-hidden="true" />
              </button>
            </div>

            {success ? (
              <div className="mt-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">
                {labels.success}
              </div>
            ) : (
              <div className="mt-5 space-y-4">
                <label className="block text-sm font-bold text-ink">
                  {labels.type}
                  <select
                    value={type}
                    onChange={(event) => setType(event.target.value as FeedbackType)}
                    className="mt-2 w-full rounded-md border border-line bg-card px-3 py-2 text-sm font-normal text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                  >
                    {Object.entries(labels.typeLabels).map(([value, label]) => (
                      <option key={value} value={value}>
                        {label}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="block text-sm font-bold text-ink">
                  {labels.message}
                  <textarea
                    value={message}
                    minLength={5}
                    maxLength={2000}
                    onChange={(event) => setMessage(event.target.value)}
                    className="mt-2 min-h-36 w-full rounded-md border border-line bg-card px-3 py-2 text-sm font-normal text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                  />
                </label>

                <label className="flex items-start gap-3 text-sm text-ink">
                  <input
                    type="checkbox"
                    checked={contactConsent}
                    onChange={(event) => setContactConsent(event.target.checked)}
                    className="mt-1 h-4 w-4 rounded border-line text-primary"
                  />
                  <span>{labels.contactConsent}</span>
                </label>

                {error && (
                  <div role="alert" className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
                    {error}
                  </div>
                )}

                <Button
                  type="button"
                  variant="primary"
                  size="md"
                  full
                  disabled={isSending || message.trim().length < 5}
                  onClick={() => void submit()}
                >
                  <Send className="h-4 w-4" aria-hidden="true" />
                  {labels.submit}
                </Button>
              </div>
            )}
          </section>
        </div>
      )}
    </>
  );
}

function routeToSubArea(pathname: string): string {
  const normalized = pathname
    .replace(/^\/+/, '')
    .replace(/[^A-Za-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');

  return normalized || 'home';
}
