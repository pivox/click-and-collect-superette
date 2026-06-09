// Custom Service Worker handlers for Web Push notifications (S14-003)
// This file is bundled into the Workbox-generated sw.js via next.config.mjs customWorkerSrc

declare const self: ServiceWorkerGlobalScope;

// Handle incoming push notifications
self.addEventListener('push', (event: PushEvent) => {
  const fallback = {
    title: 'Kadhia',
    body: '',
    url: '/',
  };

  let data = fallback;
  try {
    data = event.data?.json() ?? fallback;
  } catch {
    // Keep fallback if JSON parsing fails
  }

  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: '/icons/icon-192.png',
      badge: '/icons/icon-192.png',
      data: { url: data.url ?? '/' },
    })
  );
});

// Handle notification click events
self.addEventListener('notificationclick', (event: NotificationEvent) => {
  event.notification.close();

  const rawUrl = event.notification.data?.url ?? '/';
  let targetUrl: URL;
  try {
    targetUrl = new URL(rawUrl, self.location.origin);
    // Security: reject cross-origin URLs
    if (targetUrl.origin !== self.location.origin) {
      targetUrl.pathname = '/';
      targetUrl.search = '';
    }
  } catch {
    // Fallback if URL parsing fails
    targetUrl = new URL('/', self.location.origin);
  }

  event.waitUntil(
    self.clients.matchAll({ type: 'window' }).then((windows) => {
      // Try to focus existing window instead of opening new one
      for (const w of windows) {
        if (w.url === targetUrl.href && 'focus' in w) {
          return w.focus();
        }
      }
      // No matching window found, open new one
      return self.clients.openWindow(targetUrl.href);
    })
  );
});
