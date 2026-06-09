import type { Metadata } from 'next';
import type { ReactNode } from 'react';
import { defaultViewport } from '@/lib/config/viewport';
import MerchantClientLayout from './MerchantClientLayout';

export const viewport = defaultViewport;

export const metadata: Metadata = {
  title: 'Kadhia Marchand · Click & Collect',
  manifest: '/merchant-manifest.webmanifest',
  appleWebApp: {
    capable: true,
    statusBarStyle: 'black-translucent',
    title: 'Marchand',
  },
  formatDetection: { telephone: false },
  icons: {
    apple: '/icons/apple-touch-icon.png',
  },
};

export default function MerchantLayout({ children }: { children: ReactNode }) {
  return <MerchantClientLayout>{children}</MerchantClientLayout>;
}
