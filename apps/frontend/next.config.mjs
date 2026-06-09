import withPWAInit from '@ducanh2912/next-pwa';

// Note: Service worker is scoped to / (root) and shared between client and merchant PWAs.
// The merchant manifest references /merchant-manifest.webmanifest with scope: /merchant/,
// but the actual SW registration is at scope: / by design (one SW, shared precache).
// This is acceptable for Sprint 14 (both PWAs use the same offline strategy and cache).
// Future scoped SWs (one per route group) are not supported by @ducanh2912/next-pwa config.
const withPWA = withPWAInit({
  dest: 'public',
  cacheOnFrontendNav: true,
  aggressiveFrontEndNavCaching: true,
  reloadOnOnline: true,
  disable: process.env.NODE_ENV === 'development',
  customWorkerSrc: 'worker', // Custom SW handlers for Web Push (S14-003)
  workboxOptions: {
    disableDevLogs: true,
  },
});

/** @type {import('next').NextConfig} */
const nextConfig = {};

export default withPWA(nextConfig);
