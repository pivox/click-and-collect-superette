import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PushNotificationToggle } from '@/components/shared/PushNotificationToggle';
import * as pushService from '@/lib/services/push-subscription.service';

vi.mock('@/lib/i18n/ClientLocaleContext', () => ({
  useClientLocale: () => ({
    t: (key: string) => key,
  }),
}));

vi.mock('@/lib/services/push-subscription.service', () => ({
  getPushPermissionState: vi.fn(),
  isWebPushSupported: vi.fn(),
  isPWAInstalledOnIOS: vi.fn(),
  subscribeToPush: vi.fn(),
  unsubscribeFromPush: vi.fn(),
  getPushSubscriptionStatus: vi.fn(),
}));

describe('PushNotificationToggle', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(pushService.isWebPushSupported).mockResolvedValue(false);
    vi.mocked(pushService.getPushPermissionState).mockResolvedValue('default');
    vi.mocked(pushService.isPWAInstalledOnIOS).mockResolvedValue(false);
  });

  it('should not render anything if web push is unsupported', async () => {
    render(React.createElement(PushNotificationToggle, { role: 'customer' }));

    await waitFor(() => {
      expect(screen.queryByText(/Enabled|Disabled|ios_help|permission_denied/i)).not.toBeInTheDocument();
    });
  });

  it('should show help message for iOS without PWA installed', async () => {
    // Mock iOS user agent
    Object.defineProperty(navigator, 'userAgent', {
      value: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_4 like Mac OS X)',
      configurable: true,
    });

    vi.mocked(pushService.isWebPushSupported).mockResolvedValue(false);
    vi.mocked(pushService.isPWAInstalledOnIOS).mockResolvedValue(false);

    render(React.createElement(PushNotificationToggle, { role: 'customer' }));

    await waitFor(() => {
      expect(screen.getByText('notifications.ios_help')).toBeInTheDocument();
    });
  });

  it('should show denied message when permission is denied', async () => {
    vi.mocked(pushService.isWebPushSupported).mockResolvedValue(true);
    vi.mocked(pushService.getPushPermissionState).mockResolvedValue('denied');

    render(React.createElement(PushNotificationToggle, { role: 'customer' }));

    await waitFor(() => {
      expect(screen.getByText('notifications.permission_denied')).toBeInTheDocument();
    });
  });

  it('should show enabled button when subscribed', async () => {
    vi.mocked(pushService.isWebPushSupported).mockResolvedValue(true);
    vi.mocked(pushService.getPushPermissionState).mockResolvedValue('granted');
    vi.mocked(pushService.getPushSubscriptionStatus).mockResolvedValue(true);

    render(React.createElement(PushNotificationToggle, { role: 'customer' }));

    await waitFor(() => {
      expect(screen.getByText('notifications.enabled')).toBeInTheDocument();
    });
  });

  it('should show disabled button when not subscribed', async () => {
    vi.mocked(pushService.isWebPushSupported).mockResolvedValue(true);
    vi.mocked(pushService.getPushPermissionState).mockResolvedValue('granted');
    vi.mocked(pushService.getPushSubscriptionStatus).mockResolvedValue(false);

    render(React.createElement(PushNotificationToggle, { role: 'customer' }));

    await waitFor(() => {
      expect(screen.getByText('notifications.disabled')).toBeInTheDocument();
    });
  });
});
