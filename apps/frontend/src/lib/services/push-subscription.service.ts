import { apiClient } from '@/lib/api';

const VAPID_PUBLIC_KEY = process.env.NEXT_PUBLIC_VAPID_PUBLIC_KEY;

export type PushSubscriptionRole = 'customer' | 'merchant';

export async function getPushPermissionState(): Promise<PermissionStatus | 'unsupported'> {
  if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
    return 'unsupported';
  }

  return Notification.permission as PermissionStatus;
}

export async function isWebPushSupported(): Promise<boolean> {
  return (
    'Notification' in window &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    !!VAPID_PUBLIC_KEY
  );
}

export async function isPWAInstalledOnIOS(): Promise<boolean> {
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
  return isIOS && window.navigator.standalone === true;
}

export async function subscribeToPush(role: PushSubscriptionRole): Promise<void> {
  if (!VAPID_PUBLIC_KEY) {
    throw new Error('NEXT_PUBLIC_VAPID_PUBLIC_KEY not configured');
  }

  const registration = await navigator.serviceWorker.ready;
  const subscription = await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
  });

  const endpoint = subscription.endpoint;
  const p256dh = subscription.getKey('p256dh');
  const auth = subscription.getKey('auth');

  if (!endpoint || !p256dh || !auth) {
    throw new Error('Failed to extract subscription keys');
  }

  const endpoint_str = endpoint;
  const p256dhKey = btoa(String.fromCharCode.apply(null, Array.from(new Uint8Array(p256dh))));
  const authKey = btoa(String.fromCharCode.apply(null, Array.from(new Uint8Array(auth))));

  const endpoint_url = role === 'customer' ? '/api/me/push-subscriptions' : '/api/merchant/push-subscriptions';

  await apiClient.post(endpoint_url, {
    endpoint: endpoint_str,
    p256dhKey,
    authKey,
    userAgent: navigator.userAgent,
  });
}

export async function unsubscribeFromPush(role: PushSubscriptionRole): Promise<void> {
  const registration = await navigator.serviceWorker.ready;
  const subscription = await registration.pushManager.getSubscription();

  if (!subscription) {
    return; // Already unsubscribed
  }

  const endpoint = subscription.endpoint;

  // Delete from backend
  const endpoint_url = role === 'customer' ? '/api/me/push-subscriptions' : '/api/merchant/push-subscriptions';
  await apiClient.delete(endpoint_url, {
    data: {
      endpoint,
    },
  });

  // Unsubscribe from browser
  await subscription.unsubscribe();
}

export async function getPushSubscriptionStatus(role: PushSubscriptionRole): Promise<boolean> {
  const registration = await navigator.serviceWorker.ready;
  const subscription = await registration.pushManager.getSubscription();
  return !!subscription;
}

// Utility: Convert VAPID public key from base64url to Uint8Array
function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding)
    .replace(/\-/g, '+')
    .replace(/_/g, '/');

  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }

  return outputArray;
}
