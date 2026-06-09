'use client';

import { useCallback, useEffect, useState } from 'react';
import { Bell } from 'lucide-react';
import {
  getPushPermissionState,
  isWebPushSupported,
  isPWAInstalledOnIOS,
  subscribeToPush,
  unsubscribeFromPush,
  getPushSubscriptionStatus,
  type PushSubscriptionRole,
} from '@/lib/services/push-subscription.service';
import { useClientLocale } from '@/lib/i18n/ClientLocaleContext';

type PushState = 'unsupported' | 'unsupported_ios_not_installed' | 'loading' | 'denied' | 'subscribed' | 'unsubscribed' | 'error';

interface PushNotificationToggleProps {
  role: PushSubscriptionRole;
}

export function PushNotificationToggle({ role }: PushNotificationToggleProps) {
  const { t } = useClientLocale();
  const [state, setState] = useState<PushState>('loading');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const loadState = useCallback(async () => {
    try {
      const supported = await isWebPushSupported();
      if (!supported) {
        const isIosPwa = await isPWAInstalledOnIOS();
        // isPWAInstalledOnIOS returns true = iOS+standalone (which actually supports push on iOS 16.4+)
        // Show iOS install help only when on iOS but NOT installed as PWA
        const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent);
        if (isIos && !isIosPwa) {
          // iOS without PWA installed
          setState('unsupported_ios_not_installed');
        } else {
          setState('unsupported');
        }
        return;
      }

      const permission = await getPushPermissionState();
      if (permission === 'denied') {
        setState('denied');
        return;
      }

      const isSubscribed = await getPushSubscriptionStatus(role);
      setState(isSubscribed ? 'subscribed' : 'unsubscribed');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
      setState('error');
    }
  }, [role]);

  useEffect(() => {
    void loadState();
  }, [loadState]);

  const handleToggle = async () => {
    setIsSubmitting(true);
    setError(null);

    try {
      if (state === 'subscribed') {
        await unsubscribeFromPush(role);
      } else if (state === 'unsubscribed') {
        // Request permission if not already granted
        if ((await getPushPermissionState()) === 'default') {
          const permission = await Notification.requestPermission();
          if (permission !== 'granted') {
            setState('denied');
            setIsSubmitting(false);
            return;
          }
        }
        await subscribeToPush(role);
      }

      // Reload state after toggle
      await loadState();
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to update push subscription';
      setError(message);
      setState('error');
    } finally {
      setIsSubmitting(false);
    }
  };

  // Unsupported browsers
  if (state === 'unsupported') {
    return null; // Silent fail for unsupported browsers
  }

  // iOS without PWA installed
  if (state === 'unsupported_ios_not_installed') {
    return (
      <div className="rounded-md bg-amber/10 px-3 py-2 text-xs font-semibold text-amber">
        {t('notifications.ios_help') || 'Add Kadhia to your home screen to enable notifications'}
      </div>
    );
  }

  // Loading state
  if (state === 'loading') {
    return (
      <div className="inline-block h-6 w-6 animate-spin rounded-full border-2 border-muted border-t-primary" />
    );
  }

  // Permission denied
  if (state === 'denied') {
    return (
      <div className="rounded-md bg-danger/10 px-3 py-2 text-xs font-semibold text-danger">
        {t('notifications.permission_denied') || 'Enable notifications in browser settings'}
      </div>
    );
  }

  // Error state
  if (state === 'error') {
    return (
      <div className="rounded-md bg-danger/10 px-3 py-2 text-xs font-semibold text-danger">
        {error || t('notifications.error') || 'Failed to update notifications'}
      </div>
    );
  }

  // Toggle button for subscribed/unsubscribed states
  return (
    <button
      type="button"
      disabled={isSubmitting}
      onClick={handleToggle}
      className="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold transition-colors disabled:opacity-50"
      style={{
        backgroundColor: state === 'subscribed' ? '#1f7a4d' : '#f0f0f0',
        color: state === 'subscribed' ? '#ffffff' : '#333333',
      }}
    >
      <Bell className="h-4 w-4" aria-hidden="true" />
      {state === 'subscribed'
        ? t('notifications.enabled') || 'Enabled'
        : t('notifications.disabled') || 'Disabled'}
    </button>
  );
}
