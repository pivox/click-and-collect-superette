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

const FEEDBACK_EXCLUDED_ROUTES = new Set([
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
    authError: 'Reconnectez-vous pour envoyer votre retour.',
    forbiddenError: 'Votre retour n’est pas autorisé pour cette page ou cette supérette.',
    disabledError: 'Le module Retour n’est pas disponible sur cette page.',
    validationError: 'Vérifiez le message de votre retour puis réessayez.',
    unconfirmedError: 'Votre retour n’a pas été confirmé par le serveur. Réessayez.',
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
    authError: 'سجّل الدخول من جديد لإرسال ملاحظتك.',
    forbiddenError: 'لا يمكن إرسال ملاحظتك من هذه الصفحة أو هذه المغازة.',
    disabledError: 'خدمة الملاحظات غير متاحة في هذه الصفحة.',
    validationError: 'تحقق من نص الملاحظة ثم حاول مرة أخرى.',
    unconfirmedError: 'لم يؤكد الخادم إرسال ملاحظتك. حاول مرة أخرى.',
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
  authError: string;
  forbiddenError: string;
  disabledError: string;
  validationError: string;
  unconfirmedError: string;
  typeLabels: Record<FeedbackType, string>;
}>;

interface FeedbackErrorLike {
  response?: {
    status?: number;
    data?: unknown;
  };
  config?: {
    method?: string;
    url?: string;
  };
  message?: string;
}

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

  const close = useCallback(() => {
    setIsOpen(false);
    setError(null);
    setSuccess(false);
    setMessage('');
    setContactConsent(false);
    setType('bug');
  }, []);

  useEffect(() => {
    let cancelled = false;
    close();
    setIsAvailable(false);
    if (!enabled || FEEDBACK_EXCLUDED_ROUTES.has(pathname)) {
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
  }, [appArea, appSubArea, close, enabled, pathname]);

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
      console.error('[feedback] submit failed', safeFeedbackErrorLog(err));
      setError(feedbackSubmitErrorMessage(err, labels));
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

function feedbackSubmitErrorMessage(
  err: unknown,
  labels: (typeof FEEDBACK_LABELS)['fr'],
): string {
  const status = feedbackErrorStatus(err);
  const code = feedbackErrorCode(err);

  if (status === 401) {
    return labels.authError;
  }
  if (status === 400 || status === 422) {
    return labels.validationError;
  }
  if (
    status === 403 &&
    (code === 'FEEDBACK_DISABLED' ||
      code === 'FEEDBACK_DISABLED_FOR_ROLE' ||
      code === 'FEEDBACK_DISABLED_FOR_AREA' ||
      code === 'FEEDBACK_AREA_FORBIDDEN')
  ) {
    return labels.disabledError;
  }
  if (status === 403) {
    return labels.forbiddenError;
  }
  if (code === 'FEEDBACK_UNEXPECTED_STATUS') {
    return labels.unconfirmedError;
  }

  return labels.submitError;
}

function safeFeedbackErrorLog(err: unknown): Record<string, string | number | undefined> {
  return compactLogFields({
    status: feedbackErrorStatus(err),
    code: feedbackErrorCode(err),
    method: feedbackErrorConfig(err)?.method,
    url: feedbackErrorConfig(err)?.url,
  });
}

function feedbackErrorStatus(err: unknown): number | undefined {
  return feedbackErrorObject(err)?.response?.status;
}

function feedbackErrorCode(err: unknown): string | undefined {
  if (err instanceof Error && err.message === 'FEEDBACK_UNEXPECTED_STATUS') {
    return err.message;
  }

  const data = feedbackErrorObject(err)?.response?.data;
  if (typeof data === 'string') {
    return data;
  }
  if (!data || typeof data !== 'object') {
    return undefined;
  }

  const record = data as Record<string, unknown>;
  const candidate = record.detail ?? record.code ?? record.message;

  return typeof candidate === 'string' ? candidate : undefined;
}

function feedbackErrorConfig(err: unknown): FeedbackErrorLike['config'] | undefined {
  return feedbackErrorObject(err)?.config;
}

function feedbackErrorObject(err: unknown): FeedbackErrorLike | undefined {
  return err && typeof err === 'object' ? (err as FeedbackErrorLike) : undefined;
}

function compactLogFields<T extends Record<string, string | number | undefined>>(fields: T): T {
  return Object.fromEntries(
    Object.entries(fields).filter(([, value]) => value !== undefined && value !== ''),
  ) as T;
}
